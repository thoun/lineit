<?php

class Player {
    public int $id;
    public string $name;
    public string $color;
    public int $no;
    public int $score;
    public $jackpotColors;
    public $tokens;
    public bool $playedHand;

    public function __construct($dbPlayer) {
        $this->id = intval($dbPlayer['player_id']);
        $this->name = $dbPlayer['player_name'];
        $this->color = $dbPlayer['player_color'];
        $this->no = intval($dbPlayer['player_no']);
        $this->score = intval($dbPlayer['player_score']);
        $this->jackpotColors = json_decode($dbPlayer['player_jackpot_colors'] ?? '[]', true);
        $this->tokens = json_decode($dbPlayer['player_tokens'] ?? '[]', true);
        $this->playedHand = boolval($dbPlayer['player_played_hand']);
    }
}
?>