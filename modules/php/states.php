<?php

trait StateTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    
    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stChoosePlace() {
        $playerId = self::getActivePlayerId();
        
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        if (count(array_filter($hand, fn($tile) => $tile->type > 0)) == 0) {
            $this->applyPass($playerId);
        }
    }
    
}
