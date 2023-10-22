declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;

declare const board: HTMLDivElement;

const ANIMATION_MS = 500;
const SCORE_MS = 1500;
const SLOW_SCORE_MS = 2000;

const REFILL_DELAY = [];
REFILL_DELAY[5] = 1600;
REFILL_DELAY[7] = 2200;
REFILL_DELAY[9] = 2900;

const ZOOM_LEVELS = [0.25, 0.375, 0.5, 0.625, 0.75, 0.875, 1];
const LOCAL_STORAGE_ZOOM_KEY = 'AzulSummerPavilion-zoom';

const isDebug = window.location.host == 'studio.boardgamearena.com';
const log = isDebug ? console.log.bind(window.console) : function () { };

class AzulSummerPavilion implements AzulSummerPavilionGame {
    public animationManager: AnimationManager;

    private gamedatas: AzulSummerPavilionGamedatas;
    private zoomManager: ZoomManager;
    private factories: Factories;
    private playersTables: PlayerTable[] = [];

    public zoom: number = 0.75;

    constructor() {    
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

    public setup(gamedatas: AzulSummerPavilionGamedatas) {
        // ignore loading of some pictures
        if (this.isVariant()) {
            (this as any).dontPreloadImage('playerboard.jpg');
        } else {
            (this as any).dontPreloadImage('playerboard-variant.jpg');
        }
        (this as any).dontPreloadImage('publisher.png');

        log("Starting game setup");
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);

        this.animationManager = new AnimationManager(this);

        this.createPlayerPanels(gamedatas);
        this.factories = new Factories(this, gamedatas.factoryNumber, gamedatas.factories, gamedatas.remainingTiles);
        this.createPlayerTables(gamedatas);

        // before set
        this.zoomManager = new ZoomManager({
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

        log("Ending game setup");
    }

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    public onEnteringState(stateName: string, args: any) {
        log( 'Entering state: '+stateName , args.args );

        switch (stateName) {
            case 'chooseTile':
                this.onEnteringChooseTile(args.args);
                break;
            case 'choosePlace':
                this.onEnteringChoosePlace(args.args);
                break;
            case 'playTile':
                this.onEnteringPlayTile(args.args);
                break;
            case 'chooseKeptTiles':
                this.onEnteringChooseKeptTiles(args.args);
                break;
            case 'gameEnd':
                const lastTurnBar = document.getElementById('last-round');
                if (lastTurnBar) {
                    lastTurnBar.style.display = 'none';
                }
                break;
        }
    }

    onEnteringChooseTile(args: EnteringChooseTileArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            this.factories.wildColor = args.wildColor;
            dojo.addClass('factories', 'selectable');
        }
    }

    onEnteringChoosePlace(args: EnteringChoosePlaceArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            const playerId = this.getPlayerId();
            for (let star = 0; star <= 6; star++) {
                for (let space = 1; space <= 6; space++) {
                    document.getElementById(`player-table-${playerId}-star-${star}-space-${space}`).classList.toggle('selectable',
                        args.possibleSpaces.includes(star * 100 + space)
                    );
                }
            }
        }
    }

