<?php

class Tile {
    public int $id;
    public int $type; // 0 : FP, 1 : black, 2 : cyan, 3 : blue, 4 : yellow, 5 : red
    public string $location; // deck (bag), factory, hand${playerId}, line${playerId}, wall${playerId}, discard
    public int $line; // TODO remove
    public int $column; // TODO remove
    public int $star; // factory : unused, else line
    public int $space; // factory : 0 for center 1-9 for factories, else column

    public function __construct($dbTile) {
        $this->id = intval($dbTile['id']);
        $this->type = intval($dbTile['type']);
        $this->location = $dbTile['location'];
        $locationArg = intval($dbTile['location_arg']);
        $this->line = floor($locationArg / 100);
        $this->column = $locationArg % 100;
        $this->star = floor($locationArg / 100);
        $this->space = $locationArg % 100;
    }
}
?>
