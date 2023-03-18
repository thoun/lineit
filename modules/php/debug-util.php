<?php

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        $this->debugSetCardInLine(2343492, 2, 3);
        $this->debugSetCardInHand(2343492, 2, 5);
        
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

    public function debugReplacePlayersIds() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

		// These are the id's from the BGAtable I need to debug.
		/*$ids = [
            84319026,
86175279
		];*/
        $ids = array_map(fn($dbPlayer) => intval($dbPlayer['player_id']), array_values($this->getCollectionFromDb('select player_id from player order by player_no')));

		// Id of the first player in BGA Studio
		$sid = 2343492;
		
		foreach ($ids as $id) {
			// basic tables
			$this->DbQuery("UPDATE player SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE global SET global_value=$sid WHERE global_value = $id" );
			$this->DbQuery("UPDATE card SET card_location_arg=$sid WHERE card_location_arg = $id" );

			// 'other' game specific tables. example:
			// tables specific to your schema that use player_ids
			$this->DbQuery("UPDATE card SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE card SET card_location='bag$sid' WHERE card_location='bag$id'" );
			$this->DbQuery("UPDATE card SET card_location='reserve$sid' WHERE card_location='reserve$id'" );
			$this->DbQuery("UPDATE card SET card_location='highCommand$sid' WHERE card_location='highCommand$id'" );
			$this->DbQuery("UPDATE discover_tile SET card_location_arg=$sid WHERE card_location_arg = $id" );
			$this->DbQuery("UPDATE objective_token SET card_location_arg=$sid WHERE card_location_arg = $id" );
			$this->DbQuery("UPDATE link SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE circle SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE operation SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE realized_objective SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE realized_objective SET realized_by=$sid WHERE realized_by = $id" );
            
			++$sid;
		}

        self::reloadPlayersBasicInfos();
	}

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
