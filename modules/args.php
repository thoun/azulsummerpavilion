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

        $placedTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $wildColor = $this->getWildColor();
        $possibleSpaces = [];
        $variant = $this->isVariant();

        for ($star = 0; $star <= 6; $star++) {
            $forcedColor = $this->STANDARD_FACE_STAR_COLORS[$star];

            for ($space = 1; $space <= 6; $space++) {
                if ($this->array_some($placedTiles, fn($placedTile) => $placedTile->star == $star && $placedTile->space == $space)) {
                    continue;
                }

                $colors = [$forcedColor];
                if ($variant || $forcedColor == 0) {
                    $starTiles = array_values(array_filter($placedTiles, fn($placedTile) => $placedTile->star == $star));
                    $starColors = array_map(fn($starTile) => $starTile->type, $starTiles);
                    $colors = array_diff([1, 2, 3, 4, 5, 6], $starColors);
                    if ($variant && count($starColors) >= 2 && $starColors[0] == $starColors[1]) {
                        $colors = [$starColors[0]];
                    }
                }

                if ($this->array_some($colors, fn($color) => $this->getMaxWildTiles($hand, $space, $color, $wildColor) !== null)) {
                    $possibleSpaces[] = $star * 100 + $space;
                }
            }
        }

        return [
            'possibleSpaces' => $possibleSpaces,
        ];
    }

    function argChooseColor() {
        $playerId = self::getActivePlayerId();

        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $star = $selectedPlace[0];
        $space = $selectedPlace[1];
        $selectedColor = $this->STANDARD_FACE_STAR_COLORS[$star];

        $possibleColors = [];
        $variant = $this->isVariant();
        if ($variant || $selectedColor == 0) {
            $placedTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
            $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
            $wildColor = $this->getWildColor();
            $starTiles = array_values(array_filter($placedTiles, fn($placedTile) => $placedTile->star == $star));
            $starColors = array_map(fn($starTile) => $starTile->type, $starTiles);
            $colors = array_diff([1, 2, 3, 4, 5, 6], $starColors);
            if ($variant && count($starColors) >= 2 && $starColors[0] == $starColors[1]) {
                $colors = [$starColors[0]];
            }

            foreach ($colors as $possibleColor) {
                if ($this->getMaxWildTiles($hand, $space, $possibleColor, $wildColor) !== null) {
                    $possibleColors[] = $possibleColor;
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
        $space = $selectedPlace[1];
        $selectedColor = $this->getGlobalVariable(SELECTED_COLOR);
        $wildColor = $this->getWildColor();
        $number = $space;
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