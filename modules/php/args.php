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
        $canPlaceOnLine = $this->canPlaceOnLine($playerId, $market);
        $canAddToHand = intval($this->cards->countCardInLocation('hand', $playerId)) < 2;
    
        return [
           'canPlaceOnLine' => $canPlaceOnLine,
           'canAddToLine' => count($canPlaceOnLine) > 0,
           'canAddToHand' => $canAddToHand,
        ];
    }
   
    function argPlayCard() {
        $playerId = intval($this->getActivePlayerId());

        $canPlaceOnLine = $this->canPlaceOnLine($playerId, []);
        $args = $this->argChooseMarketCard();
        $mustClose = !$args['canAddToLine'] && !$args['canAddToHand'];
    
        return [
           'canPlaceOnLine' => $canPlaceOnLine,
           'canClose' => intval($this->cards->countCardInLocation('line'.$playerId)) > 0,
           'mustClose' => $mustClose,
        ];
    }
} 
