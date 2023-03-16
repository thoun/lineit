<?php

class Card {
    public int $id;
    public string $location;
    public int $locationArg;
    public int $type; // 1 for number, 2 for bet
    public int $number;
    public /* int | null*/ $color;

    public function __construct($dbCard) {
        $this->id = intval($dbCard['card_id']);
        $this->location = $dbCard['card_location'];
        $this->locationArg = intval($dbCard['card_location_arg']);
        $this->type = intval($dbCard['card_type']);
        $this->number = intval($dbCard['card_type_arg']);
        if ($this->type == 1) {
            $this->color = ($this->number - 1) % 4 + 1;
        }
    } 

    public static function onlyId(Card $card) {
        return new Card([
            'card_id' => $card->id,
            'card_location' => $card->location,
            'card_location_arg' => $card->locationArg,
            'card_type' => null,
            'card_type_arg' => null,
            'player_id' => null,
            'played' => null,
        ], null);
    }

    public static function onlyIds(array $cards) {
        return array_map(fn($card) => self::onlyId($card), $cards);
    }
}

?>