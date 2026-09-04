const ANIMATION_MS = 500;
const SCORE_MS = 1500;
const SLOW_SCORE_MS = 2000;

function slideToObjectAndAttach(game, object, destinationId, posX, posY, rotation = 0, placeInParent) {
    const destination = document.getElementById(destinationId);
    if (destination.contains(object)) {
        return Promise.resolve(true);
    }
    return new Promise(resolve => {
        const originalZIndex = Number(object.style.zIndex);
        object.style.zIndex = '10';
        const objectCR = object.getBoundingClientRect();
        const destinationCR = destination.getBoundingClientRect();
        const deltaX = destinationCR.left - objectCR.left + (posX ?? 0) * game.getZoom();
        const deltaY = destinationCR.top - objectCR.top + (posY ?? 0) * game.getZoom();
        const attachToNewParent = () => {
            object.style.top = posY !== undefined ? `${posY}px` : 'unset';
            object.style.left = posX !== undefined ? `${posX}px` : 'unset';
            object.style.position = (posX !== undefined || posY !== undefined) ? 'absolute' : 'unset';
            object.style.zIndex = originalZIndex ? '' + originalZIndex : 'unset';
            object.style.transform = '';
            object.style.setProperty('--rotation', `${rotation ?? 0}deg`);
            object.style.transition = null;
            if (placeInParent) {
                placeInParent(object, destination);
            }
            else {
                destination.appendChild(object);
            }
        };
        if (document.visibilityState === 'hidden' || !game.bga.gameui.bgaAnimationsActive()) {
            // if tab is not visible, we skip animation (else they could be delayed or cancelled by browser)
            attachToNewParent();
        }
        else {
            object.style.transition = `transform 0.5s ease-in`;
            object.style.setProperty('--rotation', `${rotation ?? 0}deg`);
            object.style.transform = `translate(${deltaX / game.getZoom()}px, ${deltaY / game.getZoom()}px) rotate(calc(45deg + var(--rotation))) skew(15deg, 15deg)`;
            let securityTimeoutId = null;
            const transitionend = () => {
                attachToNewParent();
                object.removeEventListener('transitionend', transitionend);
                object.removeEventListener('transitioncancel', transitionend);
                resolve(true);
                if (securityTimeoutId) {
                    clearTimeout(securityTimeoutId);
                }
            };
            object.addEventListener('transitionend', transitionend);
            object.addEventListener('transitioncancel', transitionend);
            // security check : if transition fails, we force tile to destination
            securityTimeoutId = setTimeout(() => {
                if (!destination.contains(object)) {
                    attachToNewParent();
                    object.removeEventListener('transitionend', transitionend);
                    object.removeEventListener('transitioncancel', transitionend);
                    resolve(true);
                }
            }, 700);
        }
    });
}

