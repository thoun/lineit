<?php

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    public function playCardFromHand(int $id) {
        $this->checkAction('playCardFromHand'); 
        
        $playerId = intval($this->getActivePlayerId());

        if ($this->getPlayer($playerId)->playedHand) {
            throw new BgaUserException("You already played a card from your hand on this round");
        }

        $this->playCard($playerId, $id);

        $stateName = $this->gamestate->state()['name'];
        if($stateName != 'playHandCard') {
            self::DbQuery("update player set player_played_hand = 1 where player_id = $playerId");
        }

        $this->incStat(1, 'playedCardFromHand');   
        $this->incStat(1, 'playedCardFromHand', $playerId);   

        $this->gamestate->nextState($stateName == 'playHandCard' ? 'next' : 'stay');
    }

    public function chooseMarketCardLine(int $id) {
        $this->checkAction('chooseMarketCardLine'); 
        
        $playerId = intval($this->getActivePlayerId());

        $args = $this->argChooseMarketCard();
        if (!$this->array_some($args['canPlaceOnLine'], fn($card) => $card->id == $id)) {
            throw new BgaUserException("You can't play this card");
        }

        $this->playCard($playerId, $id, true);

        $this->incStat(1, 'marketToLine');   
        $this->incStat(1, 'marketToLine', $playerId);   

        $this->gamestate->nextState('next');
    }

    public function chooseMarketCardHand(int $id) {
        $this->checkAction('chooseMarketCardHand'); 
        
        $playerId = intval($this->getActivePlayerId());

        $args = $this->argChooseMarketCard();
        if (!$args['canAddToHand']) {
            throw new BgaUserException("Your hand is full (2 cards max)");
        }

        $this->cards->moveCard($id, 'hand', $playerId);
        $card = $this->getCardById($id);
        
        self::notifyAllPlayers('chooseMarketCardHand', clienttranslate('${player_name} adds card ${cardValue} to hand'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'cardValue' => '',
            'preserve' => ['card', 'cardValue'],
        ]);

        $this->incStat(1, 'marketToHand');   
        $this->incStat(1, 'marketToHand', $playerId);   

        $this->gamestate->nextState('next');
    }
  	
    public function closeLine() {
        $this->checkAction('closeLine'); 
        
        $playerId = intval($this->getActivePlayerId());

        $forced = false;
        if ($this->gamestate->state()['name'] == 'chooseMarketCard') {
            $args = $this->argChooseMarketCard();
            if ($args['mustClose']) {
                $forced = true;
            }
        }

        $this->applyCloseLine($playerId);
        
        if ($forced) {
            $this->incStat(1, 'closedLinesForced');   
            $this->incStat(1, 'closedLinesForced', $playerId);   
        }

        $this->gamestate->nextState('stay');
    }
  	
    public function pass() {
        $this->checkAction('pass');         

        if($this->gamestate->state()['name'] == 'playHandCard') {
            $playerId = intval($this->getActivePlayerId());
            self::DbQuery("update player set player_played_hand = 1 where player_id = $playerId");
        }

        $this->gamestate->nextState('next');
    }
}
