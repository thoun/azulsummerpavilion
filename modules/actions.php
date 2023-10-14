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

        $factory = $tile->column;
        $factoryTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $factory));
        
        $firstPlayerTokens = [];
        $selectedTiles = [];
        $discardedTiles = [];
        $hasFirstPlayer = false;

        if ($factory == 0) {
            $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
            $hasFirstPlayer = count($firstPlayerTokens) > 0;

            foreach($factoryTiles as $factoryTile) {
                if ($tile->type == $factoryTile->type) {
                    $selectedTiles[] = $factoryTile;
                }
            }

            $this->tiles->moveCards(array_map('getIdPredicate', $selectedTiles), 'hand', $playerId);

            if ($hasFirstPlayer) {
                $this->putFirstPlayerTile($firstPlayerTokens, $playerId);
            }
        } else {
            $discardOtherTiles = true;

            foreach($factoryTiles as $factoryTile) {
                if ($tile->type == $factoryTile->type) {
                    $selectedTiles[] = $factoryTile;
                } else if ($discardOtherTiles) {
                    $discardedTiles[] = $factoryTile;
                }
            }

            $this->tiles->moveCards(array_map('getIdPredicate', $selectedTiles), 'hand', $playerId);
            $this->tiles->moveCards(array_map('getIdPredicate', $discardedTiles), 'factory', 0);
        }

        
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

    function selectPlace(int $line, int $column, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('selectPlace');
        }
        
        $playerId = self::getActivePlayerId();

        /*if (array_search($line, $this->availableLines($playerId)) === false) {
            throw new BgaUserException('Line not available');
        }*/

        /*$tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $this->placeTilesOnLine($playerId, $tiles, $line, true);

        $this->setGlobalVariable(UNDO_PLACE, new Undo($tiles, null, null, false)); */
        $this->setGlobalVariable(SELECTED_PLACE, [$line, $column]);

        $this->gamestate->nextState('next');
    }

    function playTile(int $line, $skipActionCheck = false) {
        if (!$skipActionCheck) {
            $this->checkAction('playTile');
        }
        
        $playerId = self::getActivePlayerId();

        /*if (array_search($line, $this->availableLines($playerId)) === false) {
            throw new BgaUserException('Line not available');
        }*/

        $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $this->placeTilesOnLine($playerId, $tiles, $line, true);

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

}
