<?php

class Undo {
    public ?int $from;
    public array/*Tile[]*/ $tiles;
    public ?int $previousFirstPlayer;
    public ?bool $lastRoundBefore;

    public function __construct(array $tiles, ?int $from = null, ?int $previousFirstPlayer = null, ?bool $lastRoundBefore = null) {
        $this->from = $from;
        $this->tiles = $tiles;
        $this->previousFirstPlayer = $previousFirstPlayer;
        $this->lastRoundBefore = $lastRoundBefore;
    }
}
?>
