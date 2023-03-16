<?php

trait StateTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewRound() {
        // place new market cards
        $this->cards->pickCardsForLocation($this->getRoundCardCount(), 'deck', 'market'); 

        $this->gamestate->nextState('next');
    }

    function stChooseMarketCard() {
        $args = $this->argChooseMarketCard();
        if (!$args['canAddToLine'] && !$args['canAddToHand']) {        
            $this->gamestate->nextState('next');
        }
    }

    function stNextPlayer() {
        $playerId = intval($this->getActivePlayerId());

        $this->giveExtraTime($playerId);

        $playerId = intval($this->activeNextPlayer());

        $endRound = $playerId == $this->getFirstPlayer();
        if ($endRound) {
            $this->gamestate->nextState('endRound');
        } else {
            $this->activeNextPlayer();
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stEndRound() {
        $this->incStat(1, 'roundNumber');

        $cards = $this->getCardsByLocation('market');
        foreach($cards as $card) {
            if ($card->type == 1) {
                $this->cards->moveCard($card->id, 'jackpot', $card->color);
            } else if ($card->type == 2) {
                $this->cards->moveCard($card->id, 'discard');
            }
            // TODO notif
        }

        $lastRound = intval($this->cards->countCardInLocation('deck')) < $this->getRoundCardCount();
        if (!$lastRound) {            
            $this->setFirstPlayer($this->activeNextPlayer());
        }

        $this->gamestate->nextState($lastRound ? 'endScore' : 'newRound');
    }

    function stEndScore() {
        $playersIds = $this->getPlayersIds();

        /* TODO $scenarioId = $this->getScenarioId();
        $scenario = $this->getScenario();

        $this->scoreMissions($playersIds, $scenario);
        $this->scoreTerritoryControl($playersIds, $scenario);
        $this->scoreDiscoverTiles($playersIds);
        $this->scoreScenarioEndgameObjectives($scenarioId);
        $this->scoreObjectiveTokens($playersIds);

        // update player_score_aux
        $initiativeMarkerControlledPlayer = $this->getTerritoryControlledPlayer(intval($this->getGameStateValue(INITIATIVE_MARKER_TERRITORY)));
        if ($initiativeMarkerControlledPlayer !== null) {
            $this->DbQuery("UPDATE `player` SET `player_score_aux` = 1 WHERE `player_id` = $initiativeMarkerControlledPlayer"); 
        }

        $this->endStats($playersIds);*/

        $this->gamestate->nextState('endGame');
    }
}
