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
        self::DbQuery("update player set player_played_hand = 0");

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
            
                self::notifyAllPlayers('jackpotRemaining', clienttranslate('Card ${cardValue} is added to the jackpot ${colorName}'), [
                    'colorName' => $this->getColorName($card->color),
                    'color' => $card->color,
                    'card' => $card,
                    'cardValue' => '',
                    'preserve' => ['card', 'cardValue'],
                ]);
            } else if ($card->type == 2) {
                $this->cards->moveCard($card->id, 'discard');
            
                self::notifyAllPlayers('discardRemaining', clienttranslate('Card ${cardValue} is discarded'), [
                    'card' => $card,
                    'cardValue' => '',
                    'preserve' => ['card', 'cardValue'],
                ]);
            }
        }

        $lastRound = intval($this->cards->countCardInLocation('deck')) < $this->getRoundCardCount();
        if (!$lastRound) {            
            $this->setFirstPlayer($this->activeNextPlayer());
        }

        $this->gamestate->nextState($lastRound ? 'endScore' : 'newRound');
    }

    function stEndScore() {
        $playersIds = $this->getPlayersIds();

        // place remaining deck cards on jackpot
        self::notifyAllPlayers('log', clienttranslate('Remaining cards in deck are placed on Jackpot tokens...'), []);
        $cards = $this->getCardsByLocation('deck');
        foreach($cards as $card) {
            if ($card->type == 1) {
                $this->cards->moveCard($card->id, 'jackpot', $card->color);
            
                self::notifyAllPlayers('jackpotRemaining', clienttranslate('Card ${cardValue} is added to the jackpot ${colorName}'), [
                    'colorName' => $this->getColorName($card->color),
                    'color' => $card->color,
                    'card' => $card,
                    'cardValue' => '',
                    'preserve' => ['card', 'cardValue'],
                ]);
            } else if ($card->type == 2) {
                $this->cards->moveCard($card->id, 'discard');
            
                self::notifyAllPlayers('discardRemaining', clienttranslate('Card ${cardValue} is discarded'), [
                    'card' => $card,
                    'cardValue' => '',
                    'preserve' => ['card', 'cardValue'],
                ]);
            }
        }

        // TODO TOCHECK play automatically ?
        /*
En commençant par le premier joueur, vous pouvez jouer une première carte de
votre main. Puis vous répétez cette étape avec la seconde.*/
    foreach($playersIds as $playerId) {
        $this->closeLine($playerId);
        /*
Chaque carte Numéro dans votre pile de score rapporte 1 point, auquel vous ajoutez
les bonus ou malus de vos jetons Pari.



    function setPlayerScore(int $playerId, int $score) {
        $this->DbQuery("UPDATE player SET `player_score` = $score WHERE player_id = $playerId");
            
        $this->notifyAllPlayers('score', clienttranslate('${player_name} ends ${cardValue} to line'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'score' => $score,
        ]);
    }

Le joueur ayant le meilleur score remporte la partie !
En cas d'égalité, la victoire est partagée.*/

        $this->gamestate->nextState('endGame');
    }
}
