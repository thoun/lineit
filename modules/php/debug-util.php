<?php

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        //$this->debugSetCardInLine(2343492, 2, 3);
        //$this->debugSetCardInHand(2343492, 2, 5);
        
        //$this->debugLastTurn();
    }

    private function debugCardByTypes($type, $subType, $index = 0) {
        return $this->getCardsByLocation('deck', null, $type, $subType)[$index];
    }

    private function debugSetCardInLine($playerId, $type, $subType, $index = 0) {
        $card = $this->debugCardByTypes($type, $subType, $index);
        $this->cards->moveCard($card->id, 'line'.$playerId, intval($this->cards->countCardInLocation('line'.$playerId)));
    }

    private function debugSetCardInHand($playerId, $type, $subType, $index = 0) {
        $card = $this->debugCardByTypes($type, $subType, $index);
        $this->cards->moveCard($card->id, 'hand', $playerId);
    }

    public function debugLastTurn() {
        $this->cards->moveAllCardsInLocation('deck', 'discard');    
    }

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