const FACTORY_RADIUS = 125;
const HALF_TILE_SIZE = 29;
const CENTER_FACTORY_TILE_SHIFT = 12;
class Factories {
    constructor(game, factoryNumber, factories, remainingTiles) {
        this.game = game;
        this.factoryNumber = factoryNumber;
        this.tilesPositionsInCenter = [[], [], [], [], [], [], []]; // color, tiles
        this.tilesInFactories = []; // factory, color, tiles
        const factoriesDiv = document.getElementById('factories');
        const radius = 175 + factoryNumber * 25;
        const halfSize = radius + FACTORY_RADIUS;
        const size = `${halfSize * 2}px`;
        factoriesDiv.style.width = size;
        factoriesDiv.style.height = size;
        const bagDiv = document.getElementById('bag');
        this.bagCounter = new ebg.counter();
        this.bagCounter.create('bag-counter');
        bagDiv.addEventListener('click', () => document.getElementById('bag-counter').classList.toggle('visible'));
        let html = `<div>`;
        html += `<div id="factory0" class="factory-center"></div>`;
        for (let i = 1; i <= factoryNumber; i++) {
            const angle = (i - 1) * Math.PI * 2 / factoryNumber; // in radians
            const left = radius * Math.sin(angle);
            const top = radius * Math.cos(angle);
            html += `<div id="factory${i}" class="factory" style="left: ${halfSize - FACTORY_RADIUS + left}px; top: ${halfSize - FACTORY_RADIUS - top}px;"></div>`;
        }
        html += `</div>`;
        factoriesDiv.insertAdjacentHTML('beforeend', html);
        this.fillFactories(factories, false);
        this.setRemainingTiles(remainingTiles);
    }
    getWidth() {
        const radius = 175 + this.factoryNumber * 25;
        const halfSize = radius + FACTORY_RADIUS;
        return halfSize * 2;
    }
    centerColorRemoved(selectedTiles) {
        selectedTiles.forEach(tile => {
            this.tilesInFactories[0][tile.type] = this.tilesInFactories[0][tile.type].filter(t => t.id != tile.id);
            this.tilesPositionsInCenter[tile.type] = this.tilesPositionsInCenter[tile.type].filter(t => t.id != tile.id);
        });
        this.updateDiscardedTilesNumbers();
    }
    factoryTilesRemoved(factory) {
        this.tilesInFactories[factory] = [[], [], [], [], [], [], []];
    }
    getCoordinatesInFactory(tileIndex, tileNumber) {
        const angle = tileIndex * Math.PI * 2 / tileNumber - Math.PI / 4; // in radians
        return {
            left: 125 + 70 * Math.sin(angle) - HALF_TILE_SIZE,
            top: 125 + 70 * Math.cos(angle) - HALF_TILE_SIZE,
        };
        /*return {
            left: 50 + Math.floor(tileIndex / 2) * 90,
            top: 50 + Math.floor(tileIndex % 2) * 90,
        };*/
    }
    getCoordinatesForTile0() {
        const centerFactoryDiv = document.getElementById('factory0');
        return {
            left: centerFactoryDiv.clientWidth / 2 - HALF_TILE_SIZE,
            top: centerFactoryDiv.clientHeight / 2,
        };
    }
    fillFactories(factories, animation = true) {
        let tileIndex = 0;
        for (let factoryIndex = 0; factoryIndex <= this.factoryNumber; factoryIndex++) {
            this.tilesInFactories[factoryIndex] = [[], [], [], [], [], [], []]; // color, tiles
            const factoryTiles = factories[factoryIndex];
            factoryTiles.forEach((tile, index) => {
                let left = null;
                let top = null;
                if (factoryIndex > 0) {
                    const coordinates = this.getCoordinatesInFactory(index, factoryTiles.length);
                    left = coordinates.left;
                    top = coordinates.top;
                }
                else {
                    if (tile.type == 0) {
                        const coordinates = this.getCoordinatesForTile0();
                        left = coordinates.left;
                        top = coordinates.top;
                    }
                    else {
                        const coords = this.getFreePlaceForFactoryCenter(tile.type);
                        left = coords.left;
                        top = coords.top;
                        this.tilesPositionsInCenter[tile.type].push({ id: tile.id, x: left, y: top });
                    }
                }
                this.tilesInFactories[factoryIndex][tile.type].push(tile);
                if (tile.type == 0) {
                    this.game.placeTile(tile, `factory${factoryIndex}`, left, top);
                }
                else {
                    const delay = animation ? tileIndex * 80 : 0;
                    setTimeout(() => {
                        this.game.placeTile(tile, `bag`, 20, 20, 0);
                        slideToObjectAndAttach(this.game, document.getElementById(`tile${tile.id}`), `factory${factoryIndex}`, left, top, Math.round(Math.random() * 90 - 45));
                    }, delay);
                    tileIndex++;
                }
            });
        }
        this.updateDiscardedTilesNumbers();
    }
    factoriesChanged(args) {
        const factoryTiles = args.factories[args.factory];
        args.tiles.forEach(newTile => {
            const index = factoryTiles.findIndex(tile => tile.id == newTile.id);
            const coordinates = this.getCoordinatesInFactory(index, factoryTiles.length);
            const left = coordinates.left;
            const top = coordinates.top;
            slideToObjectAndAttach(this.game, document.getElementById(`tile${newTile.id}`), `factory${args.factory}`, left, top, Math.round(Math.random() * 90 - 45));
            this.updateTilesInFactories(args.tiles, args.factory);
        });
        factoryTiles.forEach((tile, index) => {
            const coordinates = this.getCoordinatesInFactory(index, factoryTiles.length);
            const left = coordinates.left;
            const top = coordinates.top;
            const tileDiv = document.getElementById(`tile${tile.id}`);
            tileDiv.style.left = `${left}px`;
            tileDiv.style.top = `${top}px`;
        });
    }
    factoriesCompleted(args) {
        const factoryTiles = args.factories[args.factory];
        factoryTiles.forEach((tile, index) => {
            const coordinates = this.getCoordinatesInFactory(index, factoryTiles.length);
            const left = coordinates.left;
            const top = coordinates.top;
            const tileDiv = document.getElementById(`tile${tile.id}`);
            if (tileDiv) {
                tileDiv.style.left = `${left}px`;
                tileDiv.style.top = `${top}px`;
            }
            else {
                const rotation = Math.round(Math.random() * 90 - 45);
                this.game.placeTile(tile, `factory${args.factory}`, left, top, rotation);
                this.game.animationManager.slideIn(document.getElementById(`tile${tile.id}`), document.getElementById('bag'));
            }
        });
        this.updateTilesInFactories(factoryTiles, args.factory);
    }
    updateTilesInFactories(tiles, factory) {
        tiles.forEach(tile => {
            let oldFactory = this.tilesInFactories.findIndex(f => f[tile.type].some(t => t.id == tile.id));
            if (oldFactory != factory) {
                this.tilesInFactories[factory][tile.type].push(tile);
                if (oldFactory !== -1) {
                    const oldIndex = this.tilesInFactories[oldFactory][tile.type].findIndex(t => t.id == tile.id);
                    if (oldIndex !== -1) {
                        this.tilesInFactories[oldFactory][tile.type].splice(oldIndex, 1);
                    }
                }
            }
        });
    }
    discardTiles(discardedTiles) {
        const promise = discardedTiles.map(tile => {
            const { left, top } = this.getFreePlaceForFactoryCenter(tile.type);
            this.tilesInFactories[0][tile.type].push(tile);
            this.tilesPositionsInCenter[tile.type].push({ id: tile.id, x: left, y: top });
            const tileDiv = document.getElementById(`tile${tile.id}`);
            const rotation = tileDiv ? Number(tileDiv.dataset.rotation || 0) : 0;
            return this.game.placeTile(tile, 'factory0', left, top, rotation + Math.round(Math.random() * 20 - 10));
        });
        setTimeout(() => this.updateDiscardedTilesNumbers(), ANIMATION_MS);
        return promise;
    }
    getDistance(p1, p2) {
        return Math.sqrt((p1.x - p2.x) ** 2 + (p1.y - p2.y) ** 2);
    }
    setRandomCoordinates(newPlace, xCenter, yCenter, radius, color) {
        const angle = (0.3 + color / 5 + Math.random() / 4) * Math.PI * 2;
        const distance = Math.random() * radius;
        newPlace.x = xCenter - HALF_TILE_SIZE - distance * Math.sin(angle);
        newPlace.y = yCenter - distance * Math.cos(angle);
    }
    getMinDistance(placedTiles, newPlace) {
        if (!placedTiles.length) {
            return 999;
        }
        const distances = placedTiles.map(place => this.getDistance(newPlace, place));
        if (distances.length == 1) {
            return distances[0];
        }
        return distances.reduce((a, b) => a < b ? a : b);
    }
    getFreePlaceCoordinatesForFactoryCenter(placedTiles, xCenter, yCenter, color) {
        const radius = 175 + this.factoryNumber * 25 - 165;
        let place = { x: 0, y: HALF_TILE_SIZE };
        this.setRandomCoordinates(place, xCenter, yCenter, radius, color);
        let minDistance = this.getMinDistance(placedTiles, place);
        let protection = 0;
        while (protection < 1000 && minDistance < HALF_TILE_SIZE * 2) {
            const newPlace = { x: 0, y: HALF_TILE_SIZE };
            this.setRandomCoordinates(newPlace, xCenter, yCenter, radius, color);
            const newMinDistance = this.getMinDistance(placedTiles, newPlace);
            if (newMinDistance > minDistance) {
                place = newPlace;
                minDistance = newMinDistance;
            }
            protection++;
        }
        return place;
    }
    getFreePlaceForFactoryCenter(color) {
        const div = document.getElementById('factory0');
        const xCenter = div.clientWidth / 2;
        const yCenter = div.clientHeight / 2;
        const placed = div.dataset.placed ? JSON.parse(div.dataset.placed) : [{
                x: xCenter - HALF_TILE_SIZE,
                y: yCenter,
            }];
        const newPlace = this.getFreePlaceCoordinatesForFactoryCenter(placed, xCenter, yCenter, color);
        placed.push(newPlace);
        div.dataset.placed = JSON.stringify(placed);
        return {
            left: newPlace.x,
            top: newPlace.y,
        };
    }
    updateDiscardedTilesNumbers() {
        document.querySelectorAll('.tile-count').forEach(tc => tc?.remove());
        for (let type = 1; type <= 6; type++) {
            const number = this.tilesPositionsInCenter[type].length;
            if (!number) {
                continue;
            }
            const x = this.tilesPositionsInCenter[type].reduce((sum, place) => sum + place.x, 0) / number + 14;
            const y = this.tilesPositionsInCenter[type].reduce((sum, place) => sum + place.y, 0) / number + 14;
            document.getElementById('factories').insertAdjacentHTML('beforeend', `
            <div id="tileCount${type}" class="tile-count tile${type}" style="left: ${x}px; top: ${y}px;">${number}</div>
            `);
            const newNumberDiv = document.getElementById(`tileCount${type}`);
            const firstTileId = this.tilesInFactories[0][type][0].id;
            newNumberDiv.addEventListener('click', () => this.game.takeTiles(firstTileId));
            newNumberDiv.addEventListener('mouseenter', () => this.tileMouseEnter(firstTileId));
            newNumberDiv.addEventListener('mouseleave', () => this.tileMouseLeave(firstTileId));
        }
    }
    getTilesOfPossibleSelection(id) {
        const selectionTiles = [];
        for (const tilesInFactory of this.tilesInFactories) {
            for (const colorTilesInFactory of tilesInFactory) {
                if (colorTilesInFactory.some(tile => tile.id === id)) {
                    const isWild = colorTilesInFactory[0].type == this.wildColor;
                    if (isWild) {
                        if (!tilesInFactory.some(aColorTilesInFactory => aColorTilesInFactory.length && ![0, this.wildColor].includes(aColorTilesInFactory[0].type))) {
                            selectionTiles.push(tilesInFactory[this.wildColor][0]);
                        }
                    }
                    else {
                        selectionTiles.push(...colorTilesInFactory);
                        if (tilesInFactory[this.wildColor].length) {
                            selectionTiles.push(tilesInFactory[this.wildColor][0]);
                        }
                    }
                    if (tilesInFactory[0].length) {
                        selectionTiles.push(tilesInFactory[0][0]);
                    }
                }
            }
        }
        return selectionTiles;
    }
    tileMouseEnter(id) {
        const tiles = this.getTilesOfPossibleSelection(id);
        if (tiles?.length && this.tilesInFactories[0].some(tilesOfColor => tilesOfColor.some(tile => tile.id == id))) {
            document.getElementById(`tileCount${tiles[0].type}`)?.classList.add('hover');
        }
        tiles?.forEach(tile => {
            document.getElementById(`tile${tile.id}`).classList.add('hover');
        });
    }
    tileMouseLeave(id) {
        const tiles = this.getTilesOfPossibleSelection(id);
        if (tiles?.length) {
            document.getElementById(`tileCount${tiles[0].type}`)?.classList.remove('hover');
        }
        tiles?.forEach(tile => {
            document.getElementById(`tile${tile.id}`).classList.remove('hover');
        });
    }
    undoTakeTiles(tiles, from, factoryTilesBefore) {
        let promise;
        if (from > 0) {
            const countBefore = factoryTilesBefore?.length ?? 0;
            const count = countBefore + tiles.length;
            if (factoryTilesBefore?.length) {
                factoryTilesBefore.forEach((tile, index) => {
                    const coordinates = this.getCoordinatesInFactory(index, count);
                    const left = coordinates.left;
                    const top = coordinates.top;
                    const tileDiv = document.getElementById(`tile${tile.id}`);
                    tileDiv.style.left = `${left}px`;
                    tileDiv.style.top = `${top}px`;
                });
            }
            promise = Promise.all(tiles.map((tile, index) => {
                const coordinates = this.getCoordinatesInFactory(countBefore + index, count);
                this.tilesInFactories[from][tile.type].push(tile);
                const centerIndex = this.tilesInFactories[0][tile.type].findIndex(t => tile.id == t.id);
                if (centerIndex !== -1) {
                    this.tilesInFactories[0][tile.type].splice(centerIndex, 1);
                }
                const centerCoordIndex = this.tilesPositionsInCenter[tile.type].findIndex(t => tile.id == t.id);
                if (centerCoordIndex !== -1) {
                    this.tilesPositionsInCenter[tile.type].splice(centerCoordIndex, 1);
                }
                return this.game.placeTile(tile, `factory${from}`, coordinates.left, coordinates.top, Math.round(Math.random() * 90 - 45));
            }));
        }
        else {
            const promises = this.discardTiles(tiles.filter(tile => tile.type > 0));
            const tile0 = tiles.find(tile => tile.type == 0);
            if (tile0) {
                const coordinates = this.getCoordinatesForTile0();
                promises.push(this.game.placeTile(tile0, `factory0`, coordinates.left, coordinates.top));
            }
            promise = Promise.all(promises);
        }
        setTimeout(() => this.updateDiscardedTilesNumbers(), ANIMATION_MS);
        return promise;
    }
    setRemainingTiles(remainingTiles) {
        this.bagCounter.setValue(remainingTiles);
    }
    displayScoringCenter(playerId, points) {
        this.game.animationManager.displayScoring(document.getElementById('factory0'), points, this.game.getPlayerColor(playerId), { duration: SCORE_MS });
    }
}

