<?php

trait ArgsTrait {
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argChooseTile() {
        return [
            'wildColor' => $this->getWildColor(),
        ];
    }

    function argChoosePlace() {
        $playerId = self::getActivePlayerId();
        $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));

        $placedTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('line'.$playerId));

        //$number = count($tiles);
        // TODO filter where player can place

        return [
            'placedTiles' => $placedTiles,
            'passWarning' => count($tiles) > 4,
        ];
    }

    function argPlayTile() {
        $playerId = self::getActivePlayerId();
        $tiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));

        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $row = $selectedPlace[0];
        $column = $selectedPlace[1];
        $selectedColor = ($row + $this->indexForDefaultWall[$column] - 1) % 5 + 1; // TODO
        $tiles = array_slice(array_values(array_filter($tiles, fn($tile) => $tile->type == $selectedColor)), 0, $row);

        $number = count($tiles);


        return [
            'selectedPlace' => $selectedPlace,
            'lines' => $this->availableLines($playerId),
            'number' => $number,
            'color' => $number > 0 ? $this->getColor($tiles[0]->type) : null,
            'i18n' => ['color'],
            'type' => $number > 0 ? $tiles[0]->type : null,
        ];
    }
}