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
   
    function argChooseMarketCard() {
        $playerId = intval($this->getActivePlayerId());

        $market = $this->getCardsByLocation('market');
        $canPlaceOnLine = $this->canCompleteLine($playerId, $market);
        $canAddToHand = intval($this->cards->countCardInLocation('hand', $playerId)) < 2;
    
        return [
           'canAddToLine' => count($canPlaceOnLine) > 0,
           'canAddToHand' => $canAddToHand,
        ];
    }
   
    function argPlayCard() {
        $playerId = intval($this->getActivePlayerId());
    
        return [
           'operations' => [], // TODO
        ];
    }
} 
