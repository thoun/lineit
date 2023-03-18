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
        $hand = $this->getPlayer($playerId)->playedHand ? [] : $this->getCardsByLocation('hand', $playerId);

        $cards = array_merge($hand, $market);
        $line = $this->getCardsByLocation('line'.$playerId);
        $hasBetCard = count(array_filter($line, fn($card) => $card->type == 2)) > 0;
        $lineWithoutBet = array_values(array_filter($line, fn($card) => $card->type == 1));

        if ($hasBetCard) {
            $cards = array_values(array_filter($cards, fn($card) => $card->type != 2));
        }

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
        return array_map(fn($dbResult) => new LineItPlayer($dbResult), array_values($dbResults))[0];
    }
   
    function getPlayers() {
        $sql = "SELECT * FROM player ORDER BY player_no";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbResult) => new LineItPlayer($dbResult), array_values($dbResults));
    }

    function playCard(int $playerId, int $id, $fromMarket = false) {
        $args = $this->argChooseMarketCard();
        $card = $this->array_find($args['canPlaceOnLine'], fn($c) => $c->id == $id);
        if (($card == null)  ||
            (!$fromMarket && ($card->location != 'hand' || $card->locationArg != $playerId)) ||
            ($fromMarket && $card->location != 'market')) {
            throw new BgaUserException("You can't play this card");
        }

        $this->cards->moveCard($id, 'line'.$playerId, intval($this->cards->countCardInLocation('line'.$playerId)));

        self::notifyAllPlayers('playCard', clienttranslate('${player_name} adds card ${cardValue} to line'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'cardValue' => '',
            'preserve' => ['card', 'cardValue'],
            'fromHand' => $card->location == 'hand',
        ]);

        if ($card->type == 1) {
            $this->checkJackpot($playerId, $card->color);
        } else if ($card->type == 2) {
            $this->incStat(1, 'betCardsPlayed');   
            $this->incStat(1, 'betCardsPlayed', $playerId);   
        }
    }

    function checkJackpot(int $playerId, int $color) {        
        $line = $this->getCardsByLocation('line'.$playerId);
        $lineColorCards = count(array_filter($line, fn($card) => $card->type == 1 && $card->color == $color));
        if ($lineColorCards == 3) {
            $this->applyJackpot($playerId, $color);
        }
    }

    function applyJackpot(int $playerId, int $color) {
        $jackpotCardsCount = intval($this->cards->countCardInLocation('jackpot', $color));
        if ($jackpotCardsCount > 0) {
            $this->cards->moveAllCardsInLocation('jackpot', 'scored', $color, $playerId);            
            self::DbQuery("update player set player_score = player_score + $jackpotCardsCount where `player_id` = $playerId");
        }
        self::notifyAllPlayers($jackpotCardsCount > 0 ? 'applyJackpot' : 0, clienttranslate('${player_name} adds ${count} card(s) from the jackpot pile to scored cards'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'count' => $jackpotCardsCount,
            'color' => $color,
        ]);
        $this->incStat(1, 'jackpotCollected');   
        $this->incStat(1, 'jackpotCollected', $playerId); 

        $this->incStat($jackpotCardsCount, 'pointsFromJackpots');   
        $this->incStat($jackpotCardsCount, 'pointsFromJackpots', $playerId);   
    }

    function applyCloseLine(int $playerId) {
        self::notifyAllPlayers('log', clienttranslate('${player_name} closes his line'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
        ]);

        $line = $this->getCardsByLocation('line'.$playerId);
        $lineWithoutBet = array_values(array_filter($line, fn($card) => $card->type == 1));
        $betCard = $this->array_find($line, fn($card) => $card->type == 2);

        if ($betCard != null)  {
            $cardsAfterBetCard = array_values(array_filter($lineWithoutBet, fn($card) => $card->locationArg > $betCard->locationArg));
            $betWon = count($cardsAfterBetCard) >= $betCard->number;
            $tokenNumber = $betWon ? $betCard->number : -$betCard->number;

            $tokens = $this->getPlayer($playerId)->tokens;
            $tokens[$tokenNumber]++;
            $this->DbQuery("UPDATE player SET `player_tokens` = '".json_encode($tokens)."', player_score = player_score + $tokenNumber WHERE player_id = $playerId");

            self::notifyAllPlayers('betResult', $betWon ? clienttranslate('${player_name} won the ${cardValue} bet') : clienttranslate('${player_name} lost the ${cardValue} bet'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'value' => $tokenNumber,
                'card' => $betCard,
                'cardValue' => '',
                'preserve' => ['card', 'cardValue'],
            ]);

            $statName = $betWon ? 'betWon' : 'betLost' ;
            $this->incStat(1, $statName);   
            $this->incStat(1, $statName, $playerId);  
            $this->incStat($tokenNumber, 'pointsFromBet');   
            $this->incStat($tokenNumber, 'pointsFromBet', $playerId);  
        }

        $discardedCards = array_slice($line, 0, 3);
        $scoredCards = array_slice($line, 3);

        if (count($discardedCards) > 0) {
            $this->cards->moveCards(array_map(fn($card) => $card->id, $discardedCards), 'discard');
        }

        if (count($scoredCards) > 0) {
            $this->cards->moveCards(array_map(fn($card) => $card->id, $scoredCards), 'scored', $playerId);
            self::DbQuery("update player set player_score = player_score + ".count($scoredCards)." where `player_id` = $playerId");
        }

        self::notifyAllPlayers('closeLine', clienttranslate('${player_name} adds ${count} card(s) from the line to scored cards (${removed} removed cards)'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'count' => count($scoredCards),
            'removed' => count($discardedCards),
        ]);

        $this->incStat(count($scoredCards), 'pointsFromLines');   
        $this->incStat(count($scoredCards), 'pointsFromLines', $playerId); 
        if (count($lineWithoutBet) >= 2) {
            $statName = $lineWithoutBet[1]->number > $lineWithoutBet[0]->number ? 'increasingLines' : 'decreasingLines';
            $this->incStat(1, $statName);   
            $this->incStat(1, $statName, $playerId);   
        }
        
        $this->incStat(1, 'closedLines');   
        $this->incStat(1, 'closedLines', $playerId);   
    }

    function getColorName(int $color) {
        switch ($color) {
            case 1: return clienttranslate('Red');
            case 2: return clienttranslate('Blue');
            case 3: return clienttranslate('Green');
            case 4: return clienttranslate('Yellow');
        }
        return null;
    }
    
}
