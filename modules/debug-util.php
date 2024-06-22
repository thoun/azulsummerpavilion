<?php

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    // shortcut to launch multiple debug lines
    function d() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        $this->setStat(6, 'roundsNumber');
        //$this->debugEmptyFactories();
        //$this->debugRemoveFp();
        //$this->stFillFactories();
    }

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }
        $this->debugSetWallTile(2343492, 1, 6, 5);
        $this->debugSetWallTile(2343492, 1, 3, 5);
        $this->debugSetWallTile(2343492, 1, 4, 2);
        $this->debugSetWallTile(2343492, 1, 1, 3);
        $this->debugSetWallTile(2343492, 1, 2, 4);
        $this->debugSetWallTile(2343492, 1, 5, 4);
        $this->debugSetWallTile(2343492, 2, 3, 3);
        $this->debugSetWallTile(2343492, 2, 2, 3);
        $this->debugSetWallTile(2343492, 4, 2, 3);
        $this->debugSetWallTile(2343492, 0, 2, 3);
        $this->debugSetWallTile(2343492, 5, 2, 3);
        $this->debugSetWallTile(2343492, 6, 2, 3);
        $this->debugSetWallTile(2343492, 3, 2, 3);

        //$this->setStat(5, 'roundsNumber');
    }

    function debugSetWallTile(int $playerId, int $star, int $space, int $color) {
        $tile = $this->getTilesFromDb($this->tiles->getCardsOfTypeInLocation($color, null, 'deck'))[0];

        $this->tiles->moveCard($tile->id, 'wall'.$playerId, $star*100 + $space);
    }

    function debug_emptyFactories($full = true, int $playerId = 2343492) {
        $this->debug_removeFp();

        $factoryNumber = $this->getFactoryNumber();
        for ($i = 1; $i<=$factoryNumber; $i++) {
            if (intval($this->tiles->countCardInLocation('factory', $i)) > 0) {
                $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $i));
                foreach ($tiles as $key => $tile) {
                    if ($full || $i > 1 || $key > 0) {
                        $this->tiles->moveCard($tile->id, 'hand', $playerId + ($i % 2));
                    }
                }
            }
        }

        //$this->tiles->moveAllCardsInLocation('corner', 'hand', $playerId, $playerId);
    }

    function debug_removeFp(int $playerId = 2343492) {
        $factoryTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', 0));
        $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
        $hasFirstPlayer = count($firstPlayerTokens) > 0;
        if ($hasFirstPlayer) {
            $this->tiles->moveCards(array_map('getIdPredicate', $firstPlayerTokens), 'hand', $playerId);
            $this->putFirstPlayerTile($playerId, $firstPlayerTokens);
        }
    }

    function debug_place() {
        $this->gamestate->jumpToState(ST_PLAYER_CHOOSE_PLACE);
    }

    public function loadBugReportSQL(int $reportId, array $studioPlayers): void
    {
        $prodPlayers = $this->getObjectListFromDb("SELECT `player_id` FROM `player`", true);
        $prodCount = count($prodPlayers);
        $studioCount = count($studioPlayers);
        if ($prodCount != $studioCount) {
            throw new BgaVisibleSystemException("Incorrect player count (bug report has $prodCount players, studio table has $studioCount players)");
        }

        // SQL specific to your game
        // For example, reset the current state if it's already game over
        $sql = [
            "UPDATE `global` SET `global_value` = 20 WHERE `global_id` = 1 AND `global_value` = 99"
        ];
        foreach ($prodPlayers as $index => $prodId) {
            $studioId = $studioPlayers[$index];
            // SQL common to all games
            $sql[] = "UPDATE `player` SET `player_id` = $studioId WHERE `player_id` = $prodId";
            $sql[] = "UPDATE `global` SET `global_value` = $studioId WHERE `global_value` = $prodId";
            $sql[] = "UPDATE `stats` SET `stats_player_id` = $studioId WHERE `stats_player_id` = $prodId";

            // SQL specific to your game
            $sql[] = "UPDATE `tile` SET `card_location_arg` = $studioId WHERE `card_location_arg` = $prodId";
			$sql[] = "UPDATE tile SET card_location='wall$studioId' WHERE card_location = 'wall$prodId'";
        }
        foreach ($sql as $q) {
            $this->DbQuery($q);
        }
    }

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
