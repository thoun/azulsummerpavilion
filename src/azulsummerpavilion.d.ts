interface Tile {
    id: number;
    type: number;
    location: string;
    line: number;
    column: number;
}

interface AzulSummerPavilionPlayer extends Player {
    lines: Tile[];
    wall: Tile[];
    hand: Tile[];
    playerNo: number;
    selectedColumns: SelectedColumn[];
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
}

interface AzulSummerPavilionGame extends Game {
    animationManager: AnimationManager;
    
    getPlayerId(): number;
    isDefaultFont(): boolean;
    getZoom(): number;
    isVariant(): boolean;
    takeTiles(id: number): void;
    playTile(line: number): void;
    selectPlace(line: number, column: number): void;
    removeTile(tile: Tile): void;
    removeTiles(tiles: Tile[]): void;
    placeTile(tile: Tile, destinationId: string, left?: number, top?: number, rotation?: number): Promise<boolean>;
}

interface EnteringChooseTileArgs {
    wildColor: number;
}

interface EnteringChoosePlaceArgs {
    placedTiles: Tile[];
}

interface EnteringChooseColorArgs {
    possibleColors: number[];
}


interface EnteringPlayTileArgs {
    selectedPlace: number[];
    number: number;
    color: number;
    wildColor: number;
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
}

interface NotifFactoriesFilledArgs {
    factories: { [factoryId: number]: Tile[] };
    remainingTiles: number;
}

interface NotifFactoriesChangedArgs extends NotifFactoriesFilledArgs {
    factory: number;
    tiles: Tile[];
}

interface NotifTilesSelectedArgs {
    playerId: number;
    selectedTiles: Tile[];
    discardedTiles: Tile[];
    fromFactory: number;
}

interface UndoSelect {
    from: number;
    tiles: Tile[];
    previousFirstPlayer: number;
    lastRoundBefore: boolean;
}

interface NotifUndoArgs {
    playerId: number;
    undo: UndoSelect;
    factoryTilesBefore: Tile[],
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

interface PlacedTileOnWall {
    placedTile: Tile;
    discardedTiles: Tile[];
    pointsDetail: WallTilePointDetail;
}

interface NotifPlaceTileOnWallArgs {
    completeLines: { [playerId: number]: PlacedTileOnWall };
}

interface FloorLine {
    points: number;
    tiles: Tile[];
}

interface NotifEmptyFloorLineArgs {
    floorLines: { [playerId: number]: FloorLine };
}

interface EndScoreTiles {
    tiles: Tile[];
    points: number;
}

interface NotifEndScoreArgs {
    scores: { [playerId: number]: EndScoreTiles };
}

interface PlacedTile {
    id?: number;
    x: number;
    y: number;
}