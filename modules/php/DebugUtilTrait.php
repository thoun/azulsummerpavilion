<?php
namespace Bga\Games\AzulSummerPavilion;

use Bga\GameFrameworkPrototype\Helpers\Arrays;
use Bga\Games\AzulSummerPavilion\States\ChoosePlace;

function debug(...$debugData) {
    if (\Bga\GameFramework\Table::getBgaEnvironment() != 'studio') { 
        return;
    }die('debug data : <pre>'.substr(json_encode($debugData, JSON_PRETTY_PRINT), 1, -1).'</pre>');
}

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

/*
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
    }*/

    function debugSetWallTile(int $playerId, int $star, int $space, int $color) {
        $tile = $this->getTilesFromDb($this->tiles->getCardsOfTypeInLocation($color, null, 'deck'))[0];

        $this->tiles->moveCard($tile->id, 'wall'.$playerId, $star*100 + $space);
    }

    function debug_setRound(int $round = 6) {
        $this->setStat($round, 'roundsNumber');
    }

    function debug_emptyFactories(bool $full = false, int $playerId = 2343492) {
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

    function debug_testOnlyWild() {
        $this->debug_removeFp();

        $factoryNumber = $this->getFactoryNumber();
        for ($i = 1; $i<=$factoryNumber; $i++) {
            if (intval($this->tiles->countCardInLocation('factory', $i)) > 0) {
                $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $i));
                foreach ($tiles as $key => $tile) {
                    if ($i > 1 || $key > 2) {
                        $this->tiles->moveCard($tile->id, 'void');
                    } else {
                        $type = $key == 0 ? 2 : 1;
                        $this->DbQuery("UPDATE `tile` SET `card_type` = $type, card_location_arg = 0 WHERE `card_id` = $tile->id");
                    }
                }
            }
        }

        //$this->tiles->moveAllCardsInLocation('corner', 'hand', $playerId, $playerId);
    }

    function debug_setType(int $tileId, int $type) {
        $this->DbQuery("UPDATE `tile` SET `card_type` = $type WHERE `card_id` = $tileId");
    }

    function debug_removeFp(int $playerId = 2343492) {
        $factoryTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', 0));
        $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
        $hasFirstPlayer = count($firstPlayerTokens) > 0;
        if ($hasFirstPlayer) {
            $this->tiles->moveCards(array_map(fn($t) => $t->id, $firstPlayerTokens), 'hand', $playerId);
            $this->putFirstPlayerTile($playerId, $firstPlayerTokens);
        }
    }

    function debug_place() {
        $this->gamestate->jumpToState(ChoosePlace::class);
    }

    function debug_playToEndRound() {
        $round = $this->getStat('roundsNumber');
        $count = 0;
        $stopIfAtState = null;
        //$stopIfAtState = ST_MULTIPLAYER_PRIVATE_CHOOSE_COLUMNS;
        while ($this->getStat('roundsNumber') == $round && $count < 100 && ($stopIfAtState === null || $this->gamestate->getCurrentMainStateId() < $stopIfAtState)) {
            $count++;
            foreach($this->gamestate->getActivePlayerList() as $playerId) {
                $playerId = (int)$playerId;
                $this->gamestate->runStateClassZombie($this->gamestate->getCurrentState($playerId), $playerId);
            }
        }
    }
}