    onEnteringPlayTile(args: EnteringPlayTileArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            // TODO place ghost
        }
    }

    onEnteringChooseKeptTiles(args: EnteringChooseTileArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            document.getElementById(`player-hand-${this.getPlayerId()}`).classList.add('selectable');
        }
    }

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    public onLeavingState(stateName: string) {
        log( 'Leaving state: '+stateName );

        switch (stateName) {
            case 'chooseTile':
                this.onLeavingChooseTile();
                break;
            case 'choosePlace':
                this.onLeavingChoosePlace();
                break;
            case 'playTile':
                this.onLeavingPlayTile();
                break;
            case 'chooseKeptTiles':
                this.onLeavingChooseKeptTiles();
                break;
        }
    }

    onLeavingChooseTile() {
        dojo.removeClass('factories', 'selectable');
    }

    onLeavingChoosePlace() {
        const playerId = this.getPlayerId();
        for (let star = 0; star <= 6; star++) {
            for (let space = 1; space <= 6; space++) {
                document.getElementById(`player-table-${playerId}-star-${star}-space-${space}`)?.classList.remove('selectable');
            }
        }
    }

    onLeavingPlayTile() {
        // TODO remove ghost
    }

    onLeavingChooseKeptTiles() {
        document.getElementById(`player-hand-${this.getPlayerId()}`)?.classList.remove('selectable');
    }

    private updateSelectKeptTilesButton() {
        const button = document.getElementById(`selectKeptTiles_button`);

        const handDiv = document.getElementById(`player-hand-${this.getPlayerId()}`);
        const handTileDivs = Array.from(handDiv.querySelectorAll('.tile'));
        const selectedTileDivs = Array.from(handDiv.querySelectorAll('.tile.selected'));
        const selectedTileDivsIds = selectedTileDivs.map((div: HTMLElement) => Number(div.dataset.id));
        const discardedTileDivs = handTileDivs.filter((div: HTMLElement) => !selectedTileDivsIds.includes(Number(div.dataset.id)));
        const warning = selectedTileDivs.length < handTileDivs.length && selectedTileDivs.length < 4;

        const labelKeep = selectedTileDivs.map((div: HTMLElement) => this.format_string_recursive('${number} ${color}', { number: 1, type: Number(div.dataset.type) })).join('');
        const labelDiscard = discardedTileDivs.map((div: HTMLElement) => this.format_string_recursive('${number} ${color}', { number: 1, type: Number(div.dataset.type) })).join('');
        let label = '';
        if (labelKeep != '' && labelDiscard != '') {
            label = _("Keep ${keep} and discard ${discard}");
        } else if (labelKeep != '') {
            label = _("Keep ${keep}");
        } else if (labelDiscard != '') {
            label = _("Discard ${discard}");
        }  
        label = label.replace('${keep}', labelKeep).replace('${discard}', labelDiscard);

        button.innerHTML = label;
        button.classList.toggle('bgabutton_blue', !warning);
        button.classList.toggle('bgabutton_red', warning);
        button.classList.toggle('disabled', selectedTileDivs.length > 4);
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        log('onUpdateActionButtons', stateName, args);
        
        if((this as any).isCurrentPlayerActive()) {
            switch (stateName) { 
                case 'confirmAcquire':
                    (this as any).addActionButton('confirmAcquire_button', _("Confirm"), () => this.confirmAcquire());
                    (this as any).addActionButton('undoAcquire_button', _("Undo tile selection"), () => this.undoTakeTiles(), null, null, 'gray');
                    this.startActionTimer('confirmAcquire_button', 5);
                    break;
                case 'choosePlace':
                    const choosePlaceArgs = args as EnteringChoosePlaceArgs;
                    (this as any).addActionButton('pass_button', _("Pass (end round)"), () => this.pass(), null, null, choosePlaceArgs.skipIsFree ? undefined : 'red');
                    break;
                case 'chooseColor':
                    const chooseColorArgs = args as EnteringChooseColorArgs;
                    chooseColorArgs.possibleColors.forEach(color => {
                        const label = this.format_string_recursive('${number} ${color}', { number: 1, type: color });
                        (this as any).addActionButton(`chooseColor${color}_button`, label, () => this.selectColor(color));
                    });
                    (this as any).addActionButton('undoPlayTile_button', _("Undo line selection"), () => this.undoPlayTile(), null, null, 'gray');
                    break;
                case 'playTile':
                    const playTileArgs = args as EnteringPlayTileArgs;
                    for (let i = 0; i <= playTileArgs.maxWildTiles; i++) {
                        const colorNumber = playTileArgs.number - i;
                        if (colorNumber <= args.maxColor) {
                            let label = this.format_string_recursive('${number} ${color}', { number: colorNumber, type: playTileArgs.color });
                            label += this.format_string_recursive('${number} ${color}', { number: i, type: playTileArgs.wildColor });
                            (this as any).addActionButton(`playTile${i}_button`, label, () => this.playTile(i));
                        }
                    }
                    (this as any).addActionButton('undoPlayTile_button', _("Undo line selection"), () => this.undoPlayTile(), null, null, 'gray');
                    break;
                case 'confirmPlay':
                    (this as any).addActionButton('confirmLine_button', _("Confirm"), () => this.confirmPlay());
                    (this as any).addActionButton('undoPlayTile_button', _("Undo line selection"), () => this.undoPlayTile(), null, null, 'gray');
                    this.startActionTimer('confirmLine_button', 5);
                    break;
                case 'chooseKeptTiles':
                    (this as any).addActionButton('selectKeptTiles_button', '', () => this.selectKeptTiles());
                    this.updateSelectKeptTilesButton();
                    break;
            }
        }
    } 
    

    ///////////////////////////////////////////////////
    //// Utility methods


    ///////////////////////////////////////////////////

    private setupPreferences() {
        // Extract the ID and value from the UI control
        const onchange = (e) => {
          var match = e.target.id.match(/^preference_control_(\d+)$/);
          if (!match) {
            return;
          }
          var prefId = +match[1];
          var prefValue = +e.target.value;
          (this as any).prefs[prefId].value = prefValue;
          this.onPreferenceChange(prefId, prefValue);
        }
        
        // Call onPreferenceChange() when any value changes
        dojo.query(".preference_control").connect("onchange", onchange);
        
        // Call onPreferenceChange() now
        dojo.forEach(
          dojo.query("#ingame_menu_content .preference_control"),
          el => onchange({ target: el })
        );

        try {
            (document.getElementById('preference_control_299').closest(".preference_choice") as HTMLDivElement).style.display = 'none';
            (document.getElementById('preference_fontrol_299').closest(".preference_choice") as HTMLDivElement).style.display = 'none';
        } catch (e) {}
    }
      
    private onPreferenceChange(prefId: number, prefValue: number) {
        switch (prefId) {
            // KEEP
            case 201: 
                dojo.toggleClass('table', 'disabled-shimmer', prefValue == 2);
                break;
            case 202:
                dojo.toggleClass(document.getElementsByTagName('html')[0] as any, 'background2', prefValue == 2);
                this.zoomManager.setZoomControlsColor(prefValue == 2 ? 'white' : 'black');
                break;
            case 203:
                dojo.toggleClass(document.getElementsByTagName('html')[0] as any, 'cb', prefValue == 1);
                break;
            case 205:
                dojo.toggleClass(document.getElementsByTagName('html')[0] as any, 'hide-tile-count', prefValue == 2);
                break;
            case 206: 
                this.playersTables.forEach(playerTable => playerTable.setFont(prefValue));
                break;
            case 299: 
                this.toggleZoomNotice(prefValue == 1);
                break;
        }
    }

    private toggleZoomNotice(visible: boolean) {
        const elem = document.getElementById('zoom-notice');
        if (visible) {
            if (!elem) {
                dojo.place(`
                <div id="zoom-notice">
                    ${_("Use zoom controls to adapt players board size !")}
                    <div style="text-align: center; margin-top: 10px;"><a id="hide-zoom-notice">${_("Dismiss")}</a></div>
                    <div class="arrow-right"></div>
                </div>
                `, 'bga-zoom-controls');

                document.getElementById('hide-zoom-notice').addEventListener('click', () => {
                    const select = document.getElementById('preference_control_299') as HTMLSelectElement;
                    select.value = '2';
    
                    var event = new Event('change');
                    select.dispatchEvent(event);
                });
            }
        } else if (elem) {
            elem.parentElement.removeChild(elem);
        }
    }

    public isDefaultFont(): boolean {
        return Number((this as any).prefs[206].value) == 1;
    }

    private startActionTimer(buttonId: string, time: number) {
        if ((this as any).prefs[204]?.value === 2) {
            return;
        }

        const button = document.getElementById(buttonId);
 
        let actionTimerId = null;
        const _actionTimerLabel = button.innerHTML;
        let _actionTimerSeconds = time;
        const actionTimerFunction = () => {
            const button = document.getElementById(buttonId);
            if (button == null) {
                window.clearInterval(actionTimerId);
            } else if (_actionTimerSeconds-- > 1) {
                button.innerHTML = _actionTimerLabel + ' (' + _actionTimerSeconds + ')';
            } else {
                window.clearInterval(actionTimerId);
                button.click();
            }
        };
        actionTimerFunction();
        actionTimerId = window.setInterval(() => actionTimerFunction(), 1000);
    }

    public getZoom() {
        return this.zoom;
    }

    private onTableCenterSizeChange(newZoom: number) {
        this.zoom = newZoom;

        const maxWidth = document.getElementById('table').clientWidth;
        const factoriesWidth = document.getElementById('factories').clientWidth;
        const playerTableWidth = 780;
        const tablesMaxWidth = maxWidth - factoriesWidth;
     
        document.getElementById('centered-table').style.width = tablesMaxWidth < playerTableWidth * this.gamedatas.playerorder.length ?
            `${factoriesWidth + (Math.floor(tablesMaxWidth / playerTableWidth) * playerTableWidth)}px` : `unset`;
    }

    public isVariant(): boolean {
        return this.gamedatas.variant;
    }

    public getPlayerId(): number {
        return Number((this as any).player_id);
    }

    public getPlayerColor(playerId: number): string {
        return this.gamedatas.players[playerId].color;
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    private incScore(playerId: number, incScore: number) {
        if ((this as any).scoreCtrl[playerId]?.getValue() + incScore < 1) {
            (this as any).scoreCtrl[playerId]?.toValue(1);
        } else {
            (this as any).scoreCtrl[playerId]?.incValue(incScore);
        }
    }

    public placeTile(tile: Tile, destinationId: string, left?: number, top?: number, rotation?: number): Promise<boolean> {
        //this.removeTile(tile);
        //dojo.place(`<div id="tile${tile.id}" class="tile tile${tile.type}" style="left: ${left}px; top: ${top}px;"></div>`, destinationId);
        const tileDiv = document.getElementById(`tile${tile.id}`);
        if (tileDiv) {
            return slideToObjectAndAttach(this, tileDiv, destinationId, left, top, rotation);
        } else {
            dojo.place(`<div id="tile${tile.id}" class="tile tile${tile.type}" data-id="${tile.id}" data-type="${tile.type}" style="${left !== undefined ? `left: ${left}px;` : ''}${top !== undefined ? `top: ${top}px;` : ''}" data-rotation="${rotation ?? 0}"></div>`, destinationId);
            const newTileDiv = document.getElementById(`tile${tile.id}`);
            newTileDiv.style.setProperty('--rotation', `${rotation ?? 0}deg`);
            newTileDiv.addEventListener('click', () => {
                this.onTileClick(tile);
                this.factories.tileMouseLeave(tile.id);
            });
            newTileDiv.addEventListener('mouseenter', () => this.factories.tileMouseEnter(tile.id));
            newTileDiv.addEventListener('mouseleave', () => this.factories.tileMouseLeave(tile.id));

            return Promise.resolve(true);
        }
        
    }

    private removeGhostTile() {
        if (!this.gamedatas.players[this.getPlayerId()]) {
            return;
        }

        // TODO remove ghost
    }

    private createPlayerPanels(gamedatas: AzulSummerPavilionGamedatas) {

        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);     

            // first player token
            dojo.place(`<div id="player_board_${player.id}_firstPlayerWrapper" class="firstPlayerWrapper disabled-shimmer"></div>`, `player_board_${player.id}`);

            if (gamedatas.firstPlayerTokenPlayerId === playerId) {
                this.placeFirstPlayerToken(gamedatas.firstPlayerTokenPlayerId);
            }
        });
    }

    private createPlayerTables(gamedatas: AzulSummerPavilionGamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === Number((this as any).player_id));
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;

        orderedPlayers.forEach(player => 
            this.createPlayerTable(gamedatas, Number(player.id))
        );
    }

    private createPlayerTable(gamedatas: AzulSummerPavilionGamedatas, playerId: number) {
        this.playersTables.push(new PlayerTable(this, gamedatas.players[playerId]));
    }

    public removeTile(tile: Tile, fadeOut?: boolean) {
        // we don't remove the FP tile, it just goes back to the center
        if (tile.type == 0) {
            const coordinates = this.factories.getCoordinatesForTile0();
            this.placeTile(tile, `factory0`, coordinates.left, coordinates.top, undefined);
        } else {
            const divElement = document.getElementById(`tile${tile.id}`);
            if (divElement) {
                if (fadeOut) {
                    const destroyedId = `${divElement.id}-to-be-destroyed`;
                    divElement.id = destroyedId;
                    (this as any).fadeOutAndDestroy(destroyedId);
                } else {
                    divElement.parentElement.removeChild(divElement);
                }
            }
        }
    }

    public removeTiles(tiles: Tile[], fadeOut?: boolean) {
        tiles.forEach(tile => this.removeTile(tile, fadeOut));
    }

    public onTileClick(tile: Tile) {
        if (this.gamedatas.gamestate.name == 'chooseTile') {
            this.takeTiles(tile.id);
        } else if (this.gamedatas.gamestate.name == 'chooseKeptTiles') {
            const divElement = document.getElementById(`tile${tile.id}`);
            if (divElement?.closest(`#player-hand-${this.getPlayerId()}`)) {
                divElement.classList.toggle('selected');
                this.updateSelectKeptTilesButton();
            }
        }
    }

    public takeTiles(id: number) {
        if(!(this as any).checkAction('takeTiles')) {
            return;
        }

        this.takeAction('takeTiles', {
            id
        });
    }

    public undoTakeTiles() {
        if(!(this as any).checkAction('undoTakeTiles')) {
            return;
        }

        this.takeAction('undoTakeTiles');
    }

    public confirmAcquire() {
        if(!(this as any).checkAction('confirmAcquire')) {
            return;
        }

        this.takeAction('confirmAcquire');
    }

    public pass() {
        if(!(this as any).checkAction('pass')) {
            return;
        }

        this.takeAction('pass');
    }

    public selectColor(color: number) {
        if(!(this as any).checkAction('selectColor')) {
            return;
        }

        this.takeAction('selectColor', {
            color
        });
    }

    public playTile(wilds: number) {
        if(!(this as any).checkAction('playTile')) {
            return;
        }

        this.takeAction('playTile', {
            wilds
        });
    }

    public confirmPlay() {
        if(!(this as any).checkAction('confirmPlay')) {
            return;
        }

        this.takeAction('confirmPlay');
    }

    public undoPlayTile() {
        if(!(this as any).checkAction('undoPlayTile')) {
            return;
        }

        this.takeAction('undoPlayTile');
    }

    public selectPlace(star: number, space: number) {
        if(!(this as any).checkAction('selectPlace')) {
            return;
        }

        this.takeAction('selectPlace', {
            star,
            space
        });

        this.removeGhostTile();
    }

    public selectKeptTiles(askConfirmation = true) {
        if(!(this as any).checkAction('selectKeptTiles')) {
            return;
        }

        const handDiv = document.getElementById(`player-hand-${this.getPlayerId()}`);
        const handTileDivs = handDiv.querySelectorAll('.tile');
        const selectedTileDivs = handDiv.querySelectorAll('.tile.selected');

        if (askConfirmation && selectedTileDivs.length < handTileDivs.length && selectedTileDivs.length < 4) {
            (this as any).confirmationDialog(
                _('You will keep ${keep} tiles and discard ${discard} tiles, when you could keep ${possible} tiles!')
                    .replace('${keep}', `<strong>${selectedTileDivs.length}</strong>`)
                    .replace('${discard}', `<strong>${handTileDivs.length - selectedTileDivs.length}</strong>`)
                    .replace('${possible}', `<strong>${Math.min(4, handTileDivs.length)}</strong>`), 
                () => this.selectKeptTiles(false)
            );
        } else {
            this.takeAction('selectKeptTiles', {
                ids: Array.from(selectedTileDivs).map((tile: HTMLElement) => Number(tile.dataset.id)).sort().join(','),
            });
        }
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/azulsummerpavilion/azulsummerpavilion/${action}.html`, data, this, () => {});
    }

    placeFirstPlayerToken(playerId: number) {
        const firstPlayerToken = document.getElementById('firstPlayerToken');
        if (firstPlayerToken) {
            this.animationManager.attachWithAnimation(
                new BgaSlideAnimation({
                    element: firstPlayerToken
                }), 
                document.getElementById(`player_board_${playerId}_firstPlayerWrapper`),
            );
        } else {
            dojo.place('<div id="firstPlayerToken" class="tile tile0"></div>', `player_board_${playerId}_firstPlayerWrapper`);

            (this as any).addTooltipHtml('firstPlayerToken', _("First Player token. Player with this token will start the next turn"));
        }
    }

    private displayScoringOnTile(tile: Tile, playerId: string | number, points: number) {
        // create a div over tile, same position and width, but no overflow hidden (that must be kept on tile for glowing effect)
        dojo.place(`<div id="tile${tile.id}-scoring" class="scoring-tile"></div>`, `player-table-${playerId}-star-${tile.star}-space-${tile.space}`);
        (this as any).displayScoring(`tile${tile.id}-scoring`, this.getPlayerColor(Number(playerId)), points, SCORE_MS);
    }

    private displayScoringOnStar(star: number, playerId: string | number, points: number) {
        (this as any).displayScoring(`player-table-${playerId}-star-${star}`, this.getPlayerColor(Number(playerId)), points, SCORE_MS);
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
            ['factoriesChanged', ANIMATION_MS],
            ['factoriesCompleted', ANIMATION_MS],
            ['tilesSelected', ANIMATION_MS],
            ['undoTakeTiles', ANIMATION_MS],
            ['undoPlayTile', ANIMATION_MS],
            ['placeTileOnWall', this.gamedatas.fastScoring ? SCORE_MS : SLOW_SCORE_MS],
            ['emptyFloorLine', this.gamedatas.fastScoring ? SCORE_MS : SLOW_SCORE_MS],
            ['endScore', this.gamedatas.fastScoring ? SCORE_MS : SLOW_SCORE_MS],
            ['firstPlayerToken', 1],
            ['lastRound', 1],
        ];

        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, `notif_${notif[0]}`);
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });
    }

    notif_factoriesFilled(notif: Notif<NotifFactoriesFilledArgs>) {
        this.factories.fillFactories(notif.args.factories, notif.args.remainingTiles);
    }

    notif_factoriesChanged(notif: Notif<NotifFactoriesChangedArgs>) {
        this.factories.factoriesChanged(notif.args);
    }

    notif_factoriesCompleted(notif: Notif<NotifFactoriesChangedArgs>) {
        this.factories.factoriesCompleted(notif.args);
    }

    notif_tilesSelected(notif: Notif<NotifTilesSelectedArgs>) {
        if (notif.args.fromFactory == 0) {
            this.factories.centerColorRemoved(notif.args.selectedTiles[0].type);
        } else {
            this.factories.factoryTilesRemoved(notif.args.fromFactory);
        }
        const table = this.getPlayerTable(notif.args.playerId);
        table.placeTilesOnHand(notif.args.selectedTiles);
        this.factories.discardTiles(notif.args.discardedTiles);
    }

    notif_undoTakeTiles(notif: Notif<NotifUndoArgs>) {
        this.placeFirstPlayerToken(notif.args.undo.previousFirstPlayer);

        this.factories.undoTakeTiles(notif.args.undo.tiles, notif.args.undo.from, notif.args.factoryTilesBefore);
    }

    notif_undoPlayTile(notif: Notif<NotifUndoArgs>) {
        const table = this.getPlayerTable(notif.args.playerId);
        table.placeTilesOnHand(notif.args.undo.tiles);

        if (document.getElementById('last-round') && !notif.args.undo.lastRoundBefore) {
            dojo.destroy('last-round');
        }
    }


    /*notif_tilesPlacedOnLine(notif: Notif<NotifTilesPlacedOnLineArgs>) {
        this.getPlayerTable(notif.args.playerId).placeTilesOnLine(notif.args.discardedTiles, 0);
        this.getPlayerTable(notif.args.playerId).placeTilesOnLine(notif.args.placedTiles, notif.args.line);
    }*/
    notif_placeTileOnWall(notif: Notif<NotifPlaceTileOnWallArgs>) {
        const { playerId, placedTile, discardedTiles, scoredTiles } = notif.args;
        console.log(notif.args, playerId);
        this.getPlayerTable(playerId).placeTilesOnWall([placedTile]);
        this.removeTiles(discardedTiles, true);

        scoredTiles.forEach(tile => dojo.addClass(`tile${tile.id}`, 'highlight'));
        setTimeout(() => scoredTiles.forEach(tile => dojo.removeClass(`tile${tile.id}`, 'highlight')), SCORE_MS - 50);

        this.displayScoringOnTile(placedTile, playerId, scoredTiles.length);
        this.incScore(playerId, scoredTiles.length);
    }

    notif_emptyFloorLine(notif: Notif<NotifEmptyFloorLineArgs>) {
        Object.keys(notif.args.floorLines).forEach(playerId => {
            const floorLine: FloorLine = notif.args.floorLines[playerId];
            
            setTimeout(() => this.removeTiles(floorLine.tiles, true), SCORE_MS - 50);
            //(this as any).displayScoring(`player-table-${playerId}-line0`, this.getPlayerColor(Number(playerId)), floorLine.points, SCORE_MS);
            this.incScore(Number(playerId), floorLine.points);
        });
    }

    notif_endScore(notif: Notif<NotifEndScoreArgs>) {
        Object.keys(notif.args.scores).forEach(playerId => {
            const endScore: EndScoreTiles = notif.args.scores[playerId];

            endScore.tiles.forEach(tile => dojo.addClass(`tile${tile.id}`, 'highlight'));
            setTimeout(() => endScore.tiles.forEach(tile => dojo.removeClass(`tile${tile.id}`, 'highlight')), SCORE_MS - 50);

            this.displayScoringOnStar(endScore.star, playerId, endScore.points);
            this.incScore(Number(playerId), endScore.points);
        });
    }

    notif_firstPlayerToken(notif: Notif<NotifFirstPlayerTokenArgs>) {
        const { playerId, decScore } = notif.args;
        this.placeFirstPlayerToken(playerId);
        this.incScore(playerId, -decScore);

        this.factories.displayScoringCenter(playerId, -decScore);
    }

    notif_lastRound() {
        if (document.getElementById('last-round')) {
            return;
        }
        
        dojo.place(`<div id="last-round">${_("This is the last round of the game!")}</div>`, 'page-title');
    }

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (typeof args.lineNumber === 'number') {
                    args.lineNumber = `<strong>${args.line}</strong>`;
                }

                if (log.indexOf('${number} ${color}') !== -1 && typeof args.type === 'number') {

                    const number = args.number;
                    let html = '';
                    for (let i=0; i<number; i++) {
                        html += `<div class="tile tile${args.type}"></div>`;
                    }

                    log = _(log).replace('${number} ${color}', html);
                } else if (log.indexOf('${color}') !== -1 && typeof args.type === 'number') {
                    let html = `<div class="tile tile${args.type}"></div>`;
                    log = _(log).replace('${color}', html);
                }
            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return (this as any).inherited(arguments);
    }
}