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

    function debugEmptyFactories($full = false) {
        $this->debugRemoveFp();

        $factoryNumber = $this->getFactoryNumber();
        for ($i = 1; $i<=$factoryNumber; $i++) {
            if (intval($this->tiles->countCardInLocation('factory', $i)) > 0) {
                $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $i));
                foreach ($tiles as $key => $tile) {
                    if ($full || $i > 1 || $key > 0) {
                        $this->tiles->moveCard($tile->id, 'discard');
                    }
                }
            }
        }

        //$this->tiles->moveAllCardsInLocation('corner', 'hand', $playerId, $playerId);
    }

    function debugRemoveFp() {
        $playerId = 2343492;
        $factoryTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', 0));
        $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
        $hasFirstPlayer = count($firstPlayerTokens) > 0;
        if ($hasFirstPlayer) {
            $this->tiles->moveCards(array_map('getIdPredicate', $firstPlayerTokens), 'hand', $playerId);
            $this->putFirstPlayerTile($playerId, $firstPlayerTokens);
        }
    }

    public function debugReplacePlayersIds() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        $ids = array_map(fn($dbPlayer) => intval($dbPlayer['player_id']), array_values($this->getCollectionFromDb('select player_id from player order by player_no')));

		// Id of the first player in BGA Studio
		$sid = 2343492;
		
		foreach ($ids as $id) {
			// basic tables
			self::DbQuery("UPDATE player SET player_id=$sid WHERE player_id = $id" );
			self::DbQuery("UPDATE global SET global_value=$sid WHERE global_value = $id" );
			self::DbQuery("UPDATE stats SET stats_player_id=$sid WHERE stats_player_id = $id" );

			// 'other' game specific tables. example:
			// tables specific to your schema that use player_ids
			self::DbQuery("UPDATE tile SET card_location='line$sid' WHERE card_location = 'line$id'" );
			self::DbQuery("UPDATE tile SET card_location='wall$sid' WHERE card_location = 'wall$id'" );
			self::DbQuery("UPDATE tile SET card_location_arg=$sid WHERE card_location_arg = $id" );
			
			++$sid;
		}
	}

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
