const HAND_CENTER = 327;

class PlayerTable {
    public playerId: number;

    constructor(
        private game: AzulSummerPavilionGame, 
        player: AzulSummerPavilionPlayer) {

        this.playerId = Number(player.id);

        const nameClass = player.name.indexOf(' ') !== -1 ? 'with-space' : 'without-space';

        let html = `<div id="player-table-wrapper-${this.playerId}" class="player-table-wrapper">
        <div id="player-hand-${this.playerId}" class="player-hand">
        </div>
        <div id="player-table-${this.playerId}" class="player-table ${this.game.isVariant() ? 'variant' : ''}" style="--player-color: #${player.color};">
            <div class="player-name-wrapper shift">
                <div id="player-name-shift-${this.playerId}" class="player-name color ${game.isDefaultFont() ? 'standard' : 'azul'} ${nameClass}">${player.name}</div>
            </div>
            <div class="player-name-wrapper">
                <div id="player-name-${this.playerId}" class="player-name dark ${game.isDefaultFont() ? 'standard' : 'azul'} ${nameClass}">${player.name}</div>
            </div>
            `;
            
        html += `<div id="player-table-${this.playerId}-line0" class="floor line"></div>`;
        html += `<div id="player-table-${this.playerId}-wall" class="wall">`;

        for (let line=1; line<=5; line++) {
            for (let column=1; column<=5; column++) {
                html += `<div id="player-table-${this.playerId}-wall-spot-${line}-${column}" class="wall-spot" style="left: ${69*(column-1) - 1}px; top: ${70*(line-1) - 1}px;"></div>`;
            }
        }
        html += `</div>
        <div id="player-table-${this.playerId}-column0" class="floor wall-spot"></div>`;
        
        html += `
            <div class="score-magnified row">2</div>
            <div class="score-magnified column">7</div>
            <div class="score-magnified color">10</div>
        `;

        html += `   
            </div>
        </div>`;

        dojo.place(html, 'centered-table');

        this.placeTilesOnHand(player.hand);

        for (let line=1; line<=5; line++) {
            for (let column=1; column<=5; column++) {
                document.getElementById(`player-table-${this.playerId}-wall-spot-${line}-${column}`).addEventListener('click', () => {
                    this.game.selectPlace(line, column);
                });
            }
        }
        document.getElementById(`player-table-${this.playerId}-column0`).addEventListener('click', () => this.game.selectPlace(0, 0));

        for (let i=-1; i<=5; i++) {
            const tiles = player.lines.filter(tile => tile.line === i);
            this.placeTilesOnLine(tiles, i);
        }

        this.placeTilesOnWall(player.wall);
    }

    public placeTilesOnHand(tiles: Tile[]) {
        const startX = HAND_CENTER - tiles.length * (HALF_TILE_SIZE + 5);
        tiles.forEach((tile, index) => this.game.placeTile(tile, `player-hand-${this.playerId}`, startX + (tiles.length - index) * (HALF_TILE_SIZE + 5) * 2, 5));
    }

    public placeTilesOnLine(tiles: Tile[], line: number): Promise<any> {
        return Promise.all(tiles.map(tile => {
            const left = line == -1 ? 9 : (line > 0 ? (line - tile.column) * 69 : 5 + (tile.column-1) * 74);
            const top = line == -1 ? 9 : 0;
            return Promise.resolve(true); // this.game.placeTile(tile, `player-table-${this.playerId}-line${line}`, left, top); // TODO no lines anymore
        }));
    }

    public placeTilesOnWall(tiles: Tile[]) {
        tiles.forEach(tile => this.game.placeTile(tile, `player-table-${this.playerId}-wall-spot-${tile.line}-${tile.column}`));
    }
    
    public setFont(prefValue: number): void {
        const defaultFont = prefValue === 1;
        dojo.toggleClass(`player-name-shift-${this.playerId}`, 'standard', defaultFont);
        dojo.toggleClass(`player-name-shift-${this.playerId}`, 'azul', !defaultFont);
        dojo.toggleClass(`player-name-${this.playerId}`, 'standard', defaultFont);
        dojo.toggleClass(`player-name-${this.playerId}`, 'azul', !defaultFont);
    }
}