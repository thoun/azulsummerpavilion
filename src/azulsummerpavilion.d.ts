interface Tile {
    id: number;
    type: number;
    location: string;
    star: number;
    space: number;
}

interface AzulSummerPavilionPlayer extends Player {
    wall: Tile[];
    hand: Tile[];
    corner: Tile[];
    playerNo: number;
    selectedColumns: SelectedColumn[];
    passed: boolean;
}

/**
 * Your game interfaces
 */

interface AzulSummerPavilionGamedatas {
    current_player_id: string;
    decision: {decision_type: string};
    game_result_neutralized: string;
    gamestate: Gamestate;
    gamestates: { [gamestateId: number]: Gamestate };
    neutralized_player_id: string;
    notifications: {last_packet_id: string, move_nbr: string}
    playerorder: (string | number)[];
    players: { [playerId: number]: AzulSummerPavilionPlayer };
    tablespeed: string;

    // Add here variables you set up in getAllDatas
    factoryNumber: number;
    factories: { [factoryId: number]: Tile[] };
    firstPlayerTokenPlayerId: number;
    variant: boolean;
    endRound: boolean;
    undo: boolean;
    fastScoring: boolean;
    remainingTiles: number;
    round: number; // 1..6
    supply: Tile[];
}

interface AzulSummerPavilionGame extends Game {
    animationManager: AnimationManager;
    
    getPlayerId(): number;
    isDefaultFont(): boolean;
    getZoom(): number;
    isVariant(): boolean;
    getPlayerColor(playerId: number): string;
    takeTiles(id: number): void;
    playTile(line: number): void;
    selectPlace(star: number, space: number): void;
    removeTile(tile: Tile): void;
    removeTiles(tiles: Tile[]): void;
    placeTile(tile: Tile, destinationId: string, left?: number, top?: number, rotation?: number, placeInParent?: (elem, parent) => void): Promise<boolean>;
}

interface EnteringChooseTileArgs {
    wildColor: number;
}

interface EnteringTakeBonusTileArgs {
    count: number;
    highlightedTiles: Tile[];
}

interface EnteringChoosePlaceArgs {
    possibleSpaces: number[];
    skipIsFree: boolean;
}

interface EnteringChooseColorArgs {
    playerId: number;
    possibleColors: number[];
    star: number;
    space: number;
}


interface EnteringPlayTileArgs {
    selectedPlace: number[];
    number: number;
    color: number;
    wildColor: number;
    maxColor: number;
    maxWildTiles: number;
}

interface NextColumnToSelect {
    availableColumns: number[];
    color: number;
    line: number;
}

interface SelectedColumn {
    column: number;
    color: number;
    line: number;

}

interface NotifFirstPlayerTokenArgs {
    playerId: number;
    decScore: number;
}

interface NotifFactoriesFilledArgs {
    factories: { [factoryId: number]: Tile[] };
    remainingTiles: number;
    roundNumber: number;
}

interface NotifFactoriesChangedArgs extends NotifFactoriesFilledArgs {
    factory: number;
    tiles: Tile[];
}

interface NotifTilesSelectedArgs {
    playerId: number;
    selectedTiles: Tile[];
    discardedTiles: Tile[];
    typeWild: number;
    fromFactory: number;
    fromSupply: boolean;
}

interface UndoSelect {
    from: number;
    tiles: Tile[];
    previousFirstPlayer: number;
    previousScore: number;
}

interface UndoPlace {
    tiles: Tile[];    
    supplyTiles: Tile[];
    previousScore: number;
}

interface NotifUndoSelectArgs {
    playerId: number;
    undo?: UndoSelect;
    factoryTilesBefore: Tile[];
}

interface NotifUndoPlaceArgs {
    playerId: number;
    undo?: UndoPlace;
}

interface NotifTilesPlacedOnLineArgs {
    playerId: number;
    line: number;
    placedTiles: Tile[];
    discardedTiles: Tile[];
    fromHand: boolean;
}

interface WallTilePointDetail {
    points: number;
    rowTiles: Tile[];
    columnTiles: Tile[];
}

interface NotifPlaceTileOnWallArgs {
    playerId: number;
    placedTile: Tile;
    discardedTiles: Tile[];
    scoredTiles: Tile[];
}

interface FloorLine {
    points: number;
    tiles: Tile[];
}

interface NotifPutToCornerArgs {
    playerId: number;
    keptTiles: Tile[];
    discardedTiles: Tile[];
}

interface NotifCornerToHandArgs {
    playerId: number;
    tiles: Tile[];
}

interface NotifSupplyFilledArgs {
    newTiles: Tile[];
    remainingTiles: number;
}

interface EndScoreTiles {
    tiles: Tile[];
    points: number;
    star: number;
}

interface NotifEndScoreArgs {
    scores: { [playerId: number]: EndScoreTiles };
}

interface PlacedTile {
    id?: number;
    x: number;
    y: number;
}
