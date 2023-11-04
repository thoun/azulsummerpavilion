<?php

class UndoSelect {
    public int $from;
    public array/*Tile[]*/ $tiles;
    public int $previousFirstPlayer;
    public int $previousScore;

    public function __construct(array $tiles, int $from, int $previousFirstPlayer, int $previousScore) {
        $this->from = $from;
        $this->tiles = $tiles;
        $this->previousFirstPlayer = $previousFirstPlayer;
        $this->previousScore = $previousScore;
    }
}

class UndoPlace {
    public array/*Tile[]*/ $tiles;
    public int $previousScore;
    public array $supplyTiles = [];

    public function __construct(array $tiles, int $previousScore) {
        $this->tiles = $tiles;
        $this->previousScore = $previousScore;
    }
}
?>
