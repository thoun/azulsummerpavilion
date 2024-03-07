class ScoringBoard {

    constructor(
        private game: AzulSummerPavilionGame, 
        roundNumber: number,
        supplyTiles: Tile[],
    ) {
        const scoringBoardDiv = document.getElementById('scoring-board');

        let html = `<div id="round-counter">`;
        for (let i=1; i<=6; i++) {            
            html += `<div id="round-space-${i}" class="round-space">${roundNumber == i ? `<div id="round-marker"></div>` : ''}</div>`;
        }
        html += `</div>
        <div id="supply">`;
        for (let i=1; i<=10; i++) {
            html += `<div id="supply-space-${i}" class="supply-space space${i}"></div>`;
        }
        html += `</div>`;

        
        for (let i=1; i<=3; i++) {
            html += `<div id="bonus-info-${i}" class="bonus-info" data-bonus="${i}"></div>`;
        }

        scoringBoardDiv.insertAdjacentHTML('beforeend', html);

        const bonusInfos = [
            _("a pillar"),
            _("a statue"),
            _("a window"),
        ];

        for (let i=1; i<=3; i++) {
            (this.game as any).addTooltipHtml(
                `bonus-info-${i}`, 
                _("When you surround the 4 adjacent spaces of ${a_bonus_shape} with tiles, you must then immediately take any ${number} tile(s) of your choice from the supply.")
                    .replace('${a_bonus_shape}', `<strong>${bonusInfos[i - 1]}</strong>`)
                    .replace('${number}', `<strong>${i}</strong>`)                
            );
        }

        this.placeTiles(supplyTiles, false);
    }

    public placeTiles(tiles: Tile[], animation: boolean) {
        tiles.forEach(tile => {
            if (animation) {
                this.game.placeTile(tile, `bag`, 20, 20, 0);
                slideToObjectAndAttach(this.game, document.getElementById(`tile${tile.id}`), `supply-space-${tile.space}`);
            } else {
                this.game.placeTile(tile, `supply-space-${tile.space}`);
            }
        });
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