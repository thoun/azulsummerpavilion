<?php

trait StateTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    
    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

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
            $this->actTakeTiles($possibleTile->id, true);
        }
    }

    function stChoosePlace() {
        $playerId = self::getActivePlayerId();
        
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        if (count(array_filter($hand, fn($tile) => $tile->type > 0)) == 0) {
            $this->applyPass($playerId);
        }
    }
    
}
