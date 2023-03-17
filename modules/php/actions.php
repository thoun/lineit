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
            throw new BgaUserException("You already played a card from hand on this round");
        }

        $this->playCard($playerId, $id);

        self::DbQuery("update player set player_played_hand = 1 where player_id = $playerId");

        $this->gamestate->nextState('stay');
    }

    public function chooseMarketCardLine(int $id) {
        $this->checkAction('chooseMarketCardLine'); 
        
        $playerId = intval($this->getActivePlayerId());

        $this->playCard($playerId, $id);

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

        $this->gamestate->nextState('next');
    }
  	
    public function closeLine() {
        $this->checkAction('closeLine'); 
        
        $playerId = intval($this->getActivePlayerId());

        $this->closeLine($playerId);

        $this->gamestate->nextState('next');
    }
  	
    public function pass() {
        $this->checkAction('pass'); 

        $this->gamestate->nextState('next');
    }
}
