<?php

trait UtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function array_find(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return $value;
            }
        }
        return null;
    }

    function array_find_key(array $array, callable $fn) {
        foreach ($array as $key => $value) {
            if($fn($value)) {
                return $key;
            }
        }
        return null;
    }

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return true;
            }
        }
        return false;
    }
    
    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if(!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    function array_identical(array $a1, array $a2) {
        if (count($a1) != count($a2)) {
            return false;
        }
        for ($i=0;$i<count($a1);$i++) {
            if ($a1[$i] != $a2[$i]) {
                return false;
            }
        }
        return true;
    }

    function setGlobalVariable(string $name, /*object|array*/ $obj) {
        /*if ($obj == null) {
            throw new \Error('Global Variable null');
        }*/
        $jsonObj = json_encode($obj);
        $this->DbQuery("INSERT INTO `global_variables`(`name`, `value`)  VALUES ('$name', '$jsonObj') ON DUPLICATE KEY UPDATE `value` = '$jsonObj'");
    }

    function getGlobalVariable(string $name, $asArray = null) {
        $json_obj = $this->getUniqueValueFromDB("SELECT `value` FROM `global_variables` where `name` = '$name'");
        if ($json_obj) {
            $object = json_decode($json_obj, $asArray);
            return $object;
        } else {
            return null;
        }
    }

    function deleteGlobalVariable(string $name) {
        $this->DbQuery("DELETE FROM `global_variables` where `name` = '$name'");
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function getRoundCardCount() {
        return count($this->getPlayersIds()) + 2;
    }

    function getPlayerName(int $playerId) {
        return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = $playerId");
    }

    function getPlayerScore(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function incPlayerScore(int $playerId, int $amount, $message = '', $args = []) {
        $this->DbQuery("UPDATE player SET `player_score` = `player_score` + $amount WHERE player_id = $playerId");
            
        $logType = array_key_exists('scoreType', $args) && in_array($args['scoreType'], ['endControlTerritory']) ? $args['scoreType'] : 'score';
        $this->notifyAllPlayers($logType, $message, [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'newScore' => $this->getPlayerScore($playerId),
            'incScore' => $amount,
        ] + $args);
    }

    function getFirstPlayer() {
        return intval($this->getGameStateValue(FIRST_PLAYER));
    }

    /*function setFirstPlayer(int $playerId, bool $withInitiativeMarker) {
        $this->setGameStateValue(FIRST_PLAYER, $playerId);
        $this->gamestate->changeActivePlayer($playerId);

        $message = $withInitiativeMarker ?
            clienttranslate('${player_name} is the first player for this round because he controls initiative marker') :
            clienttranslate('${player_name} is the new first player because no-one controls initiative marker so first player changes');
        self::notifyAllPlayers('newFirstPlayer', $message, [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
        ]);
    }*/

    function getCardById(int $id) {
        $sql = "SELECT * FROM `card` WHERE `card_id` = $id";
        $dbResults = $this->getCollectionFromDb($sql);
        $cards = array_map(fn($dbCard) => new Card($dbCard), array_values($dbResults));
        return count($cards) > 0 ? $cards[0] : null;
    }

    function getCardsByLocation(string $location, /*int|null*/ $location_arg = null, /*int|null*/ $type = null, /*int|null*/ $number = null) {
        $sql = "SELECT * FROM `card` WHERE `card_location` = '$location'";
        if ($location_arg !== null) {
            $sql .= " AND `card_location_arg` = $location_arg";
        }
        if ($type !== null) {
            $sql .= " AND `card_type` = $type";
        }
        if ($number !== null) {
            $sql .= " AND `card_type_arg` = $number";
        }
        $sql .= " ORDER BY `card_location_arg`";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbCard) => new Card($dbCard), array_values($dbResults));
    }

    function setupCards() {
        // number cards
        $cards = [];
        for ($i = 1; $i <= 100; $i++) {
            $cards[] = [ 'type' => 1, 'type_arg' => $i, 'nbr' => 1 ];
        }
        // bet cards
        for ($i = 3; $i <= 5; $i++) {
            $cards[] = [ 'type' => 2, 'type_arg' => $i, 'nbr' => 2 ];
        }
        $this->cards->createCards($cards, 'deck');
        $this->cards->shuffle('deck');
    }

    function initPlayersCards(array $players) {
        foreach($players as $playerId => $player) {
            for ($i=1; $i<=3; $i++) {
                $this->cards->pickCardForLocation('bag'.$playerId, 'reserve'.$playerId, $i);
            }
        }
    }

    // return list of cards that can be placed on the player's line
    function canCompleteLine(int $playerId, array $market = []) {
        $hand = $this->getCardsByLocation('hand', $playerId);

        $cards = array_merge($hand, $market);
        $line = $this->getCardsByLocation('line', $playerId);
        $lineWithoutBet = array_values(array_filter($line, fn($card) => $card->type == 1));

        if (count($lineWithoutBet) < 2) {
            return $cards;
        }

        $direction = $lineWithoutBet[1]->number - $lineWithoutBet[0]->number;

        $lineLastNumber = $lineWithoutBet[count($lineWithoutBet) - 1]->number;

        if ($direction > 0) {
            return array_values(array_filter($cards, fn($card) => $card->number > $lineLastNumber));
        } else {
            return array_values(array_filter($cards, fn($card) => $card->number < $lineLastNumber));
        }
    }

    function isRealizedObjective(string $letter, /*int|null*/ $playerId = null) {
        $sql = "SELECT count(*) FROM `realized_objective` WHERE `letter` = '$letter'";
        if ($playerId !== null) {
            $sql .= " AND `player_id` = $playerId";
        }
        return boolval(self::getUniqueValueFromDB($sql));
    }

    function getRealizedObjectives() {
        $dbResults = $this->getCollectionFromDb("SELECT `letter`, `realized_by` as `realizedBy` FROM `realized_objective`");
        return array_values($dbResults);
    }    

    function setRealizedObjective(string $letter, int $realizedBy, int $playerId = 0) {
        self::DbQuery("INSERT INTO `realized_objective`(`letter`, `player_id`, `realized_by`) VALUES ('$letter', $playerId, $realizedBy )");

        self::notifyAllPlayers('setRealizedObjective', '', [
            'letter' => $letter,
            'realizedBy' => $realizedBy,
        ]);
    }

    function getTerritoryNeighboursIds(int $territoryId) {
        $scenario = $this->SCENARIOS[$this->getScenarioId()];

        $neighboursId = [];

        foreach ($scenario->battlefieldsIds as $battlefieldId) {
            $battlefield = $this->BATTLEFIELDS[$battlefieldId];
            foreach ($battlefield->territoriesLinks as $from => $tos) {
                if ($from == $territoryId) {
                    if ($neighboursId == null || $tos == null) {
                    }
                    $neighboursId = array_merge($neighboursId, $tos);
                }
                if (in_array($territoryId, $tos)) {
                    $neighboursId[] = $from;
                }
            }
        }

        foreach ($scenario->territoriesLinks as $from => $tos) {
            if ($from == $territoryId) {
                $neighboursId = array_merge($neighboursId, $tos);
            }
            if (in_array($territoryId, $tos)) {
                $neighboursId[] = $from;
            }
        }

        return array_values(array_unique($neighboursId));
    }

    function addCheck(int $playerId) {
        $checks = $this->getPlayerChecks($playerId);

        if ($checks > 7) {
            return;
        }
        self::DbQuery("update player set checks = checks + 1 where player_id = $playerId");
        $checks++;
        self::notifyAllPlayers('addCheck', clienttranslate('${player_name} crosses a box in the high command section'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'checks' => $checks,
        ]);

        $slot = $this->SLOTS_BY_CHECKS[$checks];

        if ($slot > 0) {
            $this->cards->pickCardForLocation('bag0', 'highCommand'.$playerId, $slot);
            $card = $this->getCardsByLocation('highCommand'.$playerId, $slot)[0];
            self::DbQuery("update card set player_id = $playerId WHERE card_id=$card->id");
            $card->playerId = $playerId;

            self::notifyAllPlayers('addHighCommandCard', clienttranslate('${player_name} get a new high command card'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'card' => $card,
            ]);
        }

        $this->incStat(1, 'checkedMercenaries', $playerId);        

        if ($checks >= 6) {
            $this->takeCheckObjectiveToken($playerId, $checks);
        }
    }

    function getTerritoryControlledPlayer(int $territoryId, int $requiredDiff = 1) {
        $territoryControlledPlayer = null;
        $playersIds = $this->getPlayersIds();
        $fightersOnTerritory = $this->getCardsByLocation('territory', $territoryId);
        $strengthByPlayer = [];
        foreach ($playersIds as $playerId) {
            $playerFighters = array_values(array_filter($fightersOnTerritory, fn($fighter) => $fighter->playerId == $playerId));
            $playerFightersStrengthes = array_map(fn($fighter) => $fighter->getStrength(), $playerFighters);
            $strengthByPlayer[$playerId] = array_reduce($playerFightersStrengthes, fn($a, $b) => $a + $b, 0);
        }

        if ($strengthByPlayer[$playersIds[0]] >= $strengthByPlayer[$playersIds[1]] + $requiredDiff) {
            $territoryControlledPlayer = $playersIds[0];
        } else if ($strengthByPlayer[$playersIds[1]] >= $strengthByPlayer[$playersIds[0]] + $requiredDiff) {
            $territoryControlledPlayer = $playersIds[1];
        }

        return $territoryControlledPlayer;
    }

    function getCircles(int $playerId) {
        $dbCircles = $this->getCollectionFromDb( "SELECT * FROM `circle` WHERE player_id = $playerId ORDER BY `circle_id`");
        $circles = [];
        foreach ($this->CIRCLE_NEIGHBOURS as $circleId => $neighbours) {
            $dbCircle = $this->array_find($dbCircles, fn($dbCircle) => intval($dbCircle['circle_id']) == $circleId);
            if ($dbCircle !== null) {
                $circle = new Circle($circleId, intval($dbCircle['value']), intval($dbCircle['zone']));
            } else {
                $circle = new Circle($circleId);
            }
            $circle->neighbours = $neighbours;
            $circles[] = $circle;
        }
        return $circles;
    }

    function getLinks(int $playerId) {
        $dbLinks = $this->getCollectionFromDb("SELECT * FROM `link` WHERE player_id = $playerId ORDER BY `index1`, `index2`");
        return array_values(array_map(fn($dbLink) => new Link(intval($dbLink['index1']), intval($dbLink['index2'])), $dbLinks));
    }

    function refreshZones(int $playerId, int $circleId) {
        $circle = self::getObjectFromDB("SELECT * FROM circle where player_id = ".$playerId." and circle_id = ".$circleId);
      
        $neighbors = $this->CIRCLE_NEIGHBOURS[$circleId];
        $neighttxt = implode(',', $neighbors);
        
        $list = [];
        $zid = -1;
        $newZoneCellCount = 0;
        
        $zones = self::getObjectListFromDB('SELECT distinct(zone) FROM `circle` WHERE value = '.$circle['value'].' and player_id = '.$playerId.' and circle_id in ('.$neighttxt.')', true);
        if(count($zones) == 1 && intval($zones[0]) == -1) {
            //new zones
            $zid = self::getUniqueValueFromDB( "SELECT max(zone) from circle where player_id = ".$playerId) + 1;
            $newZoneCellCount = intval(self::getUniqueValueFromDB( "SELECT count(*) from circle where zone = -1 AND value = ".$circle['value']." and player_id = $playerId and circle_id in (".$neighttxt.", ".$circleId.")"));
            self::DbQuery("update circle set zone = ".$zid.' where value = '.$circle['value'].' and player_id = '.$playerId.' and circle_id in ('.$neighttxt.', '.$circleId.')');
        } else if(count($zones) == 1 && $zones[0] > -1) {
            self::DbQuery("update circle set zone = ".$zones[0].' where player_id = '.$playerId.' and circle_id = '.$circleId);
            $zid = $zones[0];
            $newZoneCellCount = 1;
        } else if(count($zones) > 1) {
            $zid = -1;              
            for($i=0;$i<count($zones);$i++) {
                if ($zones[$i] != -1) {
                    $zid = $zones[$i];
                }
            }
            
            if($zid == -1) {
                //new zones resulting from 3 merges
                $zid = self::getUniqueValueFromDB( "SELECT max(zone) from circle where player_id = ".$playerId) + 1;
                $newZoneCellCount = 3;
            } else {
                $newZoneCellCount = intval(self::getUniqueValueFromDB( "SELECT count(*) from circle where zone = -1 AND value = ".$circle['value']." and player_id = $playerId and circle_id in (".$neighttxt.", ".$circleId.")"));
            }
            
            //merge adjacent value
            self::DbQuery("update circle set zone = ".$zid.' where value = '.$circle['value'].' and player_id = '.$playerId.' and circle_id in ('.$neighttxt.', '.$circleId.')');
            
            //then merge if necessary              
            for($i=0;$i<count($zones);$i++) {
                if($zones[$i] != -1) {
                    self::DbQuery("update circle set zone = ".$zid.' where player_id = '.$playerId.' and zone = '.$zones[$i]);
                }
            }
        }
                
        if ($zid >= 0) {
            $list = self::getObjectListFromDB('SELECT circle_id FROM `circle` WHERE player_id = '.$playerId.' and zone = '.$zid, true);
            
            self::notifyAllPlayers("zone", '', [
                'playerId' => $playerId,
                'circlesIds' => array_map(fn($elem) => intval($elem), $list),
                'zoneId' => $zid,
            ]);  
        }

        return $newZoneCellCount;
    }

    function getPossibleLinkCirclesIds(int $playerId, array $links, int $circleId, int $value, int $direction) {
        $circles = $this->getCircles($playerId);
        $circle = $this->array_find($circles, fn($c) => $c->circleId == $circleId);
        $possible = [];

        foreach ($circle->neighbours as $neighbourId) {
            $neighbour = $this->array_find($circles, fn($c) => $c->circleId == $neighbourId);
            if ($neighbour->value >= 0 && $neighbour->value === $value - $direction) {
                $linkedCirclesIds = [];
                foreach ($links as $link) {
                    if ($neighbour->circleId == $link->index1) {
                        $linkedCirclesIds[] = $link->index2;
                    } else if ($neighbour->circleId == $link->index2) {
                        $linkedCirclesIds[] = $link->index1;
                    }
                }
                $neighbourHasUpperLink = $this->array_some($circles, fn($c) => in_array($c->circleId, $linkedCirclesIds) && $c->value > $neighbour->value);
                $neighbourHasLowerLink = $this->array_some($circles, fn($c) => in_array($c->circleId, $linkedCirclesIds) && $c->value < $neighbour->value);

                if (
                    ($direction === 1 && !$neighbourHasUpperLink) ||
                    ($direction === -1 && !$neighbourHasLowerLink)
                ) {
                    $possible[] = $neighbour->circleId;
                }
            }
        }

        return $possible;
    }

    function addLink(int $playerId, int $circleId, int $toCircleId) {
        $index1 = min($circleId, $toCircleId);
        $index2 = max($circleId, $toCircleId);
        $links = $this->getLinks($playerId);

        self::DbQuery("INSERT INTO link (player_id, index1, index2) VALUES ($playerId, $index1, $index2)");
        
        self::notifyAllPlayers( "link", '', [
            'playerId' => $playerId,
            'index1' => $index1,
            'index2' => $index2,
        ]);

        $isConnectedToAnotherLink = $this->array_some($links, fn($link) => $link->index1 == $index1 || $link->index2 == $index1 || $link->index1 == $index2 || $link->index2 == $index2);
        if (!$isConnectedToAnotherLink) {
            $this->incStat(1, 'numberOfLines', $playerId);
        }
    }

    function takeObjectiveTokens(int $playerId, int $number, string $message, $messageArgs = []) {
        $tokens = $this->getObjectiveTokensFromDb($this->objectiveTokens->pickCardsForLocation($number, 'deck', 'player', $playerId));

        $logType = array_key_exists('logType', $messageArgs) ? $messageArgs['logType'] : 'takeObjectiveTokens';
        
        $args = [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
        ] + $messageArgs;
        self::notifyAllPlayers($logType, $message, $args + [
            'tokens' => ObjectiveToken::onlyIds($tokens),
        ]);
        self::notifyPlayer($playerId, $logType, $message, $args + [
            'tokens' => $tokens,
        ]);
        
        $this->updateCurrentHiddenScore($playerId);
    }

    function takeScenarioObjectiveToken(int $playerId, /*string | null*/ $letter = null, $number = 1) {
        $this->takeObjectiveTokens(
            $playerId, 
            $number,
            $letter != null ? clienttranslate('${player_name} gets ${number} objective token(s) for objective ${letter}') : clienttranslate('${player_name} gets ${number} objective token(s) for a completed objective'), 
            [
                'number' => $number,
                'letterId' => $letter,
                'letter' => $letter,
            ]
        );
    }

    function takeCheckObjectiveToken(int $playerId, int $check) {
        $this->takeObjectiveTokens(
            $playerId, 
            1,
            clienttranslate('${player_name} gets an objective token for checking the high-command check number ${check}'), 
            [
                'check' => $check,
            ]
        );
    }

    function takeMissionObjectiveToken(int $playerId, int $number, Card $missionCard) {
        $mission = '';
        switch ($missionCard->power) {
            case MISSION_LOOT: $mission = clienttranslate('Loot'); break;
            case MISSION_WINTER: $mission = clienttranslate('Winter'); break;
            case MISSION_SHROOMLING: $mission = clienttranslate('Shroomling'); break;
        }

        if ($number > 0) {
            $this->takeObjectiveTokens(
                $playerId, 
                $number,
                clienttranslate('${player_name} gets ${number} objective token(s) for mission ${mission}'), 
                [
                    'logType' => 'takeMissionObjectiveTokens',
                    'number' => $number,
                    'mission' => $mission,
                    'highlightCard' => $missionCard,
                    'i18n' => ['mission'],
                ]
            );
            
            $this->incStat($number, 'tokensFromMissions');
            $this->incStat($number, 'tokensFromMissions', $playerId);
        } else {
            self::notifyAllPlayers('log', clienttranslate('${player_name} doesn\'t score mission ${mission}'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'mission' => $mission,
                'highlightCard' => $missionCard,
                'i18n' => ['mission'],
            ]);
        }
    }

    function applyMoveFighter(Card &$fighter, int $territoryId, string $logMessage = '', array $logArgs = [], bool $fromBag = false) { // return redirected for Interference
        if ($territoryId == 0) {
            // pushed to the river
            $this->putBackInBag([$fighter]);
            $this->checkTerritoriesDiscoverTileControl($fighter->playerId);
            return false;
        }

        $this->cards->moveCard($fighter->id, 'territory', $territoryId);
        $fighter = $this->getCardById($fighter->id);

        self::notifyAllPlayers("moveFighter", $logMessage, $logArgs + [
            'fighter' => $fighter,
            'territoryId' => $territoryId,
            'fromBag' => $fromBag,
            'playerId' => $fighter->playerId,
            'player_name' => $this->getPlayerName($fighter->playerId), // for logs
            'fighterType' => $fighter->subType, // for logs
            'season' => $this->getSeasonName($this->TERRITORIES[$territoryId]->lumens), // for logs
            'battlefieldId' => floor($territoryId / 10), // for logs
            'i18n' => array_merge(array_key_exists('i18n', $logArgs) ? $logArgs['i18n'] : [], ['season']),
            'preserve' => ['fighter', 'fighterType'],
        ]);

        return $this->fighterMoved($fighter, $territoryId);
    }

    function checkDiscoverTileControl(DiscoverTile &$discoverTile) {
        $controlledBy = $this->getTerritoryControlledPlayer($discoverTile->locationArg, $discoverTile->subType);
        if ($controlledBy !== null) {
            $this->moveDiscoverTileToPlayer($discoverTile, $controlledBy);
        }
    }

    function fighterMoved(Card &$fighter, int $territoryId) { // return redirected for Interference
        $redirectInterference = false;
        $discoverTiles = $this->getDiscoverTilesByLocation('territory', $territoryId, false);
        //we reveal hidden discover tiles
        foreach($discoverTiles as &$discoverTile) {
            if ($this->revealDiscoverTile($discoverTile, $fighter->playerId, $territoryId)) {
                $redirectInterference = true;
            }
        }

        // every time a fighter moves, we check if it makes a control to a visible Discover tile
        $this->checkTerritoriesDiscoverTileControl($fighter->playerId);

        $scenarioId = $this->getScenarioId();
        switch ($scenarioId) {
            case 5:
                if (!$this->isRealizedObjective('C') && $territoryId == 61) {
                    $this->takeScenarioObjectiveToken($fighter->playerId, 'C');
                    $this->setRealizedObjective('C', $fighter->playerId);
                    $this->incStat(1, 'completedObjectives');
                    $this->incStat(1, 'completedObjectives', $fighter->playerId);
                }
                break;
            case 7: 
                if (!$this->isRealizedObjective('2') && in_array($territoryId, [65, 75])) {
                    $players = $this->loadPlayersBasicInfos();
                    $playerNo = intval($this->array_find($players, fn($player) => intval($player['player_id']) == $fighter->playerId)['player_no']);
                    if (($playerNo == 1 && $territoryId == 75) || ($playerNo == 2 && $territoryId == 65)) {
                        $this->takeScenarioObjectiveToken($fighter->playerId, null, 3);
                        $this->setRealizedObjective('2', $fighter->playerId);
                        $this->incStat(1, 'completedObjectives');
                        $this->incStat(1, 'completedObjectives', $fighter->playerId);
                    }
                }
                break;
        }

        return $redirectInterference;
    }

    function checkTerritoriesDiscoverTileControl(int $currentPlayer) {
        $discoverTiles = $this->getDiscoverTilesByLocation('territory', null, true);
        foreach($discoverTiles as &$discoverTile) {
            if ($discoverTile->type === 1) { // coffre
                $this->checkDiscoverTileControl($discoverTile);
            }
        }
        
        $scenario = $this->getScenario();
        $frontierObjectives = $scenario->frontierObjectives;

        foreach ($frontierObjectives as $letter => $territoriesIds) {
            if (!$this->isRealizedObjective($letter)) {
                $controlledBy = [];
                foreach ($territoriesIds as $territoryId) {
                    $controlledBy[] = $this->getTerritoryControlledPlayer($territoryId);
                }

                if (count(array_unique($controlledBy, SORT_REGULAR)) === 1 && $controlledBy[0] !== null) {
                    $this->takeScenarioObjectiveToken($controlledBy[0], $letter);
                    $this->setRealizedObjective($letter, $controlledBy[0]);
                    $this->incStat(1, 'completedObjectives');
                    $this->incStat(1, 'completedObjectives', $controlledBy[0]);
                }
            }
        }

        $playersIds = $this->getPlayersIds();
        $this->updateControlCounters($scenario, $playersIds);
        foreach($playersIds as $playerId) {
            $this->updateCurrentVisibleScore($playerId); // will include territory control & loot
        }
        if ($currentPlayer !== null) {
            $this->updateCurrentHiddenScore($currentPlayer);
        }
    }

    function updateControlCounters(Scenario $scenario, array $playersIds) {
        $counters = [];
        foreach ($playersIds as $playerId) {
            $counters[$playerId] = [1 => 0, 3 => 0, 5 => 0, 7 => 0];
            foreach ($scenario->battlefieldsIds as $battlefieldId) {
                foreach ($this->BATTLEFIELDS[$battlefieldId]->territories as $territory) {
                    $controlledBy = $this->getTerritoryControlledPlayer($territory->id);
                    if ($controlledBy === $playerId) {
                        $counters[$playerId][$territory->lumens]++;
                    }
                }
            }
        }

        self::notifyAllPlayers("updateControlCounters", '', [
            'counters' => $counters,
        ]);

        return $counters;
    }

    function moveDiscoverTileToPlayer(DiscoverTile &$discoverTile, int $playerId) {
        $this->discoverTiles->moveCard($discoverTile->id, 'player', $playerId);

        self::notifyAllPlayers("moveDiscoverTileToPlayer", '', [
            'discoverTile' => $discoverTile,
            'playerId' => $playerId,
        ]);
    }

    function discardDiscoverTile(DiscoverTile &$discoverTile) {
        $this->discoverTiles->moveCard($discoverTile->id, 'discard');

        self::notifyAllPlayers("discardDiscoverTile", '', [
            'discoverTile' => $discoverTile,
        ]);
    }

    function applyParatrooper(DiscoverTile &$discoverTile, int $playerId, int $territoryId) {
        $cardDb = $this->cards->pickCardForLocation('bag'.$playerId, 'territory', $territoryId);
        if ($cardDb == null) {
            self::notifyAllPlayers("log", clienttranslate('The bag is empty, impossible to apply Paratrooper'), []);
            return;
        }

        $this->discardDiscoverTile($discoverTile);

        $fighter = $this->getCardById(intval($cardDb['id']));
        $this->applyMoveFighter($fighter, $territoryId, clienttranslate('${player_name} drops paratrooper ${fighterType} on ${season} territory ${battlefieldId}'), [], true);

        $this->checkEmptyBag($playerId);
    }

    function highlightDiscoverTile(DiscoverTile &$discoverTile) {
        self::notifyAllPlayers("highlightDiscoverTile", '', [
            'discoverTile' => $discoverTile,
        ]);
    }

    function revealDiscoverTile(DiscoverTile &$discoverTile, int $playerId, int $territoryId) { // return redirected for Interference
        self::DbQuery("update discover_tile set visible = true where card_id = $discoverTile->id");
        $discoverTile->visible = true;
        self::notifyAllPlayers("revealDiscoverTile", clienttranslate('${player_name} reveals discover tile ${discover_tile}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'discoverTile' => $discoverTile,
            'discover_tile' => '',
            'preserve' => ['discoverTile', 'discover_tile'],
        ]);

        switch ($discoverTile->type) {
            case 1: // loot
                // nothing, will be checked for all loots in fighterMoved
                break;
            case 2: // power
                switch ($discoverTile->power) {
                    case POWER_INTERFERENCE:
                        $this->highlightDiscoverTile($discoverTile);
                        $this->discardDiscoverTile($discoverTile);
                        return true;
                    case POWER_PLANNING:
                    case POWER_FOUL_PLAY:
                        $this->moveDiscoverTileToPlayer($discoverTile, $playerId);
                        break;
                    case POWER_PARATROOPING:
                        $this->highlightDiscoverTile($discoverTile);
                        $this->applyParatrooper($discoverTile, $playerId, $territoryId);
                        break;
                    case POWER_PRIORITY_MESSAGE:
                        $this->highlightDiscoverTile($discoverTile);
                        $this->addCheck($playerId);
                        $this->discardDiscoverTile($discoverTile);
                        break;
                }
                break;
        }

        return false;
    }

    function refillReserve(int $playerId) {
        $reserve = $this->getCardsByLocation('reserve'.$playerId);
        for ($i=1; $i<=3; $i++) {
            if (!$this->array_some($reserve, fn($fighter) => $fighter->locationArg == $i)) {
                $this->cards->pickCardForLocation('bag'.$playerId, 'reserve'.$playerId, $i);

                $fighters = $this->getCardsByLocation('reserve'.$playerId, $i);
                if (count($fighters) > 0) {
                    self::notifyAllPlayers("refillReserve", '', [
                        'playerId' => $playerId,
                        'fighter' => $fighters[0],
                        'slot' => $i,
                    ]);
                }
            }
        }

        $this->checkEmptyBag($playerId);
    }

    function setFightersActivated(array $fighters) {
        if (count($fighters) == 0) {
            return; // TODO log or block ?
        }
        
        $fightersIds = array_map(fn($fighter) => $fighter->id, $fighters);
        self::DbQuery("update card set played = true where card_id IN (".implode(',', $fightersIds).")");
        
        foreach($fighters as &$fighter) {
            $fighter->played = true;
        }

        self::notifyAllPlayers("setFightersActivated", '', [
            'fighters' => $fighters,
        ]);
    }

    function setFightersUnactivated(array $fighters) {
        if (count($fighters) == 0) {
            return; // TODO log or block ?
        }

        $fightersIds = array_map(fn($fighter) => $fighter->id, $fighters);
        self::DbQuery("update card set played = false where card_id IN (".implode(',', $fightersIds).")");

        foreach($fighters as &$fighter) {
            $fighter->played = false;
        }

        self::notifyAllPlayers("setFightersUnactivated", '', [
            'fighters' => $fighters,
        ]);
    }

    function putBackInBag(array $fighters) {
        $bags = [];
        $movedFighters = [];
        foreach($fighters as &$fighter) {
            $bag = $fighter->type != 1 ? 0 : $fighter->playerId;
            $this->cards->moveCard($fighter->id, 'bag'.$bag);
            if ($bag == 0) {
                self::DbQuery("update card set player_id = 0 where card_id = $fighter->id");
            }
            if (!in_array($bag, $bags)) {
                $bags[] = $bag;
            }
            $movedFighters[] = $this->getCardById($fighter->id);
        }    
        
        foreach($bags as $bag) {
            $this->cards->shuffle('bag'.$bag);
        }

        self::notifyAllPlayers("putBackInBag", '', [
            'fighters' => $movedFighters,
        ]);
    }
    
    function applyAction(Card &$action) {
        $this->setGameStateValue(PLAYER_SELECTED_FIGHTER, $action->id);

        $nextState = 'chooseFighter';
        switch ($action->power) {
            case ACTION_FURY:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_FURY);
                break;
            case ACTION_CLEAN_SHEET:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_RESET);
                break;
            case ACTION_TELEPORTATION:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_TELEPORT);
                break;
        }
        $this->gamestate->nextState($nextState);
    }

    function applyActivateFighter(Card &$fighter) {
        $this->setGameStateValue(PLAYER_SELECTED_FIGHTER, $fighter->id);
        $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_ACTIVATE);

        $playerId = intval($this->getActivePlayerId());

        $this->setFightersActivated([$fighter]);

        $nextState = 'nextMove';
        switch ($fighter->power) {
            case POWER_RESTORER:
                $territories = [$fighter->locationArg, ...$this->getTerritoryNeighboursIds($fighter->locationArg)];
                $playerFighters = $this->getCardsByLocation('territory', null, $fighter->playerId);
                $unactivatedFighters = array_values(array_filter($playerFighters, fn($playerFighter) => $playerFighter->played && $playerFighter->id != $fighter->id && in_array($playerFighter->locationArg, $territories)));
                $this->setFightersUnactivated($unactivatedFighters);
                if ($this->array_some($unactivatedFighters, fn($unactivatedFighter) => in_array($unactivatedFighter->power, [POWER_WEAVER, POWER_ROOTSPRING, POWER_METAMORPH]))) {
                    // every time a fighter with changing strength with is flipped, we check if it makes a control to a visible Discover tile
                    $this->checkTerritoriesDiscoverTileControl($playerId);
                }
                $this->incStat(1, 'activatedFighters', $playerId);

                self::notifyAllPlayers('log', clienttranslate('${player_name} activates ${fighterType} to reset surrounding fighters to their active face'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'fighter' => $fighter, // for logs
                    'fighterType' => $fighter->subType, // for logs
                    'preserve' => ['fighter', 'fighterType'],
                ]);
                break;
            case POWER_PUSHER:
                if ($fighter->type === 10) { // super pusher                    
                    $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_SUPER);
                    $nextState = 'chooseTerritory';
                } else {
                    $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_PUSH);
                    $nextState = 'chooseFighter';
                }
                break;
            case POWER_ASSASSIN:
                if ($fighter->type === 10) { // super assassin                 
                    $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_SUPER);
                    $nextState = 'chooseTerritory';
                } else {
                    $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_KILL);
                    $nextState = 'chooseFighter';
                }
                break;
            case POWER_FEATHERED:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_FLY);
                $nextState = 'chooseTerritory';
                break;
            case POWER_IMPATIENT:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_IMPATIENT);
                $nextState = 'chooseTerritory';
                break;
            case POWER_BOMBER:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_KILL);
                $nextState = 'chooseFighter';
                break;
            case POWER_HYPNOTIST:
                $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_UNACTIVATE);
                $nextState = 'chooseFighter';
                break;
            case POWER_WEAVER:
            case POWER_ROOTSPRING:
            case POWER_METAMORPH:
                // every time a fighter with changing strength is flipped, we check if it makes a control to a visible Discover tile
                $this->checkTerritoriesDiscoverTileControl($playerId);
                $this->incStat(1, 'activatedFighters', $playerId);     

                self::notifyAllPlayers('log', clienttranslate('${player_name} activates ${fighterType}'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'fighter' => $fighter, // for logs
                    'fighterType' => $fighter->subType, // for logs
                    'preserve' => ['fighter', 'fighterType'],
                ]);
                break;
        }
        if (in_array($nextState, ['nextMove', 'chooseCellInterference'])) {
            $this->decMoveCount(1);
        }

        $this->gamestate->nextState($nextState);
    }

    function checkEmptyBag(int $playerId) {
        if ($this->getScenarioId() == 2 && intval($this->cards->countCardInLocation('bag'.$playerId)) == 0 && !$this->isRealizedObjective('1', $playerId)) {
            $this->takeScenarioObjectiveToken($playerId, null, 2);
            $this->setRealizedObjective('1', $playerId, $playerId);
            $this->incStat(1, 'completedObjectives');
            $this->incStat(1, 'completedObjectives', $playerId);
        }
    }

    function getBattlefieldsIds(int $territory) {
        if ($this->getScenarioId() == 4) {
            $battlefield = floor($territory / 10);
            return [$battlefield, ...$this->BATTLEFIELDS_IN_SAME_ISLAND[$battlefield]];
        }

        $scenario = $this->getScenario();
        return $scenario->battlefieldsIds;
    }

    function setActionsCount(int $place, int $move) {
        $this->setGlobalVariable('REMAINING_ACTIONS', new Actions([
            new Action('PLACE', $place),
            new Action('MOVE', $move),
        ]));

        $playerId = $this->getActivePlayerId();
        $this->incStat($place, 'playObtained');
        $this->incStat($place, 'playObtained', $playerId);
        $this->incStat($move, 'moveObtained');
        $this->incStat($move, 'moveObtained', $playerId);
    }

    function getRemainingActions() {
        return $this->getGlobalVariable('REMAINING_ACTIONS');
    }

    function getCurrentAction($remainingActions = null) {
        if ($remainingActions == null) {
            $remainingActions = $this->getRemainingActions();
        }
        return $remainingActions->actions[$remainingActions->actions[0]->remaining > 0 ? 0 : 1];
    }

    function setActionOrder(int $startWithAction) {
        $remainingActions = $this->getRemainingActions();
        $remainingActions->startWith = $startWithAction == 2 ? 'MOVE' : 'PLACE';
        if ($startWithAction == 2) {
            $remainingActions->actions = [
                $remainingActions->actions[1],
                $remainingActions->actions[0],
            ];
        }
        $this->setGlobalVariable('REMAINING_ACTIONS', $remainingActions);
    }

    function decPlaceCount(int $dec) {
        $remainingActions = $this->getRemainingActions();
        $index = $this->array_find_key($remainingActions->actions, fn($action) => $action->type == 'PLACE');
        $remainingActions->actions[$index]->remaining -= $dec;
        $this->setGlobalVariable('REMAINING_ACTIONS', $remainingActions);
    }

    function decMoveCount(int $dec) {
        $remainingActions = $this->getRemainingActions();
        
        if ($remainingActions->currentFoulPlayId != null) {
            $discoverTile = $this->getDiscoverTileById($remainingActions->currentFoulPlayId);
            $this->discardDiscoverTile($discoverTile);
            $remainingActions->currentFoulPlayId = null;
            $dec--;
        }

        if ($dec > 0) {
            $index = $this->array_find_key($remainingActions->actions, fn($action) => $action->type == 'MOVE');
            $remainingActions->actions[$index]->remaining -= $dec;
        }
        
        $this->setGlobalVariable('REMAINING_ACTIONS', $remainingActions);
    }

    function updateCurrentVisibleScore(int $playerId) {
        $visibleScore = 0;

        // visible score : territory control
        $scenario = $this->getScenario();
        foreach ($scenario->battlefieldsIds as $battlefieldId) {
            foreach ($this->BATTLEFIELDS[$battlefieldId]->territories as $territory) {
                $controlledBy = $this->getTerritoryControlledPlayer($territory->id);
                if ($controlledBy === $playerId) {
                    $visibleScore += $territory->lumens;
                }
            }
        }

        // visible score : discover tiles
        $playerDiscoverTiles = $this->getDiscoverTilesByLocation('player', $playerId);
        foreach ($playerDiscoverTiles as $discoverTile) {
            if ($discoverTile->type === 1) {
                $visibleScore += $discoverTile->subType;
            }
        }

        self::notifyAllPlayers("updateVisibleScore", '', [
            'playerId' => $playerId,
            'score' => $visibleScore,
        ]);

        return $visibleScore;
    }

    function updateCurrentHiddenScore(int $playerId) {
        $hiddenScore = 0;

        // hidden score : objective tokens
        $objectiveTokens = $this->getObjectiveTokensFromDb($this->objectiveTokens->getCardsInLocation('player', $playerId));
        foreach ($objectiveTokens as $objectiveToken) {
            $hiddenScore += $objectiveToken->lumens;
        }
        self::notifyPlayer($playerId, "updateHiddenScore", '', [
            'playerId' => $playerId,
            'score' => $hiddenScore,
        ]);

        return $hiddenScore;
    }
    
    function getDiscoverTilesPoints(int $playerId) {
        $playerDiscoverTiles = $this->getDiscoverTilesByLocation('player', $playerId);

        $points = 0;
        foreach ($playerDiscoverTiles as $discoverTile) {
            if ($discoverTile->type === 1) {
                $points += $discoverTile->subType;
            }
        }

        return $points;
    }

    function getObjectiveTokensPoints(int $playerId) {
        $objectiveTokens = $this->getObjectiveTokensFromDb($this->objectiveTokens->getCardsInLocation('player', $playerId));

        $points = 0;
        foreach ($objectiveTokens as $objectiveToken) {
            $points += $objectiveToken->lumens;
        }

        return $points;
    }

    function checkPlayerElimination() { // return player(s) eliminated
        $playersIds = $this->getPlayersIds();
        $playersFightersCount = [];
        foreach ($playersIds as $playerId) {
            $playersFightersCount[$playerId] = count($this->getCardsByLocation('territory', null, $playerId));
        }

        if ($this->array_every($playersFightersCount, fn($count) => $count === 0)) {
            self::notifyAllPlayers("doubleElimination", clienttranslate('There is no fighter on the territories for both players, they are both eliminated'), []);
            $this->DbQuery("UPDATE player SET `player_score` = 0, `player_score_aux` = 0");

            return true;
        }

        $playerWithNoFighters = $this->array_find_key($playersFightersCount, fn($count) => $count === 0);
        if ($playerWithNoFighters !== null) {
            $opponentId = $this->getOpponentId($playerWithNoFighters);
            self::notifyAllPlayers("elimination", clienttranslate('${player_name} is eliminated because there is no fighter on the territories for this player'), [
                'playerId' => $playerWithNoFighters,
                'player_name' => $this->getPlayerName($playerWithNoFighters),
                'opponentId' => $opponentId,
            ]);
            $this->DbQuery("UPDATE player SET `player_score` = 0, `player_score_aux` = 0 WHERE player_id = $playerWithNoFighters");
            $this->DbQuery("UPDATE player SET `player_score` = 1, `player_score_aux` = 0 WHERE player_id = $opponentId");

            return true;
        }

        return false;
    }
}
