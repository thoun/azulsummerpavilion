<?php

class UndoSelect {
    public int $from;
    public array/*Tile[]*/ $tiles;
    public int $normalTiles;
    public bool $wildTile;
    public int $pointsLossFirstTile;
    public int $previousFirstPlayer;
    public int $previousScore;

    public function __construct(array $tiles, int $normalTiles, bool $wildTile, int $pointsLossFirstTile, int $from, int $previousFirstPlayer, int $previousScore) {
        $this->from = $from;
        $this->tiles = $tiles;
        $this->normalTiles = $normalTiles;
        $this->wildTile = $wildTile;
        $this->pointsLossFirstTile = $pointsLossFirstTile;
        $this->previousFirstPlayer = $previousFirstPlayer;
        $this->previousScore = $previousScore;
    }
}

class UndoPlace {
    public array/*Tile[]*/ $tiles;
    public int $previousScore;
    public array $supplyTiles = [];
    public int $points;

    public function __construct(array $tiles, int $previousScore, int $points) {
        $this->tiles = $tiles;
        $this->previousScore = $previousScore;
        $this->points = $points;
    }
}
?>
