const HAND_CENTER = 327;

const COLORS_WITH_COLOR_BLIND_EXTRA_SIGN = [1, 4, 6];

class PlayerTable {
    public playerId: number;

    constructor(
        private game: AzulSummerPavilionGame, 
        player: AzulSummerPavilionPlayer) {

        this.playerId = Number(player.id);

        const nameClass = player.name.indexOf(' ') !== -1 ? 'with-space' : 'without-space';
        const stars = this.game.getStars();

        let html = `<div id="player-table-wrapper-${this.playerId}" class="player-table-wrapper">
        <div id="player-hand-${this.playerId}" class="player-hand">
        </div>
        <div id="player-table-${this.playerId}" class="player-table" data-board="${this.game.getBoardNumber()}" style="--player-color: #${player.color};">
            <div class="player-name-box">
                <div class="player-name-wrapper shift">
                    <div id="player-name-shift-${this.playerId}" class="player-name color ${game.isDefaultFont() ? 'standard' : 'azul'} ${nameClass}">${player.name}</div>
                </div>
                <div class="player-name-wrapper">
                    <div id="player-name-${this.playerId}" class="player-name dark ${game.isDefaultFont() ? 'standard' : 'azul'} ${nameClass}">${player.name}</div>
                </div>
            </div>
            `;

        for (let corner=0; corner<4; corner++) {
            html += `<div id="player-table-${this.playerId}-corner-${corner}" class="corner corner${corner}"></div>`;
        }
        for (let star=0; star<=6; star++) {
            html += `<div id="player-table-${this.playerId}-star-${star}" class="star star${star}" style=" --rotation: ${(star == 0 ? 3 : star - 4) * -60}deg;">`;
            for (let space=1; space<=6; space++) {
                const spaceColor = stars[star][space].color;
                let cbTileColor = '';
                if (COLORS_WITH_COLOR_BLIND_EXTRA_SIGN.includes(spaceColor)) {
                    cbTileColor = `cb-tile${spaceColor}`;
                }
                const displayedNumber = stars[star][space].number;
                html += `<div id="player-table-${this.playerId}-star-${star}-space-${space}" class="space space${space} ${cbTileColor}" style="--number: '${displayedNumber}'; --rotation: ${240 - space * 60 - (star == 0 ? 3 : star - 4) * 60}deg;"></div>`;
            }
            html += `</div>`;
        }
        html += `</div>`;

        html += `
        </div>`;

        document.getElementById('centered-table').insertAdjacentHTML('beforeend', html);

        this.placeTilesOnHand(player.hand);
        this.placeTilesOnCorner(player.corner);

        for (let star=0; star<=6; star++) {
            for (let space=1; space<=6; space++) {
                document.getElementById(`player-table-${this.playerId}-star-${star}-space-${space}`).addEventListener('click', () => {
                    this.game.selectPlace(star, space);
                });
            }
        }

        this.placeTilesOnWall(player.wall);
    }

    public handCountChanged() {
        const handDiv = document.getElementById(`player-hand-${this.playerId}`);
        const tileCount = handDiv.querySelectorAll('.tile').length;
        handDiv.style.setProperty('--hand-overlap', `-${
            tileCount < 11 ? 0 : (tileCount - 11) * 3.5
        }px`);
    }

    public placeTilesOnHand(tiles: Tile[]) {
        const placeInHand = (tileDiv: HTMLDivElement, handDiv: HTMLDivElement): void => {
            const tileType = Number(tileDiv.dataset.type);
            let newIndex = 0;
            const handTiles = Array.from(handDiv.querySelectorAll('.tile')) as HTMLDivElement[];
            handTiles.forEach((handTileDiv, index) => {
                if (Number(handTileDiv.dataset.type) < tileType) {
                    newIndex = index + 1;
                }
            });
            
            if (newIndex >= handTiles.length) {
                handDiv.appendChild(tileDiv);
            } else {
                handDiv.insertBefore(tileDiv, handDiv.children[newIndex]);
            }
        };

        Promise.all(tiles.map(tile => this.game.placeTile(tile, `player-hand-${this.playerId}`, undefined, undefined, undefined, placeInHand))).then(() => this.handCountChanged());
        this.handCountChanged();
    }

    public placeTilesOnCorner(tiles: Tile[]) {
        tiles.forEach((tile, index) => this.game.placeTile(tile, `player-table-${this.playerId}-corner-${index}`));
        this.handCountChanged();
    }

    public placeTilesOnWall(tiles: Tile[]) {
        tiles.forEach(tile => this.game.placeTile(tile, `player-table-${this.playerId}-star-${tile.star}-space-${tile.space}`));
        this.handCountChanged();
    }
    
    public setFont(prefValue: number): void {
        const defaultFont = prefValue === 1;
        const playerName = document.getElementById(`player-name-${this.playerId}`);
        const playerNameShift = document.getElementById(`player-name-shift-${this.playerId}`);
        playerNameShift.classList.toggle('standard', defaultFont);
        playerNameShift.classList.toggle('azul', !defaultFont);
        playerName.classList.toggle('standard', defaultFont);
        playerName.classList.toggle('azul', !defaultFont);
    }
}