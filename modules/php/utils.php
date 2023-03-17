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

    function getFirstPlayer() {
        return intval($this->getGameStateValue(FIRST_PLAYER));
    }

    function setFirstPlayer(int $playerId) {
        $this->setGameStateValue(FIRST_PLAYER, $playerId);

        self::notifyAllPlayers('newFirstPlayer', '', [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
        ]);
    }

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

    // return list of cards that can be placed on the player's line
    function canPlaceOnLine(int $playerId, array $market = []) {
        $hand = $this->getCardsByLocation('hand', $playerId);

        $cards = array_merge($hand, $market);
        $line = $this->getCardsByLocation('line'.$playerId);
        $lineWithoutBet = array_values(array_filter($line, fn($card) => $card->type == 1));

        if (count($lineWithoutBet) < 2) {
            return $cards;
        }

        $direction = $lineWithoutBet[1]->number - $lineWithoutBet[0]->number;

        $lineLastNumber = $lineWithoutBet[count($lineWithoutBet) - 1]->number;

        if ($direction > 0) {
            return array_values(array_filter($cards, fn($card) => $card->type != 1 || $card->number > $lineLastNumber));
        } else {
            return array_values(array_filter($cards, fn($card) => $card->type != 1 ||$card->number < $lineLastNumber));
        }
    }

    function getPlayer(int $id) {
        $sql = "SELECT * FROM player WHERE player_id = $id";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbResult) => new Player($dbResult), array_values($dbResults))[0];
    }
   
    function getPlayers() {
        $sql = "SELECT * FROM player ORDER BY player_no";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbResult) => new Player($dbResult), array_values($dbResults));
    }

    function playCard(int $playerId, int $id) {
        $args = $this->argChooseMarketCard();
        $card = $this->array_find($args['canPlaceOnLine'], fn($c) => $c->id == $id);
        if ($card == null || $card->location != 'hand' || $card->locationArg != $playerId) {
            throw new BgaUserException("You can't play this card");
        }

        $this->cards->moveCard($id, 'line'.$playerId, intval($this->cards->countCardInLocation('line'.$playerId)));

        self::notifyAllPlayers('playCard', clienttranslate('${player_name} adds card ${cardValue} to line'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'cardValue' => '',
            'preserve' => ['card', 'cardValue'],
        ]);

        // TODO check jackpot
    }

    function closeLine(int $playerId) {
        $line = $this->getCardsByLocation('line'.$playerId);
        $lineWithoutBet = array_values(array_filter($line, fn($card) => $card->type == 1));
        $betCard = $this->array_find($line, fn($card) => $card->type == 2);

        if ($betCard != null)  {
            $cardsAfterBetCard = $this->array_find($lineWithoutBet, fn($card) => $card->locationArg > $betCard->locationArg);
            $betWon = count($cardsAfterBetCard) >= $betCard->number;
            $tokenNumber = $betWon ? $betCard->number : -$betCard->number;

            $tokens = $this->getPlayer($playerId)->tokens;
            $tokens[$tokenNumber]++;
            $this->DbQuery("UPDATE player SET `player_tokens` = '".json_encode($tokens)."' WHERE player_id = $playerId");

            // TODO notif

            // TOCHECK $this->cards->moveCard($betCard->id, 'discard');
        }

        $discardedCards = array_slice($line, 0, 3);
        $scoredCards = array_slice($line, 3);

        if (count($discardedCards) > 0) {
            $this->cards->moveCards(array_map(fn($card) => $card->id, $discardedCards), 'discard');
        }

        if (count($scoredCards) > 0) {
            $this->cards->moveCards(array_map(fn($card) => $card->id, $scoredCards), 'scored', $playerId);
        }

        // TODO notif

    }

    function getColor(int $color) {
        switch ($color) {
            case 1: return clienttranslate('Red');
            case 2: return clienttranslate('Blue');
            case 3: return clienttranslate('Green');
            case 4: return clienttranslate('Yellow');
        }
        return null;
    }
    
}
