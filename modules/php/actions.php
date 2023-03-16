<?php

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    public function continue() {
        $this->checkAction('continue'); 

        $this->gamestate->nextState('continue');
    }

    public function stop() {
        $this->checkAction('stop'); 

        $this->gamestate->nextState('stop');
    }

    
  	
    public function playCardFromHand(int $id) {
        $this->checkAction('playCardFromHand'); 

        $this->gamestate->nextState('pass');
    }
    
    public function playCardFromDeck(int $number) {
        $this->checkAction('playCardFromDeck'); 

        $this->gamestate->nextState('pass');
    }

    /*public function chooseDiceFaces(int $die1, int $die2) {
        $this->checkAction('chooseDiceFaces'); 
        
        if ($die1 < 0 || $die1 > 5 || $die2 < 1 || $die2 > 6) {
            throw new BgaUserException("Invalid die face");
        }
        
        $playerId = intval($this->getActivePlayerId());

        $planningTiles = $this->getDiscoverTilesByLocation('player', $playerId, null, 2, POWER_PLANNING);
        if (count($planningTiles) < 1) {
            throw new BgaUserException("No planning token");
        }

        $this->setGameStateValue(DIE1, $die1);
        $this->setGameStateValue(DIE2, $die2);

        $firstPlayer = intval($this->getGameStateValue(FIRST_PLAYER));
        self::notifyAllPlayers('diceChange', clienttranslate('${player_name} choses ${whiteDieFace} ${blackDieFace} with Planification'), [
            'player_name' => $this->getPlayerName($firstPlayer),
            'die1' => $die1,
            'die2' => $die2,
            'whiteDieFace' => $die1,
            'blackDieFace' => $die2,
        ]);

        // remove the used planification tile
        $this->discardDiscoverTile($planningTiles[0]);

        $this->gamestate->nextState('chooseOperation');
    }

    public function chooseOperation(int $type) {
        $this->checkAction('chooseOperation'); 

        $args = $this->argChooseOperation();
        $operation = $args['operations'][$type];
        if (!$operation || $operation['disabled'] != null) {
            throw new BgaUserException("This operation is impossible at the moment");
        }
        
        $playerId = intval($this->getActivePlayerId());
        $firstPlayer = intval($this->getGameStateValue(FIRST_PLAYER));
        $isFirstPlayer = $playerId == $firstPlayer;
        if ($isFirstPlayer) {
            $this->setGameStateValue(FIRST_PLAYER_OPERATION, $type);
        }
        $this->setGameStateValue(PLAYER_OPERATION, $type);
        $this->setGameStateValue(PLAYER_NUMBER, $operation['value']);

        self::DbQuery("update operation set nb = nb + 1 where player_id = $playerId and operation = $type");

        self::notifyAllPlayers('setPlayedOperation', clienttranslate('${player_name} chooses value ${number} (operation ${operation})'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'number' => $operation['value'], // for log
            'operation' => $type, // for log
            'type' => $type,
            'operationsNumber' => intval(self::getUniqueValueFromDB( "SELECT nb from operation where player_id = $playerId and operation = $type")),
            'firstPlayer' => $isFirstPlayer,
        ]);

        $this->gamestate->nextState('chooseCell');
    }

    public function cancelOperation() {
        $this->checkAction('cancelOperation'); 
        
        $playerId = intval($this->getActivePlayerId());
        $firstPlayer = intval($this->getGameStateValue(FIRST_PLAYER));
        $isFirstPlayer = $playerId == $firstPlayer;

        $type = intval($this->getGameStateValue(PLAYER_OPERATION));

        self::DbQuery("update operation set nb = nb - 1 where player_id = $playerId and operation = $type");

        self::notifyAllPlayers('setCancelledOperation', clienttranslate('${player_name} cancels operation choice'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'type' => $type,
            'operationsNumber' => intval(self::getUniqueValueFromDB( "SELECT nb from operation where player_id = $playerId and operation = $type")),
            'firstPlayer' => $isFirstPlayer,
        ]);

        $this->gamestate->nextState('cancel');
    }

    public function chooseCell(int $cellId) {
        $this->checkAction('chooseCell'); 
        $this->setGameStateValue(REMAINING_FIGHTERS_TO_PLACE, 0);
        $this->setGameStateValue(REMAINING_FIGHTERS_TO_MOVE_OR_ACTIVATE, 0);
        
        $playerId = intval($this->getActivePlayerId());
        
        $args = $this->argChooseCell();
        if (!in_array($cellId, $args['possibleCircles'])) {
            throw new BgaUserException("Invalid cell");
        }

        $this->setGameStateValue(PLAYER_CELL, $cellId);
        $value = intval($this->getGameStateValue(PLAYER_NUMBER));
        self::DbQuery("INSERT INTO circle (player_id, circle_id, value) VALUES ($playerId, $cellId, $value)");

        if ($value >= 7) {
            $this->incStat(1, 'figuresOver6', $playerId);
            $this->addCheck($playerId);
        }

        self::notifyAllPlayers('setCircleValue', '', [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'circleId' => $cellId,
            'value' => $value,
        ]);

        $newZoneCellCount = $this->refreshZones($playerId, $cellId);
        $this->setGameStateValue(REMAINING_FIGHTERS_TO_PLACE, $newZoneCellCount);
        if ($newZoneCellCount == 2) {
            $this->incStat(1, 'numberOfZones', $playerId);
        }

        $links = $this->getLinks($playerId);
        $currentCellInALink = false;
        $possibleUpperLinkCirclesIds = $this->getPossibleLinkCirclesIds($playerId, $links, $cellId, $value, 1);
        $possibleLowerLinkCirclesIds = $this->getPossibleLinkCirclesIds($playerId, $links, $cellId, $value, -1);

        if (count($possibleUpperLinkCirclesIds) === 1) {
            $this->addLink($playerId, $cellId, $possibleUpperLinkCirclesIds[0]);
            if (!$currentCellInALink) {
                $this->incGameStateValue(REMAINING_FIGHTERS_TO_MOVE_OR_ACTIVATE, 1);
                $currentCellInALink = true;
            }

            $isOtherCellInALink = $this->array_some($links, fn($link) => $link->index1 == $possibleUpperLinkCirclesIds[0] || $link->index2 == $possibleUpperLinkCirclesIds[0]);
            if (!$isOtherCellInALink) {
                $this->incGameStateValue(REMAINING_FIGHTERS_TO_MOVE_OR_ACTIVATE, 1);
            }
        }
        if (count($possibleLowerLinkCirclesIds) === 1) {
            $this->addLink($playerId, $cellId, $possibleLowerLinkCirclesIds[0]);
            if (!$currentCellInALink) {
                $this->incGameStateValue(REMAINING_FIGHTERS_TO_MOVE_OR_ACTIVATE, 1);
                $currentCellInALink = true;
            }

            $isOtherCellInALink = $this->array_some($links, fn($link) => $link->index1 == $possibleLowerLinkCirclesIds[0] || $link->index2 == $possibleLowerLinkCirclesIds[0]);
            if (!$isOtherCellInALink) {
                $this->incGameStateValue(REMAINING_FIGHTERS_TO_MOVE_OR_ACTIVATE, 1);
            }
        }

        if (count($possibleUpperLinkCirclesIds) > 1 || count($possibleLowerLinkCirclesIds) > 1) {
            $this->gamestate->nextState('chooseCellLink');
            return;
        }

        $this->gamestate->nextState('chooseAction');
    }

    public function chooseCellLink(int $cellId) {
        $this->checkAction('chooseCellLink'); 
        
        $playerId = intval($this->getActivePlayerId());
        
        $args = $this->argChooseCellLink();
        if (!in_array($cellId, $args['possibleLinkCirclesIds'])) {
            throw new BgaUserException("Invalid cell");
        }

        $fromCell = $args['cellId'];

        $links = $this->getLinks($playerId);
        $isOtherCellInALink = $this->array_some($links, fn($link) => $link->index1 == $cellId || $link->index2 == $cellId);
        $linkAddedToCurrentCell = $this->array_some($links, fn($link) => $link->index1 == $fromCell || $link->index2 == $fromCell);
        
        $this->addLink($playerId, $fromCell, $cellId);
        
        $this->incGameStateValue(REMAINING_FIGHTERS_TO_MOVE_OR_ACTIVATE, ($linkAddedToCurrentCell ? 0 : 1) + ($isOtherCellInALink ? 0 : 1));
        
        $this->gamestate->nextState('chooseAction');
    }

    public function chooseCellInterference(int $cellId) {
        $this->checkAction('chooseCellInterference'); 
        
        $playerId = intval($this->getActivePlayerId());
        $opponentId = $this->getOpponentId($playerId);
        
        $args = $this->argChooseCellInterference();
        if (!in_array($cellId, $args['possibleCircles'])) {
            throw new BgaUserException("Invalid cell");
        }

        self::DbQuery("INSERT INTO circle (player_id, circle_id, value) VALUES ($opponentId, $cellId, -1)");

        self::notifyAllPlayers('setCircleValue', clienttranslate('${player_name} interfere an opponent circle'), [
            'playerId' => $opponentId,
            'player_name' => $this->getPlayerName($playerId),
            'circleId' => $cellId,
            'value' => -1,
        ]);

        $nextState = 'nextMove';
        if (intval($this->getGameStateValue(PLAYER_CURRENT_MOVE)) == MOVE_SUPER) {
            $selectedFighterId = intval($this->getGameStateValue(PLAYER_SELECTED_FIGHTER));
            $selectedFighter = $this->getCardById($selectedFighterId);

            switch ($selectedFighter->power) {
                case POWER_PUSHER:
                    $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_PUSH);
                    break;
                case POWER_ASSASSIN:
                    $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_KILL);
                    break;
            }
            $nextState = 'chooseFighter';
        }

        $this->gamestate->nextState($nextState);
    }

    public function startWithAction(int $id) {
        $this->checkAction('startWithAction'); 
        if (!in_array($id, [1, 2])) {
            throw new BgaUserException("Invalid choice");
        }

        $this->setActionOrder($id);

        $this->gamestate->nextState('nextMove');
    }

    public function playFighter(int $id) {
        $this->checkAction('playFighter'); 
        
        $playerId = intval($this->getActivePlayerId());

        $currentAction = $this->getCurrentAction();
        if ($currentAction->type != 'PLACE' || $currentAction->remaining == 0) {
            throw new BgaUserException("No remaining action");
        }
        if (intval($this->getGameStateValue(PLAYER_CURRENT_MOVE)) > 0) {
            throw new BgaUserException("Impossible to play a fighter now");
        }

        $fighter = $this->getCardById($id);
        
        if ($fighter->playerId != $playerId || !in_array($fighter->location, ['reserve'.$playerId, 'highCommand'.$playerId])) {
            throw new BgaUserException("Invalid fighter");
        }
        if (!in_array($fighter->type, [1, 10])) {
            throw new BgaUserException("This is not a fighter");
        }

        $this->setGameStateValue(PLAYER_SELECTED_FIGHTER, $id);
        $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_PLAY);

        if ($this->getScenarioId() == 1 && $fighter->type == 10 && !$this->isRealizedObjective('1')) {
            $this->takeScenarioObjectiveToken($playerId, '1');
            $this->setRealizedObjective('1', $playerId);
            $this->incStat(1, 'completedObjectives');
            $this->incStat(1, 'completedObjectives', $playerId);
        }

        $this->gamestate->nextState('chooseTerritory');
    }

    public function moveFighter(int $id) {
        $this->checkAction('moveFighter'); 
        
        //$playerId = intval($this->getActivePlayerId());

        $currentAction = $this->getCurrentAction();
        if ($currentAction->type != 'MOVE' || $currentAction->remaining == 0) {
            $remainingActions = $this->getRemainingActions();
            if ($remainingActions->currentFoulPlayId == null) {
                throw new BgaUserException("No remaining action");
            }
        }
        if (intval($this->getGameStateValue(PLAYER_CURRENT_MOVE)) > 0) {
            throw new BgaUserException("Impossible to move a fighter now");
        }
        
        $args = $this->argChooseFighter();
        $possibleFightersToMove = $args['possibleFightersToMove'];
        if (!$this->array_some($possibleFightersToMove, fn($fighter) => $fighter->id == $id)) {
            throw new BgaUserException("Impossible to move this fighter");
        }

        $fighter = $this->getCardById($id);
        
        /* checked with possibleFightersToActivate 
        if ($fighter->playerId != $playerId || $fighter->location != 'territory') {
            throw new BgaUserException("Invalid fighter");
        }

        if ($fighter->power === POWER_MUDSHELL && $this->getScenarioId() != 5) {
            throw new BgaUserException("The Baveux cannot be moved");
        }
        if ($fighter->power === POWER_WEAVER && $fighter->played) {
            throw new BgaUserException("The Tisseuse cannot be moved when activated");
        }
        if ($fighter->power === POWER_ROOTSPRING && $fighter->played) {
            throw new BgaUserException("The Rooted cannot be moved when activated");
        }
        if ($fighter->power === POWER_METAMORPH && !$fighter->played) {
            throw new BgaUserException("The Metamorph cannot be moved until activated");
        }

        if ($fighter->location === 'territory') {
            $opponentTisseuses = $this->getCardsByLocation($fighter->location, $fighter->locationArg, $this->getOpponentId($playerId), null, 15);
            if ($this->array_some($opponentTisseuses, fn($opponentTisseuse) => $opponentTisseuse->played)) {
                throw new BgaUserException("An opponent Tisseuse prevents you to leave the territory");
            }

            if ($fighter->locationArg % 10 == 5 && $this->getScenarioId() == 5 && $fighter->power !== POWER_MUDSHELL) {
                throw new BgaUserException("Only Baveux can move from a green territory");
            }
        }
        *_/

        $this->setGameStateValue(PLAYER_SELECTED_FIGHTER, $id);
        $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_MOVE);

        $this->gamestate->nextState('chooseTerritory');
    }

    public function activateFighter(int $id) {
        $this->checkAction('activateFighter'); 

        $currentAction = $this->getCurrentAction();
        if ($currentAction->type != 'MOVE' || $currentAction->remaining == 0) {
            $remainingActions = $this->getRemainingActions();
            if ($remainingActions->currentFoulPlayId == null) {
                throw new BgaUserException("No remaining action");
            }
        }
        if (intval($this->getGameStateValue(PLAYER_CURRENT_MOVE)) > 0) {
            throw new BgaUserException("Impossible to activate a fighter now");
        }
        
        $args = $this->argChooseFighter();
        $possibleFightersToActivate = $args['possibleFightersToActivate'];
        if (!$this->array_some($possibleFightersToActivate, fn($fighter) => $fighter->id == $id)) {
            throw new BgaUserException("Impossible to activate this fighter");
        }

        $fighter = $this->getCardById($id);

        /* checked with possibleFightersToActivate 
        $action = $fighter->type === 20;
        if ($action) {
            if ($fighter->location != 'highCommand'.$playerId) {
                throw new BgaUserException("You can't activate this action");
            }
        } else {
            if ($fighter->playerId != $playerId || $fighter->location != 'territory') {
                throw new BgaUserException("Invalid fighter");
            }
        }
        if ($fighter->played) {
            throw new BgaUserException("This fighter is already played");
        }
        if (!$fighter->power || $fighter->power === POWER_MUDSHELL) {
            throw new BgaUserException("This fighter has no activable power");
        }*_/

        if ($fighter->type === 20) {
            $this->applyAction($fighter);
        } else {
            $this->applyActivateFighter($fighter);
        }
    }

    public function chooseFighters(array $ids) {        
        $this->checkAction('chooseFighters'); 
        
        $playerId = intval($this->getActivePlayerId());

        $args = $this->argChooseFighter();
        if ($args['move'] == MOVE_FURY) {
            if (count($ids) > $args['selectionSize']) {
                throw new BgaUserException("Invalid selection size");
            }
        } else {
            if (!in_array($args['selectionSize'], [-1, count($ids)])) {
                throw new BgaUserException("Invalid selection size");
            }
        }
        $fighters = [];
        $possibleTerritoryFightersIds = array_map(fn($fighter) => $fighter->id, $args['possibleTerritoryFighters']);
        foreach($ids as $id) {
            if (!in_array($id, $possibleTerritoryFightersIds)) {
                throw new BgaUserException("Invalid fighter");
            }
            $fighters[] = $this->getCardById($id);
        }
        $fighter = $fighters[0];

        $selectedFighterId = intval($this->getGameStateValue(PLAYER_SELECTED_FIGHTER));
        $selectedFighter = $this->getCardById($selectedFighterId);

        $nextState = 'nextMove';
        switch ($selectedFighter->power) {
            case POWER_PUSHER:                 
                $this->setGameStateValue(PLAYER_SELECTED_TARGET, $fighter->id);
                $this->incStat(1, 'activatedFighters', $playerId);
                $nextState = 'chooseTerritory';
                break;
            case POWER_ASSASSIN:
            case POWER_BOMBER:
                $this->putBackInBag([$fighter]);
                $this->checkTerritoriesDiscoverTileControl($playerId);
                $this->incStat(1, 'activatedFighters', $playerId);
                
                self::notifyAllPlayers('log', clienttranslate('${player_name} activates ${fighterType} to kill ${fighterType2} on ${season} territory ${battlefieldId}'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'fighter' => $selectedFighter, // for logs
                    'fighterType' => $selectedFighter->subType, // for logs
                    'fighter2' => $fighter, // for logs
                    'fighterType2' => $fighter->subType, // for logs
                    'season' => $this->getSeasonName($this->TERRITORIES[$fighter->locationArg]->lumens),
                    'battlefieldId' => floor($fighter->locationArg / 10),
                    'i18n' => ['season'],
                    'preserve' => ['fighter', 'fighterType', 'fighter2', 'fighterType2'],
                ]);
                break;
            case POWER_HYPNOTIST:
                $this->setFightersActivated($fighters);
                $this->incStat(1, 'activatedFighters', $playerId);
                
                self::notifyAllPlayers('log', clienttranslate('${player_name} activates ${fighterType} to set surrounding opponents to their inactive face'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'fighter' => $selectedFighter, // for logs
                    'fighterType' => $selectedFighter->subType, // for logs
                    'i18n' => ['season'],
                    'preserve' => ['fighter', 'fighterType'],
                ]);
                break;

            case ACTION_FURY:
                if (count($fighters) >= 2 && $fighters[0]->locationArg == $fighters[1]->locationArg) {
                    throw new BgaUserException("You must select fighters of different territories");
                }
                $this->putBackInBag(array_merge($fighters, [$selectedFighter]));
                $this->incStat(1, 'playedActions', $playerId);
                $this->checkTerritoriesDiscoverTileControl($playerId);
                
                foreach($fighters as $iFighter) {
                    self::notifyAllPlayers('log', clienttranslate('${player_name} activates ${fighterType} to kill ${fighterType2} on ${season} territory ${battlefieldId}'), [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerName($playerId),
                        'fighter' => $selectedFighter, // for logs
                        'fighterType' => $selectedFighter->subType, // for logs
                        'fighter2' => $iFighter, // for logs
                        'fighterType2' => $iFighter->subType, // for logs
                        'season' => $this->getSeasonName($this->TERRITORIES[$iFighter->locationArg]->lumens),
                        'battlefieldId' => floor($iFighter->locationArg / 10),
                        'i18n' => ['season'],
                        'preserve' => ['fighter', 'fighterType', 'fighter2', 'fighterType2'],
                    ]);
                }
                break;
            case ACTION_CLEAN_SHEET:
                $fighters = $this->getCardsByLocation($fighter->location, $fighter->locationArg);
                $this->putBackInBag(array_merge($fighters, [$selectedFighter]));
                $this->incStat(1, 'playedActions', $playerId);
                
                foreach($fighters as $iFighter) {
                    self::notifyAllPlayers('log', clienttranslate('${player_name} activates ${fighterType} to kill ${fighterType2} on ${season} territory ${battlefieldId}'), [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerName($playerId),
                        'fighter' => $selectedFighter, // for logs
                        'fighterType' => $selectedFighter->subType, // for logs
                        'fighter2' => $iFighter, // for logs
                        'fighterType2' => $iFighter->subType, // for logs
                        'season' => $this->getSeasonName($this->TERRITORIES[$iFighter->locationArg]->lumens),
                        'battlefieldId' => floor($iFighter->locationArg / 10),
                        'i18n' => ['season'],
                        'preserve' => ['fighter', 'fighterType', 'fighter2', 'fighterType2'],
                    ]);
                }
                break;
            case ACTION_TELEPORTATION:
                $this->cards->moveCard($fighters[0]->id, 'territory', $fighters[1]->locationArg);
                $this->cards->moveCard($fighters[1]->id, 'territory', $fighters[0]->locationArg);
                $this->putBackInBag([$selectedFighter]);
                $this->incStat(1, 'playedActions', $playerId);
                $this->checkTerritoriesDiscoverTileControl($playerId);
        
                self::notifyAllPlayers("swappedFighters", clienttranslate('${player_name} activates ${fighterType} to swap ${fighterType2} with ${fighterType3}'), [
                    'fighters' => $fighters,
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'fighter' => $selectedFighter, // for logs
                    'fighterType' => $selectedFighter->subType, // for logs
                    'fighter2' => $fighters[0], // for logs
                    'fighterType2' => $fighters[0]->subType, // for logs
                    'fighter3' => $fighters[1], // for logs
                    'fighterType3' => $fighters[1]->subType, // for logs
                    'preserve' => ['fighter', 'fighterType', 'fighter2', 'fighterType2', 'fighter3', 'fighterType3'],
                ]);
                break;
        }
        if (in_array($nextState, ['nextMove', 'chooseCellInterference'])) {
            $this->decMoveCount(1);
        }

        $this->gamestate->nextState($nextState);
    }

    public function cancelChooseFighters() {
        $this->checkAction('cancelChooseFighters');

        $args = $this->argChooseFighter();

        if (in_array($args['move'], [MOVE_PUSH, MOVE_KILL, MOVE_UNACTIVATE])) {
            $selectedFighter = $this->getCardById(intval($this->getGameStateValue(PLAYER_SELECTED_FIGHTER)));
            $this->setFightersUnactivated([$selectedFighter]);
        }

        $this->setGameStateValue(PLAYER_SELECTED_FIGHTER, 0);
        $this->setGameStateValue(PLAYER_CURRENT_MOVE, 0);

        $this->gamestate->nextState('cancel');
    }

    public function passChooseFighters() {        
        $this->checkAction('passChooseFighters');

        $this->gamestate->nextState('nextMove');
    }

    public function useFoulPlay() {        
        $this->checkAction('useFoulPlay');
        
        $playerId = intval($this->getActivePlayerId());

        $tiles = $this->getDiscoverTilesByLocation('player', $playerId, null, 2, POWER_FOUL_PLAY);
        if (count($tiles) < 1) {
            throw new BgaUserException("No Foul Play tile");
        }
        
        $remainingActions = $this->getRemainingActions();
        $remainingActions->currentFoulPlayId = $tiles[0]->id;
        $this->setGlobalVariable('REMAINING_ACTIONS', $remainingActions);

        $this->gamestate->nextState('useFoulPlay');
    }

    public function cancelFoulPlay() {        
        $this->checkAction('cancelFoulPlay');

        $remainingActions = $this->getRemainingActions();
        $remainingActions->currentFoulPlayId = null;
        $this->setGlobalVariable('REMAINING_ACTIONS', $remainingActions);

        $this->gamestate->nextState('nextMove');
    }

    public function chooseTerritory(int $territoryId) {
        $this->checkAction('chooseTerritory'); 
        
        $playerId = intval($this->getActivePlayerId());

        $args = $this->argChooseTerritory();
        $selectedFighter = $args['selectedFighter'];

        if ($selectedFighter == null) {
            throw new BgaUserException("No selected fighter");
        }
        
        $move = $args['move'];
        if ($move <= 0) {
            throw new BgaUserException("No selected move");
        }

        if (!in_array($territoryId, $args['territoriesIds'])) {
            throw new BgaUserException("Invalid territory");
        }

        $dec = 1;

        $nextState = 'nextMove';
        switch ($move) {
            case MOVE_PLAY:
                $this->applyMoveFighter($selectedFighter, $territoryId, clienttranslate('${player_name} plays ${fighterType} on ${season} territory ${battlefieldId}'));
                $this->incStat(1, 'placedFighters', $playerId);
                if ($selectedFighter->type == 10) {                    
                    $this->incStat(1, 'placedMercenaries', $playerId);
                }
                $this->checkTerritoriesDiscoverTileControl($playerId);
                break;
            case MOVE_MOVE:
                $originTerritoryId = $selectedFighter->locationArg;
                $redirectInterference = $this->applyMoveFighter($selectedFighter, $territoryId, clienttranslate('${player_name} moves ${fighterType} from ${originSeason} territory ${originBattlefieldId} to ${season} territory ${battlefieldId}'), [
                    'originSeason' => $this->getSeasonName($this->TERRITORIES[$originTerritoryId]->lumens),
                    'originBattlefieldId' => floor($originTerritoryId / 10),
                    'i18n' => ['originSeason'],
                ]);
                if ($redirectInterference) {
                    $nextState = 'chooseCellInterference';
                }

                if ($this->getScenarioId() == 3 && array_key_exists($originTerritoryId, $this->RIVER_CROSS_TERRITORIES) && in_array($territoryId, $this->RIVER_CROSS_TERRITORIES[$originTerritoryId])) {
                    $dec = 2;
                }
                $this->incStat(1, 'movedFighters', $playerId);
                break;
            case MOVE_SUPER:
                $originTerritoryId = $selectedFighter->locationArg;
                $redirectInterference = $this->applyMoveFighter($selectedFighter, $territoryId, clienttranslate('${player_name} moves ${fighterType} from ${originSeason} territory ${originBattlefieldId} to ${season} territory ${battlefieldId}'), [
                    'originSeason' => $this->getSeasonName($this->TERRITORIES[$originTerritoryId]->lumens),
                    'originBattlefieldId' => floor($originTerritoryId / 10),
                    'i18n' => ['originSeason'],
                ]);
                if ($redirectInterference) {
                    $nextState = 'chooseCellInterference';
                } else {
                    switch ($selectedFighter->power) {
                        case POWER_PUSHER:
                            $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_PUSH);
                            break;
                        case POWER_ASSASSIN:
                            $this->setGameStateValue(PLAYER_CURRENT_MOVE, MOVE_KILL);
                            break;
                    }
                    $nextState = 'chooseFighter';
                }
                break;
            case MOVE_PUSH:
                $originTerritoryId = $selectedFighter->locationArg;
                $redirectInterference = $this->applyMoveFighter($selectedFighter, $territoryId, clienttranslate('${player_name} pushes ${fighterType} from ${originSeason} territory ${originBattlefieldId} to ${season} territory ${battlefieldId}'), [
                    'originSeason' => $this->getSeasonName($this->TERRITORIES[$originTerritoryId]->lumens),
                    'originBattlefieldId' => floor($originTerritoryId / 10),
                    'i18n' => ['originSeason'],
                ]);
                if ($redirectInterference) {
                    $nextState = 'chooseCellInterference';
                }
                break;
            case MOVE_FLY:
                $originTerritoryId = $selectedFighter->locationArg;
                $redirectInterference = $this->applyMoveFighter($selectedFighter, $territoryId, clienttranslate('${player_name} flies ${fighterType} from ${originSeason} territory ${originBattlefieldId} to ${season} territory ${battlefieldId}'), [
                    'originSeason' => $this->getSeasonName($this->TERRITORIES[$originTerritoryId]->lumens),
                    'originBattlefieldId' => floor($originTerritoryId / 10),
                    'i18n' => ['originSeason'],
                ]);
                if ($redirectInterference) {
                    $nextState = 'chooseCellInterference';
                }
                break;
            case MOVE_IMPATIENT:
                $this->setGameStateValue(INITIATIVE_MARKER_TERRITORY, $territoryId);
                self::notifyAllPlayers('moveInitiativeMarker', clienttranslate('${player_name} activates ${fighterType} to move the initiative marker to ${season} territory ${battlefieldId}'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'territoryId' => $territoryId,
                    'fighter' => $selectedFighter, // for logs
                    'fighterType' => $selectedFighter->subType, // for logs
                    'season' => $this->getSeasonName($this->TERRITORIES[$territoryId]->lumens),
                    'battlefieldId' => floor($territoryId / 10),
                    'i18n' => ['season'],
                    'preserve' => ['fighter', 'fighterType'],
                ]);
                $this->incStat(1, 'activatedFighters', $playerId);
                break;
        }
        if (in_array($nextState, ['nextMove', 'chooseCellInterference'])) {
            if ($move == MOVE_PLAY) {
                $this->decPlaceCount(1);
            } else {
                $this->decMoveCount($dec);
            }
        }

        $this->gamestate->nextState($nextState);
    }

    public function cancelChooseTerritory() {
        $this->checkAction('cancelChooseTerritory'); 

        $args = $this->argChooseTerritory();
        if(!$args['canCancel']) {
            throw new BgaUserException("Cancel is not available");
        }

        if (in_array($args['move'], [MOVE_SUPER, MOVE_FLY, MOVE_IMPATIENT])) {
            $selectedFighter = $this->getCardById(intval($this->getGameStateValue(PLAYER_SELECTED_FIGHTER)));
            $this->setFightersUnactivated([$selectedFighter]);
        }

        if (intval($this->getGameStateValue(PLAYER_SELECTED_TARGET)) > 0) {
            $this->setGameStateValue(PLAYER_SELECTED_TARGET, 0);
        } else {
            $this->setGameStateValue(PLAYER_SELECTED_FIGHTER, 0);
            $this->setGameStateValue(PLAYER_CURRENT_MOVE, 0);
        }

        $this->gamestate->nextState('cancel');
    }

    public function pass() {
        $this->checkAction('pass');

        $this->gamestate->nextState('nextPlayer');
    }*/
}
