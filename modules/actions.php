<?php

trait ActionTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in azul.action.php)
    */
    
    function takeTiles(int $id, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('takeTiles');
        }
        
        $playerId = intval(self::getActivePlayerId());

        // for undo
        $previousFirstPlayer = intval(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));

        $tile = $this->getTileFromDb($this->tiles->getCard($id));

        if ($tile->location !== 'factory') {
            throw new BgaUserException("Tile is not in a factory");
        }
        if ($tile->type === 0) {
            throw new BgaUserException("Tile is First Player token");
        }

        $factory = $tile->space;
        $factoryTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $factory));

        $wildColor = $this->getWildColor();
        $isWild = $tile->type == $wildColor;
        if ($isWild) {
            if ($this->array_some($factoryTiles, fn($factoryTile) => !in_array($factoryTile->type, [0, $wildColor]))) {
                throw new BgaUserException(self::_("You cannot take a wild tile"));
            }
        }

        $wildTiles = array_values(array_filter($factoryTiles, fn($factoryTile) => $factoryTile->type == $wildColor));
        
        $firstPlayerTokens = [];
        $selectedTiles = [];
        $discardedTiles = [];
        $hasFirstPlayer = false;

        if (!$isWild) {
            foreach($factoryTiles as $factoryTile) {
                if ($tile->type == $factoryTile->type) {
                    $selectedTiles[] = $factoryTile;
                }
            }
        }
        if (count($wildTiles) > 0) {
            $selectedTiles[] = $wildTiles[0];
        }

        if ($factory == 0) {
            $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
            $hasFirstPlayer = count($firstPlayerTokens) > 0;

            if ($hasFirstPlayer) {
                $selectedTiles[] = $firstPlayerTokens[0];
                $this->putFirstPlayerTile($playerId, $selectedTiles);
            }
        } else {
            foreach($factoryTiles as $factoryTile) {
                if (!$this->array_some($selectedTiles, fn($selectedTile) => $selectedTile->id == $factoryTile->id)) {
                    $discardedTiles[] = $factoryTile;
                }
            }
            $this->tiles->moveCards(array_map('getIdPredicate', $discardedTiles), 'factory', 0);
        }
        $this->tiles->moveCards(array_map('getIdPredicate', $selectedTiles), 'hand', $playerId);

        
        if ($hasFirstPlayer) {
            $message = clienttranslate('${player_name} takes ${number} ${color} and First Player tile');
        } else {
            $message = clienttranslate('${player_name} takes ${number} ${color}');
        }

        self::notifyAllPlayers('tilesSelected', $message, [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'number' => count($selectedTiles),
            'color' => $this->getColor($tile->type),   
            'i18n' => ['color'],           
            'type' => $tile->type,
            'preserve' => [ 2 => 'type' ],
            'selectedTiles' => $selectedTiles,
            'discardedTiles' => $discardedTiles,
            'fromFactory' => $factory,
        ]);

        $this->setGlobalVariable(UNDO_SELECT, new Undo(
            array_merge($selectedTiles, $discardedTiles, $firstPlayerTokens),
            $factory, 
            $previousFirstPlayer,
            null,
            false
        ));

        if ($this->allowUndo()) {
            $this->gamestate->nextState('confirm');
        } else {
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function confirmAcquire($skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('confirmAcquire');
        }
        
        $this->gamestate->nextState('nextPlayer');
    }

    function undoTakeTiles() {
        self::checkAction('undoTakeTiles'); 

        if (!$this->allowUndo()) {
            throw new BgaUserException('Undo is disabled');
        }
        
        $playerId = intval(self::getActivePlayerId());

        $undo = $this->getGlobalVariable(UNDO_SELECT);

        $factoryTilesBefore = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $undo->from));
        $this->tiles->moveCards(array_map('getIdPredicate', $undo->tiles), 'factory', $undo->from);
        self::setGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN, $undo->previousFirstPlayer);

        self::notifyAllPlayers('undoTakeTiles', clienttranslate('${player_name} cancels tile selection'), [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'undo' => $undo,
            'factoryTilesBefore' => $factoryTilesBefore,
        ]);

        $this->gamestate->nextState('undo');
    }

    function selectPlace(int $star, int $space, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('selectPlace');
        }

        $args = $this->argChoosePlace();
        if (!in_array($star * 100 + $space, $args['possibleSpaces'])) {
            throw new BgaUserException('Space not available');
        }

        //$this->setGlobalVariable(UNDO_PLACE, new Undo($tiles, null, null, false));
        $this->setGlobalVariable(SELECTED_PLACE, [$star, $space]);

        $this->gamestate->nextState('next');

        // if only one option (no use of wilds), auto-play it
        $args = $this->argChooseColor();
        if (count($args['possibleColors']) == 1) {
            $this->selectColor($args['possibleColors'][0], true);
        }
    }

    function selectColor(int $color, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('selectColor');
        }
        
        $this->setGlobalVariable(SELECTED_COLOR, $color);

        $this->gamestate->nextState('next');

        // if only one option (no wild, or exact count of color+wilds), auto-play it
        $args = $this->argPlayTile();
        if ($args['maxWildTiles'] === 0) {
            $this->playTile(0, true);
        } else if ($args['maxWildTiles'] + $args['maxColor'] == $args['number']) {
            $this->playTile($args['maxWildTiles'], true);
        }
    }

    function playTile(int $wilds, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('playTile');
        }
        
        $playerId = intval(self::getActivePlayerId());
        $variant = $this->isVariant();

        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));

        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $star = $selectedPlace[0];
        $space = $selectedPlace[1];
        $selectedColor = $this->getGlobalVariable(SELECTED_COLOR);
        $wildColor = $this->getWildColor();
        $number = $this->getSpaceNumber($star, $space, $variant);

        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $selectedColor));
        $wildTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $wildColor));

        $tiles = array_merge(
            array_slice($colorTiles, 0, $number - $wilds),
            array_slice($wildTiles, 0, $wilds),
        );

        $placedTile = $tiles[0];
        $discardedTiles = array_slice($tiles, 1);
        $placedTile->star = $star;
        $placedTile->space = $space;
        $this->tiles->moveCard($placedTile->id, 'wall'.$playerId, $placedTile->star * 100 + $placedTile->space);
        $this->tiles->moveCards(array_map('getIdPredicate', $discardedTiles), 'discard');

        $scoredTiles = $this->getScoredTiles($playerId, $placedTile);
        $points = count($scoredTiles);

        self::notifyAllPlayers('placeTileOnWall', clienttranslate('${player_name} places ${number} ${color} and gains ${points} point(s)'), [
            'placedTile' => $placedTile,
            'discardedTiles' => $discardedTiles,
            'scoredTiles' => $scoredTiles,
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'number' => 1,
            'color' => $this->getColor($placedTile->type),
            'i18n' => ['color'],
            'type' => $placedTile->type,
            'preserve' => [ 2 => 'type' ],
            'points' => $points,
        ]);

        $this->incPlayerScore($playerId, $points);

        self::incStat($points, 'pointsCompleteLine'); // TODO
        self::incStat($points, 'pointsCompleteLine', $playerId); // TODO

        $this->setGlobalVariable(UNDO_PLACE, new Undo($tiles, null, null, false));

        if ($this->allowUndo()) {
            $this->gamestate->nextState('confirm');
        } else {
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function confirmPlay($skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('confirmPlay');
        }
        
        $this->gamestate->nextState('nextPlayer');
    }
    
    function undoPlayTile() {
        self::checkAction('undoPlayTile'); 

        if (!$this->allowUndo()) {
            throw new BgaUserException('Undo is disabled');
        }
        
        $playerId = intval(self::getActivePlayerId());       

        $undo = $this->getGlobalVariable(UNDO_PLACE);

        $this->tiles->moveCards(array_map('getIdPredicate', $undo->tiles), 'hand', $playerId);

        self::notifyAllPlayers('undoPlayTile', clienttranslate('${player_name} cancels tile placement'), [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'undo' => $undo,
        ]);
        
        $this->gamestate->nextState('undo');
    }

    function pass($skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('pass');
        }
        
        $playerId = self::getActivePlayerId();

        self::DbQuery("UPDATE player SET passed = TRUE WHERE player_id = $playerId" );
        // TODO notif players ?

        $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        if (count($tiles) > 4) { // TODO always ask ?
            $this->gamestate->nextState('chooseKeptTiles');
        } else if ($this->allowUndo()) {
            $this->gamestate->nextState('confirm');
        } else {
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function selectKeptTiles(array $ids, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('selectKeptTiles');
        }

        if (count($ids) > 4) {
            throw new BgaUserException("You cannot keep more than 4 tiles");
        }
        
        $playerId = intval(self::getActivePlayerId());
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $keptTiles = [];
        $discardedTiles = [];
        foreach ($hand as $tile) {
            if ($tile->type > 0) {
                if (in_array($tile->id, $ids)) {
                    $keptTiles[] = $tile;
                } else {
                    $discardedTiles[] = $tile;
                }
            }
        }

        if (count($ids) != count($keptTiles)) {
            throw new BgaUserException("You must select hand tiles");
        }

        $keptNumber = count($keptTiles);
        $discardedNumber = count($discardedTiles);

        if ($keptNumber > 0 || $discardedNumber > 0) {        
            $this->tiles->moveCards(array_map('getIdPredicate', $keptTiles), 'corner', $playerId);
            $this->tiles->moveCards(array_map('getIdPredicate', $discardedTiles), 'discard');

            self::notifyAllPlayers('putToCorner', clienttranslate('${player_name} keeps ${keptNumber} tiles and discards ${discardedNumber} tiles'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'keptTiles' => $keptTiles,
                'discardedTiles' => $discardedTiles,
                'keptNumber' => $keptNumber,
                'discardedNumber' => $discardedNumber,
            ]);
        }

        $this->gamestate->nextState('nextPlayer');
    }

}