const BgaAnimations = await globalThis.importEsmLib('bga-animations', '1.x');
const BgaZoom = await globalThis.importEsmLib('bga-zoom', '1.x');

const HAND_CENTER = 327;
const COLORS_WITH_COLOR_BLIND_EXTRA_SIGN = [1, 4, 6];
class PlayerTable {
    constructor(game, player) {
        this.game = game;
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
        for (let corner = 0; corner < 4; corner++) {
            html += `<div id="player-table-${this.playerId}-corner-${corner}" class="corner corner${corner}"></div>`;
        }
        for (let star = 0; star <= 6; star++) {
            html += `<div id="player-table-${this.playerId}-star-${star}" class="star star${star}" style=" --rotation: ${(star == 0 ? 3 : star - 4) * -60}deg;">`;
            for (let space = 1; space <= 6; space++) {
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
        for (let star = 0; star <= 6; star++) {
            for (let space = 1; space <= 6; space++) {
                document.getElementById(`player-table-${this.playerId}-star-${star}-space-${space}`).addEventListener('click', () => {
                    this.game.selectPlace(star, space);
                });
            }
        }
        this.placeTilesOnWall(player.wall);
    }
    handCountChanged() {
        const handDiv = document.getElementById(`player-hand-${this.playerId}`);
        const tileCount = handDiv.querySelectorAll('.tile').length;
        handDiv.style.setProperty('--hand-overlap', `-${tileCount < 11 ? 0 : (tileCount - 11) * 3.5}px`);
    }
    placeTilesOnHand(tiles) {
        const placeInHand = (tileDiv, handDiv) => {
            const tileType = Number(tileDiv.dataset.type);
            let newIndex = 0;
            const handTiles = Array.from(handDiv.querySelectorAll('.tile'));
            handTiles.forEach((handTileDiv, index) => {
                if (Number(handTileDiv.dataset.type) < tileType) {
                    newIndex = index + 1;
                }
            });
            if (newIndex >= handTiles.length) {
                handDiv.appendChild(tileDiv);
            }
            else {
                handDiv.insertBefore(tileDiv, handDiv.children[newIndex]);
            }
        };
        Promise.all(tiles.map(tile => this.game.placeTile(tile, `player-hand-${this.playerId}`, undefined, undefined, undefined, placeInHand))).then(() => this.handCountChanged());
        this.handCountChanged();
    }
    placeTilesOnCorner(tiles) {
        tiles.forEach((tile, index) => this.game.placeTile(tile, `player-table-${this.playerId}-corner-${index}`));
        this.handCountChanged();
    }
    placeTilesOnWall(tiles) {
        tiles.forEach(tile => this.game.placeTile(tile, `player-table-${this.playerId}-star-${tile.star}-space-${tile.space}`));
        this.handCountChanged();
    }
    setFont(prefValue) {
        const defaultFont = prefValue === 1;
        const playerName = document.getElementById(`player-name-${this.playerId}`);
        const playerNameShift = document.getElementById(`player-name-shift-${this.playerId}`);
        playerNameShift.classList.toggle('standard', defaultFont);
        playerNameShift.classList.toggle('azul', !defaultFont);
        playerName.classList.toggle('standard', defaultFont);
        playerName.classList.toggle('azul', !defaultFont);
    }
}

class ScoringBoard {
    constructor(game, roundNumber, supplyTiles) {
        this.game = game;
        const BONUSES = {
            'pillar': {
                name: _("a pillar"),
                adjacent: 4,
                number: 1,
            },
            'statue': {
                name: _("a statue"),
                adjacent: 4,
                number: 2,
            },
            'window': {
                name: _("a window"),
                adjacent: 2,
                number: 3,
            },
        };
        if (this.game.getBoardNumber() >= 3) {
            BONUSES['fountain'] = {
                name: _("a fountain"),
                adjacent: 4,
                number: 1,
            };
        }
        const scoringBoardDiv = document.getElementById('scoring-board');
        scoringBoardDiv.dataset.board = '' + game.gamedatas.boardNumber;
        let html = `<div id="round-counter">`;
        for (let i = 1; i <= 6; i++) {
            html += `<div id="round-space-${i}" class="round-space">${roundNumber == i ? `<div id="round-marker"></div>` : ''}</div>`;
        }
        html += `</div>
        <div id="supply">`;
        for (let i = 1; i <= 10; i++) {
            html += `<div id="supply-space-${i}" class="supply-space space${i}"></div>`;
        }
        html += `</div>`;
        Object.keys(BONUSES).forEach((from) => html += `<div id="bonus-info-${from}" class="bonus-info" data-from="${from}"></div>`);
        scoringBoardDiv.insertAdjacentHTML('beforeend', html);
        Object.entries(BONUSES).forEach(([from, detail]) => this.game.bga.gameui.addTooltipHtml(`bonus-info-${from}`, _("When you surround the ${adjacent_number} adjacent spaces of ${a_bonus_shape} with tiles, you must then immediately take any ${number} tile(s) of your choice from the supply.")
            .replace('${adjacent_number}', `${detail.adjacent}`)
            .replace('${a_bonus_shape}', `<strong>${detail.name}</strong>`)
            .replace('${number}', `<strong>${detail.number}</strong>`)));
        this.placeTiles(supplyTiles, false);
    }
    placeTiles(tiles, animation) {
        tiles.forEach(tile => {
            if (animation) {
                this.game.placeTile(tile, `bag`, 20, 20, 0);
                slideToObjectAndAttach(this.game, document.getElementById(`tile${tile.id}`), `supply-space-${tile.space}`);
            }
            else {
                this.game.placeTile(tile, `supply-space-${tile.space}`);
            }
        });
    }
    setRoundNumber(roundNumber) {
        this.game.animationManager.slideAndAttach(document.getElementById('round-marker'), document.getElementById(`round-space-${roundNumber}`));
    }
}

const REFILL_DELAY = [];
REFILL_DELAY[5] = 1600;
REFILL_DELAY[7] = 2200;
REFILL_DELAY[9] = 2900;
const ZOOM_LEVELS = [0.25, 0.375, 0.5, 0.625, 0.75, 0.875, 1];
const LOCAL_STORAGE_ZOOM_KEY = 'AzulSummerPavilion-zoom';
const isDebug = window.location.host == 'studio.boardgamearena.com';
const log = isDebug ? console.log.bind(window.console) : function () { };
class Game {
    constructor(bga) {
        this.playersTables = [];
        this.zoom = 0.75;
        this.bga = bga;
        const zoomStr = localStorage.getItem(LOCAL_STORAGE_ZOOM_KEY);
        if (zoomStr) {
            this.zoom = Number(zoomStr);
        }
    }
    /*
        setup:

        This method must set up the game user interface according to current game situation specified
        in parameters.

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */
    setup(gamedatas) {
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="table">
                <div id="centered-table">
                    <div id="factories-and-scoring-board">
                        <div id="factories">
                            <div id="bag">
                                <span id="bag-counter"></span>
                            </div>
                        </div>
                        <div id="scoring-board"></div>
                    </div>
                </div>
            </div>
        `);
        log("Starting game setup");
        this.gamedatas = gamedatas;
        log('gamedatas', gamedatas);
        // ignore loading of some pictures
        [1, 2, 3, 4].filter(boardNumber => boardNumber != this.getBoardNumber()).forEach(boardNumber => this.bga.images.dontPreloadImage(`playerboard${boardNumber}.jpg`));
        this.animationManager = new BgaAnimations.Manager({
            animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
        });
        this.createPlayerPanels(gamedatas);
        this.factories = new Factories(this, gamedatas.factoryNumber, gamedatas.factories, gamedatas.remainingTiles);
        this.scoringBoard = new ScoringBoard(this, gamedatas.round, gamedatas.supply);
        this.createPlayerTables(gamedatas);
        // before set
        this.zoomManager = new BgaZoom.Manager({
            element: document.getElementById('table'),
            smooth: false,
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
            zoomLevels: ZOOM_LEVELS,
            autoZoom: {
                expectedWidth: this.factories.getWidth(),
            },
            onDimensionsChange: (newZoom) => this.onTableCenterSizeChange(newZoom),
        });
        this.setupNotifications();
        this.setupPreferences();
        if (gamedatas.endRound) {
            this.notif_lastRound();
        }
        if (!['chooseTile', 'confirmAcquire'].includes(this.gamedatas.gamestate.name)) {
            document.getElementById('factories-and-scoring-board').classList.add('play');
        }
        document.getElementById(`page-title`).insertAdjacentHTML('beforeend', `
            <div id="summary">
                <div class="round-zone">${_('Round')} <span id="round">${this.gamedatas.round}</span>/6</div>
                <div class="wild-zone">${_('Wild color:')} <div class="wild-container"><div id="wildToken" class="tile tile${this.getSpecialTile(this.gamedatas.round)}"></div></div></div>
            </div>    
        `);
        log("Ending game setup");
    }
    getSpecialTile(roundNumber) {
        return this.gamedatas.wildColors[roundNumber];
    }
    ///////////////////////////////////////////////////
    //// Game & client states
    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState(stateName, args) {
        log('Entering state: ' + stateName, args.args);
        switch (stateName) {
            case 'chooseTile':
                this.onEnteringChooseTile(args.args);
                break;
            case 'choosePlace':
                this.onEnteringChoosePlace(args.args);
                break;
            case 'chooseColor':
                this.onEnteringChooseColor(args.args);
                break;
            case 'playTile':
                this.onEnteringPlayTile(args.args);
                break;
            case 'chooseKeptTiles':
                this.onEnteringChooseKeptTiles(args.args);
                break;
            case 'takeBonusTiles':
                this.onEnteringTakeBonusTiles(args.args);
                break;
            case 'gameEnd':
                const lastTurnBar = document.getElementById('last-round');
                if (lastTurnBar) {
                    lastTurnBar.style.display = 'none';
                }
                break;
        }
        const autopassParams = args.args?._private;
        if (autopassParams?.canSetAutopass && !this.bga.players.isCurrentPlayerActive()) {
            this.addAutopassToggle(autopassParams.autopass);
        }
        else {
            this.removeAutopassToggle();
        }
    }
    onEnteringChooseTile(args) {
        if (this.bga.players.isCurrentPlayerActive()) {
            this.factories.wildColor = args.wildColor;
            document.getElementById('factories').classList.add('selectable');
        }
    }
    onEnteringChoosePlace(args) {
        document.getElementById('factories-and-scoring-board').classList.add('play');
        if (this.bga.players.isCurrentPlayerActive()) {
            const playerId = this.getPlayerId();
            for (let star = 0; star <= 6; star++) {
                for (let space = 1; space <= 6; space++) {
                    document.getElementById(`player-table-${playerId}-star-${star}-space-${space}`).classList.toggle('selectable', args?.possibleSpaces.includes(star * 100 + space));
                }
            }
        }
    }
    onEnteringChooseColor(args) {
        if (this.bga.players.isCurrentPlayerActive()) {
            document.getElementById(`player-table-${args.playerId}-star-${args.star}-space-${args.space}`).classList.add('selected');
        }
    }
    /*private removeGhostTile() {
        document.querySelector('.tile.ghost')?.remove();
    }*/
    onEnteringPlayTile(args) {
        if (this.bga.players.isCurrentPlayerActive()) {
            /*this.removeGhostTile();

            const spotId = `player-table-${this.getPlayerId()}-star-${args.selectedPlace[0]}-space-${args.selectedPlace[1]}`;
            const ghostTileId = `${spotId}-ghost-tile`;
            document.getElementById(spotId).insertAdjacentHTML('beforeend', `<div id="${ghostTileId}" class="tile tile${args.color} ghost"></div>`);*/
        }
    }
    onEnteringChooseKeptTiles(args) {
        if (this.bga.players.isCurrentPlayerActive()) {
            document.getElementById(`player-hand-${this.getPlayerId()}`).classList.add('selectable');
        }
    }
    onEnteringTakeBonusTiles(args) {
        args.highlightedTiles.forEach(tile => document.getElementById(`tile${tile.id}`).classList.add('bonus'));
        args.from.forEach(from => document.getElementById(`bonus-info-${from}`).classList.add('active'));
        if (this.bga.players.isCurrentPlayerActive()) {
            document.getElementById(`supply`).classList.add('selectable');
        }
    }
    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState(stateName) {
        log('Leaving state: ' + stateName);
        switch (stateName) {
            case 'chooseTile':
                this.onLeavingChooseTile();
                break;
            case 'choosePlace':
                this.onLeavingChoosePlace();
                break;
            case 'chooseColor':
                this.onLeavingChooseColor();
                break;
            case 'playTile':
                this.onLeavingPlayTile();
                break;
            case 'chooseKeptTiles':
                this.onLeavingChooseKeptTiles();
                break;
            case 'takeBonusTiles':
                this.onLeavingTakeBonusTiles();
                break;
        }
    }
    onLeavingChooseTile() {
        document.getElementById('factories').classList.remove('selectable');
    }
    onLeavingChoosePlace() {
        const playerId = this.getPlayerId();
        for (let star = 0; star <= 6; star++) {
            for (let space = 1; space <= 6; space++) {
                document.getElementById(`player-table-${playerId}-star-${star}-space-${space}`)?.classList.remove('selectable');
            }
        }
    }
    onLeavingChooseColor() {
        document.querySelectorAll('.space.selected').forEach(elem => elem.classList.remove('selected'));
    }
    onLeavingPlayTile() {
    }
    onLeavingChooseKeptTiles() {
        document.getElementById(`player-hand-${this.getPlayerId()}`)?.classList.remove('selectable');
        document.querySelectorAll('.tile.selected').forEach(elem => elem.classList.remove('selected'));
    }
    onLeavingTakeBonusTiles() {
        document.getElementById(`supply`).classList.remove('selectable');
        document.querySelectorAll('.tile.selected').forEach(elem => elem.classList.remove('selected'));
        document.querySelectorAll('.tile.bonus').forEach(elem => elem.classList.remove('bonus'));
        document.querySelectorAll(`.bonus-info.active`).forEach(elem => elem.classList.remove('active'));
    }
    updateSelectKeptTilesButton() {
        const button = document.getElementById(`selectKeptTiles_button`);
        const handDiv = document.getElementById(`player-hand-${this.getPlayerId()}`);
        const handTileDivs = Array.from(handDiv.querySelectorAll('.tile:not(.tile0)'));
        const selectedTileDivs = Array.from(handDiv.querySelectorAll('.tile.selected'));
        const selectedTileDivsIds = selectedTileDivs.map((div) => Number(div.dataset.id));
        const discardedTileDivs = handTileDivs.filter((div) => !selectedTileDivsIds.includes(Number(div.dataset.id)));
        const warning = selectedTileDivs.length < handTileDivs.length && selectedTileDivs.length < 4;
        const labelKeep = selectedTileDivs.map((div) => this.bga.gameui.format_string_recursive('${number} ${color}', { number: 1, type: Number(div.dataset.type) })).join('');
        const labelDiscard = discardedTileDivs.map((div) => this.bga.gameui.format_string_recursive('${number} ${color}', { number: 1, type: Number(div.dataset.type) })).join('');
        let label = '';
        if (labelKeep != '' && labelDiscard != '') {
            label = _("Keep ${keep} and discard ${discard}");
        }
        else if (labelKeep != '') {
            label = _("Keep ${keep}");
        }
        else if (labelDiscard != '') {
            label = _("Discard ${discard}");
        }
        label = label.replace('${keep}', labelKeep).replace('${discard}', labelDiscard);
        button.innerHTML = label;
        button.classList.toggle('bgabutton_blue', !warning);
        button.classList.toggle('bgabutton_red', warning);
        button.classList.toggle('disabled', selectedTileDivs.length > 4);
    }
    updateTakeBonusTilesButton() {
        const button = document.getElementById(`takeBonusTiles_button`);
        const supplyDiv = document.getElementById(`supply`);
        const selectedTileDivs = Array.from(supplyDiv.querySelectorAll('.tile.selected'));
        let label = '-';
        if (selectedTileDivs.length > 0) {
            label = selectedTileDivs.map((div) => this.bga.gameui.format_string_recursive('${number} ${color}', { number: 1, type: Number(div.dataset.type) })).join('');
        }
        button.innerHTML = _("Take ${tiles}").replace('${tiles}', label);
        button.classList.toggle('disabled', selectedTileDivs.length != this.gamedatas.gamestate.args.count);
    }
    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons(stateName, args) {
        log('onUpdateActionButtons', stateName, args);
        if (this.bga.players.isCurrentPlayerActive()) {
            switch (stateName) {
                case 'confirmAcquire':
                    this.bga.statusBar.addActionButton(_("Confirm"), () => this.confirmAcquire(), { autoclick: this.bga.userPreferences.get(204) != 2 });
                    this.bga.statusBar.addActionButton(_("Undo tile selection"), () => this.undoTakeTiles(), { color: 'secondary' });
                    break;
                case 'choosePlace':
                    const choosePlaceArgs = args;
                    this.bga.statusBar.addActionButton(_("Pass (end round)"), () => this.pass(), { color: choosePlaceArgs?.skipIsFree ? undefined : 'alert' });
                    break;
                case 'chooseColor':
                    const chooseColorArgs = args;
                    chooseColorArgs.possibleColors.forEach(color => {
                        const label = this.bga.gameui.format_string_recursive('${number} ${color}', { number: 1, type: color });
                        this.bga.statusBar.addActionButton(label, () => this.selectColor(color));
                    });
                    this.bga.statusBar.addActionButton(_("Undo played tile"), () => this.undoPlayTile(), { color: 'secondary' });
                    break;
                case 'playTile':
                    const playTileArgs = args;
                    for (let i = 0; i <= playTileArgs.maxWildTiles; i++) {
                        const colorNumber = playTileArgs.number - i;
                        if (colorNumber <= args.maxColor) {
                            let label = (colorNumber === 0 ? '' : this.bga.gameui.format_string_recursive('${number} ${color}', { number: colorNumber, type: playTileArgs.color })) +
                                (i === 0 ? '' : this.bga.gameui.format_string_recursive('${number} ${color}', { number: i, type: playTileArgs.wildColor }));
                            this.bga.statusBar.addActionButton(label, () => this.playTile(i));
                        }
                    }
                    this.bga.statusBar.addActionButton(_("Undo played tile"), () => this.undoPlayTile(), { color: 'secondary' });
                    break;
                case 'confirmPlay':
                    this.bga.statusBar.addActionButton(_("Confirm"), () => this.confirmPlay(), { autoclick: this.bga.userPreferences.get(204) != 2 });
                    this.bga.statusBar.addActionButton(_("Undo played tile"), () => this.undoPlayTile(), { color: 'secondary' });
                    break;
                case 'chooseKeptTiles':
                    this.bga.statusBar.addActionButton('', () => this.selectKeptTiles(), { id: 'selectKeptTiles_button' });
                    this.bga.statusBar.addActionButton(_("Cancel"), () => this.undoPass(), { color: 'secondary' });
                    this.updateSelectKeptTilesButton();
                    break;
                case 'confirmPass':
                    this.bga.statusBar.addActionButton(_("Confirm"), () => this.confirmPass(), { autoclick: this.bga.userPreferences.get(204) != 2 });
                    this.bga.statusBar.addActionButton(_("Cancel"), () => this.undoPass(), { color: 'secondary' });
                    break;
                case 'takeBonusTiles':
                    this.bga.statusBar.addActionButton('', () => this.takeBonusTiles(), { id: 'takeBonusTiles_button' });
                    this.bga.statusBar.addActionButton(_("Undo played tile"), () => this.undoPlayTile(), { color: 'secondary' });
                    this.updateTakeBonusTilesButton();
                    break;
            }
        }
    }
    ///////////////////////////////////////////////////
    //// Utility methods
    ///////////////////////////////////////////////////
    setupPreferences() {
        this.bga.userPreferences.toggleVisibility(299, false);
        this.bga.userPreferences.onChange = (prefId, prefValue) => this.onUserPreferenceChanged(prefId, prefValue);
    }
    onUserPreferenceChanged(prefId, prefValue) {
        switch (prefId) {
            case 201:
                document.getElementById('table').classList.toggle('disabled-shimmer', prefValue == 2);
                break;
            case 203:
                document.documentElement.classList.toggle('cb', prefValue == 1);
                break;
            case 205:
                document.documentElement.classList.toggle('hide-tile-count', prefValue == 2);
                break;
            case 206:
                this.playersTables.forEach(playerTable => playerTable.setFont(prefValue));
                break;
            case 207:
                document.documentElement.classList.toggle('show-numbers', prefValue == 1);
                break;
            case 299:
                this.toggleZoomNotice(prefValue == 1);
                break;
        }
    }
    toggleZoomNotice(visible) {
        const elem = document.getElementById('zoom-notice');
        if (visible) {
            if (!elem) {
                document.getElementById('bga-zoom_controls').insertAdjacentHTML('beforeend', `
                <div id="zoom-notice">
                    ${_("Use zoom controls to adapt players board size !")}
                    <div style="text-align: center; margin-top: 10px;"><a id="hide-zoom-notice">${_("Dismiss")}</a></div>
                    <div class="arrow-right"></div>
                </div>
                `);
                document.getElementById('hide-zoom-notice').addEventListener('click', () => this.bga.userPreferences.set(299, 2));
            }
        }
        else if (elem) {
            elem.parentElement.removeChild(elem);
        }
    }
    isDefaultFont() {
        return this.bga.userPreferences.get(206) == 1;
    }
    getZoom() {
        return this.zoom;
    }
    onTableCenterSizeChange(newZoom) {
        this.zoom = newZoom;
        const maxWidth = document.getElementById('table').clientWidth;
        const factoriesWidth = document.getElementById('factories-and-scoring-board').clientWidth;
        const playerTableWidth = 780;
        const tablesMaxWidth = maxWidth - factoriesWidth;
        document.getElementById('centered-table').style.width = tablesMaxWidth < playerTableWidth * this.gamedatas.playerorder.length ?
            `${factoriesWidth + (Math.floor(tablesMaxWidth / playerTableWidth) * playerTableWidth)}px` : `unset`;
    }
    getBoardNumber() {
        return this.gamedatas.boardNumber;
    }
    getStars() {
        return this.gamedatas.stars;
    }
    getPlayerId() {
        return this.bga.players.getCurrentPlayerId();
    }
    getPlayerColor(playerId) {
        return this.gamedatas.players[playerId].color;
    }
    getPlayerTable(playerId) {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }
    setScore(playerId, score) {
        this.bga.playerPanels.getScoreCounter(playerId).toValue(score);
    }
    placeTile(tile, destinationId, left, top, rotation, placeInParent) {
        //this.removeTile(tile);
        //document.getElementById(destinationId).insertAdjacentHTML('beforeend', `<div id="tile${tile.id}" class="tile tile${tile.type}" style="left: ${left}px; top: ${top}px;"></div>`);
        const tileDiv = document.getElementById(`tile${tile.id}`);
        if (tileDiv) {
            return slideToObjectAndAttach(this, tileDiv, destinationId, left, top, rotation, placeInParent);
        }
        else {
            const destination = document.getElementById(destinationId);
            const newTileDiv = document.createElement('div');
            newTileDiv.id = `tile${tile.id}`;
            newTileDiv.classList.add(`tile`, `tile${tile.type}`);
            newTileDiv.dataset.id = `${tile.id}`;
            newTileDiv.dataset.type = `${tile.type}`;
            newTileDiv.dataset.rotation = `${rotation ?? 0}`;
            if (left !== undefined) {
                newTileDiv.style.left = `${left}px`;
            }
            if (top !== undefined) {
                newTileDiv.style.top = `${top}px`;
            }
            if (placeInParent) {
                placeInParent(newTileDiv, destination);
            }
            else {
                destination.appendChild(newTileDiv);
            }
            newTileDiv.style.setProperty('--rotation', `${rotation ?? 0}deg`);
            newTileDiv.addEventListener('click', () => {
                if (tile.type > 0) {
                    this.onTileClick(tile);
                    this.factories.tileMouseLeave(tile.id);
                }
            });
            newTileDiv.addEventListener('mouseenter', () => this.factories.tileMouseEnter(tile.id));
            newTileDiv.addEventListener('mouseleave', () => this.factories.tileMouseLeave(tile.id));
            return Promise.resolve(true);
        }
    }
    createPlayerPanels(gamedatas) {
        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);
            // first player token
            this.bga.playerPanels.getElement(playerId).insertAdjacentHTML('beforeend', `<div id="player-board-${player.id}-firstPlayerWrapper" class="firstPlayerWrapper disabled-shimmer"></div>`);
            if (gamedatas.firstPlayerTokenPlayerId === playerId) {
                this.placeFirstPlayerToken(gamedatas.firstPlayerTokenPlayerId);
            }
            document.getElementById(`overall_player_board_${playerId}`).classList.toggle('passed', player.passed);
        });
    }
    createPlayerTables(gamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === this.bga.players.getCurrentPlayerId());
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        orderedPlayers.forEach(player => this.createPlayerTable(gamedatas, Number(player.id)));
    }
    createPlayerTable(gamedatas, playerId) {
        this.playersTables.push(new PlayerTable(this, gamedatas.players[playerId]));
    }
    removeTile(tile, fadeOut) {
        // we don't remove the FP tile, it just goes back to the center
        if (tile.type == 0) {
            const coordinates = this.factories.getCoordinatesForTile0();
            this.placeTile(tile, `factory0`, coordinates.left, coordinates.top, undefined);
        }
        else {
            const divElement = document.getElementById(`tile${tile.id}`);
            if (divElement) {
                if (fadeOut) {
                    this.animationManager.fadeOutAndDestroy(divElement);
                }
                else {
                    divElement.parentElement.removeChild(divElement);
                }
            }
        }
    }
    removeTiles(tiles, fadeOut) {
        tiles.forEach(tile => this.removeTile(tile, fadeOut));
    }
    addAutopassToggle(active) {
        if (!document.getElementById('autopass-wrapper')) {
            document.getElementById(`game_play_area`).insertAdjacentHTML('beforeend', `<div id="autopass-wrapper">
                <label class="switch">
                    <input id="autopass-checkbox" type="checkbox" ${active ? 'checked' : ''}>
                    <span class="slider round"></span>
                </label>
                <label for="autopass-checkbox" class="text-label">${_("Auto-pass")}</label>
            </div>`);
            document.getElementById('autopass-checkbox').addEventListener('change', (e) => this.bga.actions.performAction('actSetAutopass', { autopass: e.target.checked }, { checkAction: false, }));
        }
    }
    removeAutopassToggle() {
        document.getElementById('autopass-wrapper')?.remove();
    }
    onTileClick(tile) {
        if (this.gamedatas.gamestate.name == 'chooseTile') {
            this.takeTiles(tile.id);
        }
        else if (this.gamedatas.gamestate.name == 'chooseKeptTiles') {
            const divElement = document.getElementById(`tile${tile.id}`);
            if (divElement?.closest(`#player-hand-${this.getPlayerId()}`)) {
                divElement.classList.toggle('selected');
                this.updateSelectKeptTilesButton();
            }
        }
        else if (this.gamedatas.gamestate.name == 'takeBonusTiles') {
            const divElement = document.getElementById(`tile${tile.id}`);
            if (divElement?.closest(`#supply`)) {
                divElement.classList.toggle('selected');
                this.updateTakeBonusTilesButton();
            }
        }
    }
    takeTiles(id) {
        this.bga.actions.performAction('actTakeTiles', {
            id
        });
    }
    undoTakeTiles() {
        this.bga.actions.performAction('actUndoTakeTiles');
    }
    confirmAcquire() {
        this.bga.actions.performAction('actConfirmAcquire');
    }
    pass() {
        this.bga.actions.performAction('actPass');
    }
    selectColor(color) {
        this.bga.actions.performAction('actSelectColor', {
            color
        });
    }
    playTile(wilds) {
        this.bga.actions.performAction('actPlayTile', {
            wilds
        });
    }
    confirmPlay() {
        this.bga.actions.performAction('actConfirmPlay');
    }
    confirmPass() {
        this.bga.actions.performAction('actConfirmPass');
    }
    undoPlayTile() {
        this.bga.actions.performAction('actUndoPlayTile');
    }
    undoPass() {
        this.bga.actions.performAction('actUndoPass');
    }
    selectPlace(star, space) {
        this.bga.actions.performAction('actSelectPlace', {
            star,
            space
        });
        //this.removeGhostTile();
    }
    selectKeptTiles(askConfirmation = true) {
        const handDiv = document.getElementById(`player-hand-${this.getPlayerId()}`);
        const handTileDivs = handDiv.querySelectorAll('.tile');
        const selectedTileDivs = handDiv.querySelectorAll('.tile.selected');
        if (askConfirmation && selectedTileDivs.length < handTileDivs.length && selectedTileDivs.length < 4) {
            this.bga.dialogs.confirmation(_('You will keep ${keep} tiles and discard ${discard} tiles, when you could keep ${possible} tiles!')
                .replace('${keep}', `<strong>${selectedTileDivs.length}</strong>`)
                .replace('${discard}', `<strong>${handTileDivs.length - selectedTileDivs.length}</strong>`)
                .replace('${possible}', `<strong>${Math.min(4, handTileDivs.length)}</strong>`)).then(result => {
                if (result) {
                    this.selectKeptTiles(false);
                }
            });
        }
        else {
            this.bga.actions.performAction('actSelectKeptTiles', {
                ids: Array.from(selectedTileDivs).map((tile) => Number(tile.dataset.id)).sort().join(','),
            });
        }
    }
    cancel() {
        this.bga.actions.performAction('actCancel');
    }
    takeBonusTiles() {
        const supplyDiv = document.getElementById(`supply`);
        const selectedTileDivs = supplyDiv.querySelectorAll('.tile.selected');
        this.bga.actions.performAction('actTakeBonusTiles', {
            ids: Array.from(selectedTileDivs).map((tile) => Number(tile.dataset.id)).sort().join(','),
        });
    }
    placeFirstPlayerToken(playerId) {
        const firstPlayerToken = document.getElementById('firstPlayerToken');
        if (firstPlayerToken) {
            this.animationManager.slideAndAttach(firstPlayerToken, document.getElementById(`player-board-${playerId}-firstPlayerWrapper`));
        }
        else {
            document.getElementById(`player-board-${playerId}-firstPlayerWrapper`).insertAdjacentHTML('beforeend', '<div id="firstPlayerToken" class="tile tile0"></div>');
            this.bga.gameui.addTooltipHtml('firstPlayerToken', _("First Player token. Player with this token will start the next turn"));
        }
    }
    displayScoringOnTile(tile, playerId, points) {
        // create a div over tile, same position and width, but no overflow hidden (that must be kept on tile for glowing effect)
        document.getElementById(`player-table-${playerId}-star-${tile.star}-space-${tile.space}`).insertAdjacentHTML('beforeend', `<div id="tile${tile.id}-scoring" class="scoring-tile"></div>`);
        this.animationManager.displayScoring(document.getElementById(`tile${tile.id}-scoring`), points, this.getPlayerColor(Number(playerId)), { duration: SCORE_MS });
    }
    displayScoringOnStar(star, playerId, points) {
        if (!document.getElementById(`player-table-${playerId}-star-${star}-scoring`)) {
            document.getElementById(`player-table-${playerId}-star-${star}`).insertAdjacentHTML('beforeend', `<div id="player-table-${playerId}-star-${star}-scoring" class="scoring-star"></div>`);
        }
        this.animationManager.displayScoring(document.getElementById(`player-table-${playerId}-star-${star}-scoring`), points, this.getPlayerColor(Number(playerId)), { duration: SCORE_MS });
    }
    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your azulsummerpavilion.game.php file.

