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

    function argChooseColor() {
        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $row = $selectedPlace[0];
        $column = $selectedPlace[1];
        $selectedColor = ($row + $this->indexForDefaultWall[$column] - 1) % 5 + 1; // TODO

        $possibleColors = [];
        if ($selectedColor == 0) {
            $playerId = self::getActivePlayerId();
            $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
            $wildColor = $this->getWildColor();
            $number = $row;
            for ($i = 1; $i <=6; $i++) {
                if ($this->getMaxWildTiles($hand, $number, $i, $wildColor) !== null) {
                    $possibleColors[] = $i;
                }
            }

        } else {
            $possibleColors = [$selectedColor];
        }
        return [
            'possibleColors' => $possibleColors,
        ];
    }

    function getMaxWildTiles(array $hand, int $cost, int $color, int $wildColor) { // null if cannot pay, else number max of wild tiles that can be used (0 is still valid choice!)
        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $color));
        $wildTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $wildColor));

        if ($color == $wildColor) {
            return count($colorTiles) < $cost ? null : 0;
        } else if (count($colorTiles) + count($wildTiles) < $cost) {
            return null;
        } else {
            return min($cost - 1, count($wildTiles));
        }
    }

    function argPlayTile() {
        $playerId = self::getActivePlayerId();
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));

        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $row = $selectedPlace[0];
        $column = $selectedPlace[1];
        $selectedColor = ($row + $this->indexForDefaultWall[$column] - 1) % 5 + 1; // TODO
        $wildColor = $this->getWildColor();
        $number = $row;
        $maxWildTiles = $this->getMaxWildTiles($hand, $number, $selectedColor, $wildColor);

        return [
            'selectedPlace' => $selectedPlace,
            'number' => $number,
            'color' => $selectedColor,
            'wildColor' => $wildColor,
            'maxWildTiles' => $maxWildTiles,
        ];
    }
}