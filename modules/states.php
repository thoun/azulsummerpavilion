<?php

trait StateTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    
    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stFillFactories() {
        $playerNumber = intval(self::getUniqueValueFromDB("SELECT count(*) FROM player "));

        $factories = [];

        $firstPlayerTile = $this->getTilesFromDb($this->tiles->getCardsOfType(0, null))[0];
        $this->tiles->moveCard($firstPlayerTile->id, 'factory', 0);
        $factories[0] = [$firstPlayerTile];

        $factoryNumber = $this->getFactoryNumber($playerNumber);
        for ($factory=1; $factory<=$factoryNumber; $factory++) {
            $factories[$factory] = $this->getTilesFromDb($this->tiles->pickCardsForLocation(4, 'deck', 'factory', $factory));
        }

        $this->gamestate->changeActivePlayer(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));

        self::incStat(1, 'roundsNumber');
        self::incStat(1, 'firstPlayer', intval(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN)));

        self::DbQuery("UPDATE player SET passed = false, auto_pass = false");

        self::notifyAllPlayers("factoriesFilled", clienttranslate('Round ${round_number}/6 begins !'), [
            'factories' => $factories,
            'remainingTiles' => intval($this->tiles->countCardInLocation('deck')),
            'roundNumber' => intval(self::getStat('roundsNumber')),
            'round_number' => intval(self::getStat('roundsNumber')), // for logs
        ]);


        // place stored tiles in hand
        $playersIds = $this->getPlayersIds();
        foreach ($playersIds as $playerId) {
            if (intval($this->tiles->countCardInLocation('corner', $playerId)) > 0) {
                $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('corner', $playerId));
                $this->tiles->moveAllCardsInLocation('corner', 'hand', $playerId, $playerId);

                self::notifyAllPlayers("cornerToHand", '', [
                    'playerId' => $playerId,
                    'tiles' => $tiles,
                ]);
            }
        }

        $this->gamestate->nextState('next');
    }

    function stChooseTile() {
        if (!$this->tableOptions->isTurnBased()) {
            return;
        }

        $factoriesTiles = array_filter($this->getTilesFromDb($this->tiles->getCardsInLocation('factory')), fn($tile) => $tile->type > 0);

        $possibleTile = null;

        $factories = array_values(array_unique(array_map(fn($tile) => $tile->space, $factoriesTiles)));
        
        $wildColor = $this->getWildColor();
        foreach ($factories as $factory) {
            $factoryTiles = array_filter($factoriesTiles, fn($tile) => $tile->space === $factory);

            foreach($factoryTiles as $tile) {
                $isWild = $tile->type == $wildColor;
                if ($isWild && $this->array_some($factoryTiles, fn($factoryTile) => !in_array($factoryTile->type, [0, $wildColor]))) {
                    continue; // ignore wilds we cannot take
                }

                if ($possibleTile === null) {
                    $possibleTile = $tile;
                } else if ($tile->type !== $possibleTile->type || $tile->space !== $possibleTile->space) {
                    // already another possible tile, we can't play automatically
                    return;
                }
            }
        }

        if ($possibleTile !== null) { // play automatically this tile
            $this->takeTiles($possibleTile->id, true, true);
        }
    }

    function stNextPlayerAcquire() {
        $this->setGlobalVariable(UNDO_SELECT, null);

        $factoriesAllEmpty = $this->tiles->countCardInLocation('factory') == 0;
        $playerId = self::getActivePlayerId();
        self::giveExtraTime($playerId);

        self::incStat(1, 'turnsNumber');
        self::incStat(1, 'turnsNumber', $playerId);

        if ($factoriesAllEmpty) {
            $this->gamestate->changeActivePlayer(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));
            $this->gamestate->nextState('endAcquire');
        } else {
            $this->activeNextPlayer();
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stChoosePlace() {
        $playerId = self::getActivePlayerId();
        
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        if (count(array_filter($hand, fn($tile) => $tile->type > 0)) == 0) {
            $this->applyPass($playerId);
        }
    }

    function stNextPlayerPlay() {
        $this->setGlobalVariable(UNDO_PLACE, null);

        $allPassed = intval(self::getUniqueValueFromDB("SELECT count(*) FROM player WHERE passed = FALSE")) == 0;
        $playerId = intval(self::getActivePlayerId());

        self::incStat(1, 'turnsNumber');
        self::incStat(1, 'turnsNumber', $playerId);

        $this->fillSupply();

        if ($allPassed) {
            $this->gamestate->nextState('endRound');
        } else {
            $playerId = intval($this->activeNextPlayer());
        
            while (boolval(self::getUniqueValueFromDB("SELECT passed FROM player WHERE player_id = $playerId"))) {
                $playerId = intval($this->activeNextPlayer());
            }

            $autoPass = boolval(self::getUniqueValueFromDB("SELECT auto_pass FROM player WHERE player_id = $playerId"));
            if ($autoPass) {
                self::DbQuery("UPDATE player SET auto_pass = FALSE WHERE player_id = $playerId" );
                $this->applyPass($playerId, true); // handles the redirection
            } else {
                self::giveExtraTime($playerId);

                $this->gamestate->nextState('nextPlayer');
            }
        }
    }

    function stEndRound() {    
        $firstPlayerTile = $this->getTilesFromDb($this->tiles->getCardsOfType(0))[0];
        $this->tiles->moveCard($firstPlayerTile->id, 'factory', 0);

        $round = $this->getRound();
        $this->gamestate->nextState($round < 6 ? 'newRound' : 'endScore');
    }

    private function endScoreNotifs(array $playersIds, array $walls, bool $variant) {
        // Gain points for each complete star on your wall.
        for ($star = 0; $star <= 6; $star++) {
            $this->notifCompleteStar($playersIds, $walls, $star, $variant);
        }
        // Gain 4/8/12/16 points for complete sets of 1/2/3/4.
        for ($number = 1; $number <= 4; $number++) {
            $this->notifCompleteNumbers($playersIds, $walls, $number, $variant);
        }
    }

    function stEndScore() {
        $variant = $this->isVariant();
        $playersIds = $this->getPlayersIds();

        $walls = [];
        foreach ($playersIds as $playerId) {
            $walls[$playerId] = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
        }
        
        $fastScoring = $this->isFastScoring();
        if ($fastScoring) {
            $this->endScoreNotifs($playersIds, $walls, $variant);
        } else {
            foreach($playersIds as $playerId) {
                $this->endScoreNotifs([$playerId], $walls, $variant);
            }
        }

        $this->gamestate->nextState('endGame');
    }
    
}