    */
    setupNotifications() {
        //log( 'notifications subscriptions setup' );
        const notifs = [
            ['factoriesFilled', ANIMATION_MS + REFILL_DELAY[this.gamedatas.factoryNumber]],
            ['supplyFilled', ANIMATION_MS],
            ['factoriesChanged', ANIMATION_MS],
            ['factoriesCompleted', ANIMATION_MS],
            ['tilesSelected', ANIMATION_MS],
            ['undoTakeTiles', ANIMATION_MS],
            ['undoPlayTile', ANIMATION_MS],
            ['placeTileOnWall', ANIMATION_MS],
            ['putToCorner', ANIMATION_MS],
            ['cornerToHand', 1],
            ['endScore', this.gamedatas.fastScoring ? SCORE_MS : SLOW_SCORE_MS],
            ['firstPlayerToken', 1],
            ['lastRound', 1],
            ['pass', 1],
        ];
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, e => {
                this[`notif_${notif[0]}`](e.args);
                if (e.args.playerId && e.args.newScore !== undefined && e.args.newScore !== null) {
                    this.setScore(e.args.playerId, e.args.newScore);
                }
            });
            this.bga.gameui.notifqueue.setSynchronous(notif[0], notif[1]);
        });
        ['completeStarLogDetails', 'completeNumberLogDetails', 'completeStructureSetLogDetails'].forEach(notifName => {
            dojo.subscribe(notifName, this, e => {
                if (e.args.playerId && e.args.newScore !== undefined) {
                    this.setScore(e.args.playerId, e.args.newScore);
                }
            });
        });
    }
    notif_factoriesFilled(args) {
        document.getElementById('factories-and-scoring-board').classList.remove('play');
        this.factories.fillFactories(args.factories);
        this.factories.setRemainingTiles(args.remainingTiles);
        this.scoringBoard.setRoundNumber(args.roundNumber);
        document.getElementById('round').innerText = `${args.roundNumber}`;
        const wildToken = document.getElementById(`wildToken`);
        wildToken.classList.remove(`tile${this.getSpecialTile(args.roundNumber - 1)}`);
        wildToken.classList.add(`tile${this.getSpecialTile(args.roundNumber)}`);
        Object.keys(this.gamedatas.players).forEach(playerId => document.getElementById(`overall_player_board_${playerId}`).classList.remove('passed'));
    }
    notif_supplyFilled(args) {
        this.factories.setRemainingTiles(args.remainingTiles);
        this.scoringBoard.placeTiles(args.newTiles, true);
    }
    notif_factoriesChanged(args) {
        this.factories.factoriesChanged(args);
    }
    notif_factoriesCompleted(args) {
        this.factories.factoriesCompleted(args);
    }
    notif_tilesSelected(args) {
        if (!args.fromSupply) {
            if (args.fromFactory == 0) {
                this.factories.centerColorRemoved(args.selectedTiles);
            }
            else {
                this.factories.factoryTilesRemoved(args.fromFactory);
            }
        }
        const table = this.getPlayerTable(args.playerId);
        table.placeTilesOnHand(args.selectedTiles);
        if (!args.fromSupply) {
            this.factories.discardTiles(args.discardedTiles);
        }
    }
    notif_undoTakeTiles(args) {
        this.placeFirstPlayerToken(args.undo.previousFirstPlayer);
        this.factories.undoTakeTiles(args.undo.tiles, args.undo.from, args.factoryTilesBefore);
        this.setScore(args.playerId, args.undo.previousScore);
    }
    notif_undoPlayTile(args) {
        const { playerId, undo } = args;
        const table = this.getPlayerTable(playerId);
        if (undo) {
            table.placeTilesOnHand(undo.tiles);
            this.setScore(playerId, undo.previousScore);
            this.scoringBoard.placeTiles(undo.supplyTiles, true);
        }
        document.getElementById(`overall_player_board_${playerId}`).classList.remove('passed');
        // this.removeGhostTile();
    }
    /*notif_tilesPlacedOnLine(args: NotifTilesPlacedOnLineArgs) {
        this.getPlayerTable(args.playerId).placeTilesOnLine(args.discardedTiles, 0);
        this.getPlayerTable(args.playerId).placeTilesOnLine(args.placedTiles, args.line);
    }*/
    notif_placeTileOnWall(args) {
        const { playerId, placedTile, discardedTiles, scoredTiles } = args;
        //this.removeGhostTile();
        const playerTable = this.getPlayerTable(playerId);
        playerTable.placeTilesOnWall([placedTile]);
        this.removeTiles(discardedTiles, true);
        scoredTiles.forEach(tile => document.getElementById(`tile${tile.id}`).classList.add('highlight'));
        setTimeout(() => scoredTiles.forEach(tile => document.getElementById(`tile${tile.id}`).classList.remove('highlight')), SCORE_MS - 50);
        this.displayScoringOnTile(placedTile, playerId, scoredTiles.length);
    }
    notif_putToCorner(args) {
        const { playerId, keptTiles, discardedTiles } = args;
        this.getPlayerTable(playerId).placeTilesOnCorner(keptTiles);
        this.removeTiles(discardedTiles, true);
        if (discardedTiles.length > 0) {
            this.animationManager.displayScoring(document.getElementById(`player-hand-${playerId}`), -discardedTiles.length, this.getPlayerColor(Number(playerId)), { duration: SCORE_MS });
        }
    }
    notif_cornerToHand(args) {
        const { playerId, tiles } = args;
        this.getPlayerTable(playerId).placeTilesOnHand(tiles);
    }
    notif_pass(args) {
        const { playerId } = args;
        document.getElementById(`overall_player_board_${playerId}`).classList.add('passed');
    }
    notif_endScore(args) {
        Object.keys(args.scores).forEach(playerId => {
            const endScore = args.scores[playerId];
            endScore.tiles?.forEach(tile => document.getElementById(`tile${tile.id}`).classList.add('highlight'));
            setTimeout(() => endScore.tiles?.forEach(tile => document.getElementById(`tile${tile.id}`).classList.remove('highlight')), SCORE_MS - 50);
            this.displayScoringOnStar(endScore.star, playerId, endScore.points);
        });
    }
    notif_firstPlayerToken(args) {
        const { playerId, decScore } = args;
        this.placeFirstPlayerToken(playerId);
        this.factories.displayScoringCenter(playerId, -decScore);
    }
    notif_lastRound() {
        if (document.getElementById('last-round')) {
            return;
        }
        // TODO useful ? document.getElementById('page-title').insertAdjacentHTML('beforeend', `<div id="last-round">${_("This is the last round of the game!")}</div>`);
    }
    /* This enable to inject translatable styled things to logs or action bar */
    bgaFormatText(log, args) {
        try {
            if (log && args && !args.processed) {
                if (typeof args.lineNumber === 'number') {
                    args.lineNumber = `<strong>${args.line}</strong>`;
                }
                if (log.indexOf('${number} ${color}') !== -1 && typeof args.type === 'number') {
                    const number = args.number;
                    let html = '';
                    for (let i = 0; i < number; i++) {
                        html += `<div class="tile tile${args.type}"></div>`;
                    }
                    log = _(log).replace('${number} ${color}', html);
                }
                else if (log.indexOf('${color}') !== -1 && typeof args.type === 'number') {
                    let html = `<div class="tile tile${args.type}"></div>`;
                    log = _(log).replace('${color}', html);
                }
                if (log.indexOf('${wild}') !== -1 && typeof args.typeWild === 'number') {
                    let html = `<div class="tile tile${args.typeWild}"></div>`;
                    log = _(log).replace('${wild}', html);
                }
                /*if (args._bga_automatic_action) {
                    log = _(log) + ` (⚙)`;
                }*/
            }
        }
        catch (e) {
            console.error(log, args, "Exception thrown", e.stack);
        }
        return { log, args };
    }
}

export { Game };
