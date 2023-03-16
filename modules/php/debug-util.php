<?php

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        $this->debugTestZones(2343492);
        $this->debugTestLinks(2343493);

        $this->debugAddPlayerEverywhere(2343492, 10);
        //$this->debugAddPlayerEverywhere(2343493, 8);

        //$this->debugAddObjectiveToken(2343492, 1);
        //$this->debugAddDiscoverTile(2343492, 4, 1);
        //$this->debugAddDiscoverTile(2343492, 5, 1);
        //$this->debugAddDiscoverTile(2343492, 3, 1);
        /*$this->debugAddDiscoverTile(2343492, POWER_PLANNING);
        $this->debugAddDiscoverTile(2343492, POWER_FOUL_PLAY);
        
        $this->debugAddObjectiveToken(2343493, 6);*/
        /*for ($i=0;$i<3;$i++) $this->debugAddPlayerFighter(2343492, 1, 'territory', 11);
        //for ($i=0;$i<2;$i++) $this->debugAddPlayerFighter(2343492, 1, 'territory', 15);
        $this->debugAddNeutralFighter(2343492, 11, 'territory', 11);
        $this->debugAddNeutralFighter(2343492, 13, 'territory', 11);
        $this->debugAddNeutralFighter(2343492, 12, 'territory', 41);*/
        //$this->debugAddNeutralFighter(2343492, 31, 'highCommand2343492', 1);
        //$this->debugAddNeutralFighter(2343492, 32, 'highCommand2343492', 2);
        //$this->debugAddNeutralFighter(2343492, 33, 'highCommand2343492', 5);
        /*$this->debugAddNeutralFighter(2343492, 21, 'highCommand2343492', 1);
        $this->debugAddNeutralFighter(2343492, 22, 'highCommand2343492', 2);
        $this->debugAddNeutralFighter(2343492, 23, 'highCommand2343492', 3);*/
        
        //$this->debugLastTurn();
    }

    public function debugAddPlayerFighter(int $playerId, int $subType, string $location, $locationArg = null, $played = false) {
        $cards = $this->getCardsByLocation('bag'.$playerId, null, null, null, $subType);
        $card = $cards[0];
        if ($played) {
            self::DbQuery("update card set played = true where card_id = $card->id");
        }
        $this->cards->moveCard($card->id, $location, $locationArg);
    }

    public function debugAddNeutralFighter(int $playerId, int $subType, string $location, $locationArg = null, $played = false) {
        $cards = $this->getCardsByLocation('bag0', null, null, null, $subType);
        $card = $cards[0];
        if ($played) {
            self::DbQuery("update card set played = true where card_id = $card->id");
        }
        self::DbQuery("update card set player_id = $playerId where card_id = $card->id");
        $this->cards->moveCard($card->id, $location, $locationArg);
    }

    public function debugAddObjectiveToken(int $playerId, int $number = 1) {
        $this->objectiveTokens->pickCardsForLocation($number, 'deck', 'player', $playerId);
    }

    public function debugAddDiscoverTile(int $playerId, int $powerOrLumens, int $type = 2) {
        $tiles = $this->getDiscoverTilesByLocation('deck', null, null, $type, $powerOrLumens);
        if (count($tiles) > 0) {
            $this->discoverTiles->moveCard($tiles[0]->id, 'player', $playerId);
        } else {
            $tiles = $this->getDiscoverTilesByLocation('territory', null, null, $type, $powerOrLumens);
            if (count($tiles) > 0) {
                $this->discoverTiles->moveCard($tiles[0]->id, 'player', $playerId);
            } else {
                $this->debug("Discover tile $type $powerOrLumens not found");
            }
        }
    }

    public function debugSetCircleValues($playerId, $circlesIds, $value, $zoneId = null) {
        foreach($circlesIds as $circleId) {
            self::DbQuery($zoneId !== null ?
                "INSERT INTO `circle` (`circle_id`, `player_id`, `value`, `zone`) VALUES ($circleId, $playerId, $value, $zoneId)" :
                "INSERT INTO `circle` (`circle_id`, `player_id`, `value`) VALUES ($circleId, $playerId, $value)"
            );
        }
    }

    public function debugAddPlayerEverywhere($playerId, $limit = 99) {
        $scenario = $this->getScenario();
        $number = 0;
        foreach ($scenario->battlefieldsIds as $battlefieldId) {
            foreach ($this->BATTLEFIELDS[$battlefieldId]->territories as $territory) {
                if (count($this->getCardsByLocation('territory', $territory->id, $playerId)) == 0) {
                    $cards = $this->getCardsByLocation('bag'.$playerId);
                    if (count($cards) > 0) {
                        $card = $cards[0];
                        $this->cards->moveCard($card->id, 'territory', $territory->id);

                        $number++;
                        if ($number >= $limit) {
                            return;
                        }
                    } else {
                        $cards = $this->getCardsByLocation('bag0');
                        if (count($cards) > 0) {
                            $card = $cards[0];
                            self::DbQuery("update card set player_id = $playerId where card_id = $card->id");
                            $this->cards->moveCard($card->id, 'territory', $territory->id);

                            $number++;
                            if ($number >= $limit) {
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    public function debugClean($keepId = null) {
        $sql = "update card set card_location = 'void' where card_location = 'territory'";
        if ($keepId) {
            $sql .= " and card_id <> $keepId";
        }
        self::DbQuery($sql);
    }

    public function debugLastTurn() {
        $this->incStat(20, 'roundNumber');
    }

    public function debugTestZones($playerId) {
        $this->debugSetCircleValues($playerId, [3, 8], 4, 1);
        $this->debugSetCircleValues($playerId, [13], 4);
        
        $this->debugSetCircleValues($playerId, [2, 6], 2, 2);
        $this->debugSetCircleValues($playerId, [4, 10], 2, 3);

        $this->debugSetCircleValues($playerId, [15, 19], 1);
        $this->debugSetCircleValues($playerId, [18, 20], 5, 4);

        $this->debugSetCircleValues($playerId, [11], 3);
    }

    public function debugTestLinks($playerId) {
        $this->debugSetCircleValues($playerId, [8], 3);
        $this->debugSetCircleValues($playerId, [14], 4);
        $this->addLink($playerId, 8, 14);
        
        $this->debugSetCircleValues($playerId, [1], 1, 4);
        $this->debugSetCircleValues($playerId, [10], 3, 3);

        $this->debugSetCircleValues($playerId, [15], 0);
        $this->debugSetCircleValues($playerId, [16], 1);
        $this->addLink($playerId, 15, 16);
        $this->debugSetCircleValues($playerId, [20, 18], 3);
        $this->debugSetCircleValues($playerId, [11, 12], 4);
        $this->addLink($playerId, 11, 18);
    }

    public function debugInitScenario() {
        $players = $this->loadPlayersBasicInfos();
        foreach($players as &$player) {
            $player['player_table_order'] = $player['player_no'];
        }
        $this->initScenario($players);
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
