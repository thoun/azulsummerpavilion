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
        $previousScore = $this->getPlayerScore($playerId);

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

        $additionalWildTile = null;        
        $firstPlayerTokens = [];
        $selectedNormalTiles = 0;
        $selectedTiles = [];
        $discardedTiles = [];
        $hasFirstPlayer = false;
        $pointsLossFirstTile = 0;

        if (!$isWild) {
            foreach($factoryTiles as $factoryTile) {
                if ($tile->type == $factoryTile->type) {
                    $selectedTiles[] = $factoryTile;
                    $selectedNormalTiles++;
                }
            }
        }
        if (count($wildTiles) > 0) {
            $selectedTiles[] = $wildTiles[0];

            if (!$isWild) {
                $additionalWildTile = $wildTiles[0];
            }
        }

        if ($factory == 0) {
            $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
            $hasFirstPlayer = count($firstPlayerTokens) > 0;

            if ($hasFirstPlayer) {
                $selectedTiles[] = $firstPlayerTokens[0];
                $pointsLossFirstTile = $this->putFirstPlayerTile($playerId, $selectedTiles);
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
            $message = clienttranslate('${player_name} takes ${number} ${color} ${wild} and First Player tile');
        } else {
            $message = clienttranslate('${player_name} takes ${number} ${color} ${wild}');
        }

        self::notifyAllPlayers('tilesSelected', $message, [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'number' => $selectedNormalTiles,
            'color' => $this->getColor($tile->type),   
            'wild' => $additionalWildTile != null ? $this->getColor($additionalWildTile->type) : '',   
            'i18n' => ['color'],           
            'type' => $tile->type,    
            'typeWild' => $additionalWildTile != null ? $additionalWildTile->type : null,
            'preserve' => [ 'type', 'typeWild' ],
            'selectedTiles' => $selectedTiles,
            'discardedTiles' => $discardedTiles,
            'fromFactory' => $factory,
        ]);

        $this->setGlobalVariable(UNDO_SELECT, new UndoSelect(
            array_merge($selectedTiles, $discardedTiles, $firstPlayerTokens),
            $selectedNormalTiles,
            $additionalWildTile != null,
            $pointsLossFirstTile,
            $factory, 
            $previousFirstPlayer,
            $previousScore,
        ));

        if ($this->allowUndo()) {
            $this->gamestate->nextState('confirm');
        } else {            
            $this->applyConfirmTiles($playerId);
        }
    }

    function confirmAcquire() {
        $this->checkAction('confirmAcquire');

        $playerId = intval(self::getActivePlayerId());
        $this->applyConfirmTiles($playerId);
    }

    function applyConfirmTiles(int $playerId) {
        $undo = $this->getGlobalVariable(UNDO_SELECT);
        self::incStat($undo->normalTiles, 'normalTilesCollected');
        self::incStat($undo->normalTiles, 'normalTilesCollected', $playerId);
        if ($undo->wildTile) {
            self::incStat(1, 'wildTilesCollected');
            self::incStat(1, 'wildTilesCollected', $playerId);
        }
        if ($undo->pointsLossFirstTile > 0) {
            self::incStat($undo->pointsLossFirstTile, 'pointsLossFirstTile');
            self::incStat($undo->pointsLossFirstTile, 'pointsLossFirstTile', $playerId);
            
        }
        
        $this->gamestate->jumpToState(ST_NEXT_PLAYER_ACQUIRE);
    }

    function undoTakeTiles() {
        self::checkAction('undoTakeTiles'); 

        if (!$this->allowUndo()) {
            throw new BgaUserException('Undo is disabled');
        }
        
        $playerId = intval(self::getActivePlayerId());

        $undo = $this->getGlobalVariable(UNDO_SELECT);
        $this->setPlayerScore($playerId, $undo->previousScore);
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

        $this->applySelectPlace($star, $space);
    }

    function applySelectPlace(int $star, int $space) {
        $this->setGlobalVariable(SELECTED_PLACE, [$star, $space]);
        $this->setGlobalVariable(UNDO_PLACE, null);

        // if only one option (no use of wilds), auto-play it
        $args = $this->argChooseColor();
        if (count($args['possibleColors']) > 1) {
            $this->gamestate->jumpToState(ST_PLAYER_CHOOSE_COLOR);
        } else {
            $this->applySelectColor($args['possibleColors'][0]);
        }
    }

    function selectColor(int $color, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('selectColor');
        }
        
        $this->applySelectColor($color);
    }

    function applySelectColor(int $color) {
        $this->setGlobalVariable(SELECTED_COLOR, $color);

        $playerId = intval(self::getActivePlayerId());

        // if only one option (no wild, or exact count of color+wilds), auto-play it
        $args = $this->argPlayTile();
        if ($args['maxWildTiles'] === 0) {
            $this->applyPlayTile($playerId, 0);
        } else if ($args['maxWildTiles'] + $args['maxColor'] == $args['number']) {
            $this->applyPlayTile($playerId, $args['maxWildTiles']);
        } else {
            $this->gamestate->jumpToState(ST_PLAYER_PLAY_TILE);
        }
    }

    function playTile(int $wilds, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('playTile');
        }
        
        $playerId = intval(self::getActivePlayerId());
        $this->applyPlayTile($playerId, $wilds);
    }

    function applyPlayTile(int $playerId, int $wilds) {

        $variant = $this->isVariant();

        // for undo
        $previousScore = $this->getPlayerScore($playerId);

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

        $this->setGlobalVariable(UNDO_PLACE, new UndoPlace($tiles, $previousScore, $points));

        $wall = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
        $additionalTiles = $this->additionalTilesDetail($wall, $placedTile);
        if ($additionalTiles['count'] > 0) {        
            $this->setGlobalVariable(ADDITIONAL_TILES_DETAIL, $additionalTiles);
        }

        if ($additionalTiles['count'] > 0) {
            $this->gamestate->jumpToState(ST_PLAYER_TAKE_BONUS_TILES);
        } else if ($this->allowUndo()) {
            $this->gamestate->jumpToState(ST_PLAYER_CONFIRM_PLAY);
        } else {
            $this->applyConfirmPlay($playerId);
        }
    }

    function confirmPlay() {
        $this->checkAction('confirmPlay');

        $playerId = intval(self::getActivePlayerId());   
     
        $this->applyConfirmPlay($playerId);
    }

    function applyConfirmPlay(int $playerId) {
        $undo = $this->getGlobalVariable(UNDO_PLACE);
        $count = count($undo->supplyTiles);
        if ($count > 0) {
            self::incStat($count, 'bonusTilesCollected');
            self::incStat($count, 'bonusTilesCollected', $playerId);
            self::incStat($count, 'bonusTile'.$count);
            self::incStat($count, 'bonusTile'.$count, $playerId);
        }

        self::incStat($undo->points, 'pointsWallTile');
        self::incStat($undo->points, 'pointsWallTile', $playerId);

        $this->gamestate->jumpToState(ST_NEXT_PLAYER_PLAY);
    }

    function confirmPass() {
        $this->checkAction('confirmPass');

        $playerId = intval(self::getActivePlayerId());   

        $this->applyConfirmPass($playerId);
    }

    function applyConfirmPass(int $playerId) {
        $undo = $this->getGlobalVariable(UNDO_PLACE);
        $count = $undo->points;
        if ($count > 0) {
            self::incStat($count, 'pointsLossDiscardedTiles');
            self::incStat($count, 'pointsLossDiscardedTiles', $playerId);
        }

        $this->gamestate->jumpToState(ST_NEXT_PLAYER_PLAY);
    }
    
    function undoPlayTile() {
        self::checkAction('undoPlayTile'); 

        if (!$this->allowUndo()) {
            throw new BgaUserException('Undo is disabled');
        }
        
        $playerId = intval(self::getActivePlayerId());       

        $undo = $this->getGlobalVariable(UNDO_PLACE);

        if ($undo) {
            $this->tiles->moveCards(array_map('getIdPredicate', $undo->tiles), 'hand', $playerId);

            foreach ($undo->supplyTiles as $tile) {
                $this->tiles->moveCard($tile->id, $tile->location, $tile->space);
            }
        }

        self::notifyAllPlayers('undoPlayTile', clienttranslate('${player_name} cancels tile placement'), [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'undo' => $undo,
        ]);

        $this->setGlobalVariable(UNDO_PLACE, null);
        $this->setGlobalVariable(SELECTED_PLACE, null);
        $this->setGlobalVariable(SELECTED_COLOR, null);
        $this->setGlobalVariable(ADDITIONAL_TILES_DETAIL, null);
        
        $this->gamestate->nextState('undo');
    }

    function undoPass($skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('undoPass');
        }

        if (!$this->allowUndo()) {
            throw new BgaUserException('Undo is disabled');
        }
        
        $playerId = intval(self::getActivePlayerId());       

        $undo = $this->getGlobalVariable(UNDO_PLACE);

        if ($undo) {
            $this->tiles->moveCards(array_map('getIdPredicate', $undo->tiles), 'hand', $playerId);

            foreach ($undo->supplyTiles as $tile) {
                $this->tiles->moveCard($tile->id, $tile->location, $tile->space);
            }
        }

        self::notifyAllPlayers('undoPlayTile', clienttranslate('${player_name} cancels ending the round'), [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'undo' => $undo,
        ]);

        $this->setGlobalVariable(UNDO_PLACE, null);
        
        $this->gamestate->nextState('undo');
    }

    function pass($skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('pass');
        }
        
        $playerId = self::getActivePlayerId();

        $this->applyPass($playerId);
    }

    function applyPass(int $playerId) {
        self::DbQuery("UPDATE player SET passed = TRUE WHERE player_id = $playerId" );
        self::notifyAllPlayers('pass', clienttranslate('${player_name} passes'), [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
        ]);

        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type > 0));

        $lastRound = $this->getRound() >= 6;

        if (!$lastRound && count($colorTiles) > 4) {
            $this->gamestate->jumpToState(ST_PLAYER_CHOOSE_KEPT_TILES);
        } else {
            $this->applySelectKeptTiles($playerId, $lastRound ? [] : array_map('getIdPredicate', $colorTiles));
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

        $this->applySelectKeptTiles($playerId, $ids);
    }

    function applySelectKeptTiles(int $playerId, array $ids) {
        // for undo
        $previousScore = $this->getPlayerScore($playerId);

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

        if ($discardedNumber > 0) {  
            $this->decPlayerScore($playerId, $discardedNumber); 
        }

        $this->setGlobalVariable(UNDO_PLACE, new UndoPlace($hand, $previousScore, count($discardedTiles)));

        if ($this->allowUndo() && count($ids) > 0) {
            $this->gamestate->jumpToState(ST_PLAYER_CONFIRM_PASS);
        } else {
            $this->applyConfirmPass($playerId);
        }
    }

    function cancel($skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('cancel');
        }
        
        $this->gamestate->nextState('cancel');
    }

    function takeBonusTiles(array $ids, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('takeBonusTiles');
        }

        $args = $this->argTakeBonusTiles();

        if (count($ids) != $args['count']) {
            throw new BgaUserException("You must select ".$args['count']." tiles");
        }
        
        $playerId = intval(self::getActivePlayerId());
        $supply = $this->getTilesFromDb($this->tiles->getCardsInLocation('supply'));
        $selectedTiles = [];
        foreach ($supply as $tile) {
            if (in_array($tile->id, $ids)) {
                $selectedTiles[] = $tile;
            }
        }

        if (count($ids) != count($selectedTiles)) {
            throw new BgaUserException("You must select supply tiles");
        }   

        $undo = $this->getGlobalVariable(UNDO_PLACE);
        $undo->supplyTiles = $selectedTiles;
        $this->setGlobalVariable(UNDO_PLACE, $undo);

        $this->tiles->moveCards(array_map('getIdPredicate', $selectedTiles), 'hand', $playerId);

        self::notifyAllPlayers('tilesSelected', clienttranslate('${player_name} takes ${number} tiles from supply'), [
            'playerId' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'number' => count($selectedTiles),
            'selectedTiles' => $selectedTiles,
            'fromSupply' => true,
        ]);

        if ($this->allowUndo()) {
            $this->gamestate->jumpToState(ST_PLAYER_CONFIRM_PLAY);
        } else {
            $this->applyConfirmPlay($playerId);
        }
    }

}
