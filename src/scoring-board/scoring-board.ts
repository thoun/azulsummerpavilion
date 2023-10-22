class ScoringBoard {

    constructor(
        private game: AzulSummerPavilionGame, 
        roundNumber: number,
        centerTiles: Tile[],
    ) {
        const scoringBoardDiv = document.getElementById('scoring-board');

        let html = `<div id="round-counter">`;
        for (let i=1; i<=6; i++) {            
            html += `<div id="round-space-${i}" class="round-space">${roundNumber == i ? `<div id="round-marker"></div>` : ''}</div>`;
        }
        html += `</div>
        <div class="supply">`;
        for (let i=1; i<=10; i++) {            
            html += `<div id="supply-space-${i}" class="supply-space space${i}"></div>`;
        }
        html += `</div>`;

        scoringBoardDiv.insertAdjacentHTML('beforeend', html);

        this.placeTiles(centerTiles);
    }

    public placeTiles(tiles: Tile[]) {
        tiles.forEach(tile => this.game.placeTile(tile, `supply-space-${tile.space}`));
    }
    
    public setRoundNumber(roundNumber: number) {
        this.game.animationManager.attachWithAnimation(
            new BgaSlideAnimation({
                element: document.getElementById(`round-marker`)
            }),
            document.getElementById(`round-space-${roundNumber}`),
        )
    }
}