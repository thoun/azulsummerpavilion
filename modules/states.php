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

        self::DbQuery("UPDATE player SET passed = false");
        // TODO notif players ?

        self::notifyAllPlayers("factoriesFilled", clienttranslate("A new round begins !"), [
            'factories' => $factories,
            'remainingTiles' => intval($this->tiles->countCardInLocation('deck')),
        ]);

        self::incStat(1, 'roundsNumber');
        self::incStat(1, 'firstPlayer', intval(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN)));

        // TODO place stored tiles in hand

        $this->gamestate->nextState('next');
    }

    function stNextPlayerAcquire() {
        $factoriesAllEmpty = $this->tiles->countCardInLocation('factory') == 0;
        $playerId = self::getActivePlayerId();

        self::incStat(1, 'turnsNumber');
        self::incStat(1, 'turnsNumber', $playerId);

        if ($factoriesAllEmpty) {
            $this->gamestate->nextState('endAcquire');
        } else {
            $this->activeNextPlayer();
        
            $playerId = self::getActivePlayerId();
            self::giveExtraTime($playerId);

            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stNextPlayerPlay() {
        $allPassed = intval(self::getUniqueValueFromDB("SELECT count(*) FROM player WHERE passed = FALSE")) == 0;
        $playerId = self::getActivePlayerId();

        self::incStat(1, 'turnsNumber');
        self::incStat(1, 'turnsNumber', $playerId);

        if ($allPassed) {
            $this->gamestate->nextState('endRound');
        } else {
            $this->activeNextPlayer();
        
            $playerId = self::getActivePlayerId();
            while (boolval(self::getUniqueValueFromDB("SELECT passed FROM player WHERE player_id = $playerId"))) {
                $this->activeNextPlayer();
            
                $playerId = self::getActivePlayerId();

            }
            self::giveExtraTime($playerId);

            $this->gamestate->nextState('nextPlayer');
        }
    }


    function stEndRound() {
        $round = $this->getRound();
        $this->gamestate->nextState($round < 6 ? 'newRound' : 'endScore');
    }

    // TODO delete
    function stPlaceTiles() {
        $playersIds = $this->getPlayersIds();

        $this->notifPlaceLines($playersIds);
    
        $firstPlayerTile = $this->getTilesFromDb($this->tiles->getCardsOfType(0))[0];
        $this->tiles->moveCard($firstPlayerTile->id, 'factory', 0);

        if ($this->getGameProgression() == 100) {
            $this->gamestate->nextState('endScore');
        } else {
            $playerId = intval(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));
            $this->gamestate->changeActivePlayer($playerId);
            self::giveExtraTime($playerId);

            $this->gamestate->nextState('newRound');
        }
    }

    private function endScoreNotifs(array $playersIds, array $walls, bool $variant) {
        // Gain points for each complete star on your wall.
        for ($star = 0; $star <= 6; $star++) {
            $this->notifCompleteStar($playersIds, $walls, $star, $variant);
        }
        // Gain 4/8/12/16 points for complete sets of 1/2/3/4.
        for ($number = 1; $number <= 4; $number++) {
            $this->notifCompleteNumbers($playersIds, $walls, $number);
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

        //$this->gamestate->jumpToState(ST_FILL_FACTORIES);
        $this->gamestate->nextState('endGame');
    }
    
}
