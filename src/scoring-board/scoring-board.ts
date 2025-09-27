class ScoringBoard {

    constructor(
        private game: AzulSummerPavilionGame, 
        roundNumber: number,
        supplyTiles: Tile[],
    ) {
        const BONUSES = {
            'pillar': {
                name:  _("a pillar"),
                adjacent: 4,
                number: 1,
            },
            'statue': {
                name:  _("a statue"),
                adjacent: 4,
                number: 2,
            },
            'window': {
                name:  _("a window"),
                adjacent: 2,
                number: 3,
            },
        };
        if (this.game.getBoardNumber() >= 3) {
            BONUSES['fountain'] = {
                name:  _("a fountain"),
                adjacent: 4,
                number: 1,
            };
        }

        const scoringBoardDiv = document.getElementById('scoring-board');

        scoringBoardDiv.dataset.board = ''+game.gamedatas.boardNumber;

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
        
        Object.keys(BONUSES).forEach((from) => 
            html += `<div id="bonus-info-${from}" class="bonus-info" data-from="${from}"></div>`
        )

        scoringBoardDiv.insertAdjacentHTML('beforeend', html);


        Object.entries(BONUSES).forEach(([from, detail]) => 
            (this.game as any).addTooltipHtml(
                `bonus-info-${from}`, 
                _("When you surround the ${adjacent_number} adjacent spaces of ${a_bonus_shape} with tiles, you must then immediately take any ${number} tile(s) of your choice from the supply.")
                    .replace('${adjacent_number}', `${detail.adjacent}`)
                    .replace('${a_bonus_shape}', `<strong>${detail.name}</strong>`)
                    .replace('${number}', `<strong>${detail.number}</strong>`)                
            )
        );

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