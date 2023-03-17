declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;

const ANIMATION_MS = 500;
const ACTION_TIMER_DURATION = 5;

const LOCAL_STORAGE_ZOOM_KEY = 'LineIt-zoom';

class LineIt implements LineItGame {
    public cardsManager: CardsManager;

    private zoomManager: ZoomManager;
    private animationManager: AnimationManager;
    private gamedatas: LineItGamedatas;
    private tableCenter: TableCenter;
    private playersTables: PlayerTable[] = [];
    private handCounters: Counter[] = [];
    private selectedCardId: number;
    
    private TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;

    constructor() {
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

    public setup(gamedatas: LineItGamedatas) {
        log( "Starting game setup" );
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);

        this.cardsManager = new CardsManager(this);
        this.animationManager = new AnimationManager(this);
        this.tableCenter = new TableCenter(this, gamedatas);
        this.createPlayerPanels(gamedatas);
        this.createPlayerTables(gamedatas);

        
        this.zoomManager = new ZoomManager({
            element: document.getElementById('table'),
            smooth: false,
            zoomControls: {
                color: 'white',
            },
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
        });

        this.setupNotifications();
        this.setupPreferences();

        (this as any).onScreenWidthChange = () => {
            this.updateTableHeight();
            this.onTableCenterSizeChange();
        };

        log( "Ending game setup" );
    }

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    public onEnteringState(stateName: string, args: any) {
        log('Entering state: ' + stateName, args.args);

        switch (stateName) {
            case 'chooseMarketCard':
                this.onEnteringChooseMarketCard(args.args);
                break;
           case 'playCard':
                this.onEnteringPlayCard(args.args);
                break;
            /*case 'putDiscardPile':
                this.onEnteringPutDiscardPile(args.args);
                break;
            case 'playCards':
                this.onEnteringPlayCards();
                break;
            case 'chooseDiscardPile':
                this.onEnteringChooseDiscardPile();
                break;
            case 'chooseDiscardCard':
                this.onEnteringChooseDiscardCard(args.args);
                break;
            case 'chooseOpponent':
                this.onEnteringChooseOpponent(args.args);
                break;*/
        }
    }
    
    private setGamestateDescription(property: string = '') {
        const originalState = this.gamedatas.gamestates[this.gamedatas.gamestate.id];
        this.gamedatas.gamestate.description = `${originalState['description' + property]}`; 
        this.gamedatas.gamestate.descriptionmyturn = `${originalState['descriptionmyturn' + property]}`;
        (this as any).updatePageTitle();
    }
    
    private onEnteringChooseMarketCard(args: EnteringChooseMarketCardArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            this.selectedCardId = null;
            this.tableCenter.setSelectable(true, args.canAddToHand ? null : args.canPlaceOnLine);
        }
    }
    
    private onEnteringPlayCard(args: EnteringPlayCardArgs) {
        if (args.mustClose) {
            this.setGamestateDescription(`Forced`);
        }
    }

    public onLeavingState(stateName: string) {
        log( 'Leaving state: '+stateName );

        switch (stateName) {
           case 'chooseMarketCard':
                this.selectedCardId = null;
                this.tableCenter.setSelectable(false);
                break;
        }
    }

   /* private onLeavingTakeCards() {
        this.stacks.makeDeckSelectable(false);
        this.stacks.makeDiscardSelectable(false);
    }
    
    private onLeavingChooseCard() {
        this.stacks.makePickSelectable(false);
    }

    private onLeavingPutDiscardPile() {
        this.stacks.makeDiscardSelectable(false);
    }

    private onLeavingPlayCards() {
        this.selectedCards = null;
        this.getCurrentPlayerTable()?.setSelectable(false);
    }

    private onLeavingChooseDiscardCard() {
        const pickDiv = document.getElementById('discard-pick');
        pickDiv.dataset.visible = 'false';
        this.updateTableHeight();
    }*/

    private onLeavingChooseOpponent() {
        (Array.from(document.querySelectorAll('[data-can-steal]')) as HTMLElement[]).forEach(elem => elem.dataset.canSteal = 'false');
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        if ((this as any).isCurrentPlayerActive()) {
            switch (stateName) {
                case 'chooseMarketCard':
                    this.selectedCardId = null;
                    (this as any).addActionButton(`addLine_button`, _("Add selected card to line"), () => this.chooseMarketCardLine());
                    (this as any).addActionButton(`addHand_button`, _("Add selected card to hand"), () => this.chooseMarketCardHand());
                    [`addLine_button`, `addHand_button`].forEach(id => document.getElementById(id).classList.add('disabled'));
                    break;
                case 'playCard':
                    const playCardArgs = args as EnteringPlayCardArgs;
                    (this as any).addActionButton(`closeLine_button`, _("Close the line"), () => this.closeLine(), null, null, 'red');
                    (this as any).addActionButton(`pass_button`, _("Pass"), () => this.pass());
                    if (!playCardArgs.canClose) {
                        document.getElementById(`closeLine_button`).classList.add('disabled');
                    }                    
                    if (playCardArgs.mustClose) {
                        document.getElementById(`pass_button`).classList.add('disabled');
                    }
                    break;
            }
        }
    }

    ///////////////////////////////////////////////////
    //// Utility methods


    ///////////////////////////////////////////////////

    public setTooltip(id: string, html: string) {
        (this as any).addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    public setTooltipToClass(className: string, html: string) {
        (this as any).addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }

    public getPlayerId(): number {
        return Number((this as any).player_id);
    }

    public getPlayerColor(playerId: number): string {
        return this.gamedatas.players[playerId].color;
    }

    private getPlayer(playerId: number): LineItPlayer {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    private getCurrentPlayerTable(): PlayerTable | null {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
    }

    public updateTableHeight() {
        // setTimeout(() => document.getElementById('zoom-wrapper').style.height = `${document.getElementById('full-table').getBoundingClientRect().height}px`, 600);
    }

    private onTableCenterSizeChange() {
        /*const maxWidth = document.getElementById('full-table').clientWidth;
        const tableCenterWidth = document.getElementById('table-center').clientWidth + 20;
        const playerTableWidth = 650 + 20;
        const tablesMaxWidth = maxWidth - tableCenterWidth;
     
        let width = 'unset';
        if (tablesMaxWidth < playerTableWidth * this.gamedatas.playerorder.length) {
            const reduced = (Math.floor(tablesMaxWidth / playerTableWidth) * playerTableWidth);
            if (reduced > 0) {
                width = `${reduced}px`;
            }
        }
        document.getElementById('tables').style.width = width;*/
    }

    private setupPreferences() {
        // Extract the ID and value from the UI control
        const onchange = (e) => {
          var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
          if (!match) {
            return;
          }
          var prefId = +match[1];
          var prefValue = +e.target.value;
          (this as any).prefs[prefId].value = prefValue;
        }
        
        // Call onPreferenceChange() when any value changes
        dojo.query(".preference_control").connect("onchange", onchange);
        
        // Call onPreferenceChange() now
        dojo.forEach(
          dojo.query("#ingame_menu_content .preference_control"),
          el => onchange({ target: el })
        );
    }

    private getOrderedPlayers(gamedatas: LineItGamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === Number((this as any).player_id));
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        return orderedPlayers;
    }

    private createPlayerPanels(gamedatas: LineItGamedatas) {

        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);   

            // hand cards counter
            dojo.place(`<div class="counters">
                <div id="playerhand-counter-wrapper-${player.id}" class="playerhand-counter">
                    <div class="player-hand-card"></div> 
                    <span id="playerhand-counter-${player.id}"></span>
                </div>
            </div>`, `player_board_${player.id}`);

            const handCounter = new ebg.counter();
            handCounter.create(`playerhand-counter-${playerId}`);
            handCounter.setValue(player.hand.length);
            this.handCounters[playerId] = handCounter;

            // first player
            dojo.place(`<div id="first-player-token-wrapper-${player.id}" class="first-player-token-wrapper"></div>`, `player_board_${player.id}`);
            if (gamedatas.firstPlayerId == playerId) {
                dojo.place(`<div id="first-player-token" class="first-player-token"></div>`, `first-player-token-wrapper-${player.id}`);
            }
        });

        this.setTooltipToClass('playerhand-counter', _('Number of cards in hand'));
    }

    private createPlayerTables(gamedatas: LineItGamedatas) {
        const orderedPlayers = this.getOrderedPlayers(gamedatas);

        orderedPlayers.forEach(player => 
            this.createPlayerTable(gamedatas, Number(player.id))
        );
    }

    private createPlayerTable(gamedatas: LineItGamedatas, playerId: number) {
        const table = new PlayerTable(this, gamedatas.players[playerId]);
        this.playersTables.push(table);
    }

    public onMarketCardClick(card: Card): void {
        const args: EnteringChooseMarketCardArgs = this.gamedatas.gamestate.args;
        if (!args.canAddToHand && !args.canPlaceOnLine.some(s => s.id == card.id)) {
            return;
        }

        this.selectedCardId = card.id;
        document.getElementById(`addLine_button`).classList.toggle('disabled', !(args.canAddToLine && args.canPlaceOnLine.some(s => s.id == card.id)));
        document.getElementById(`addHand_button`).classList.toggle('disabled', !args.canAddToHand);
    }

    public onHandCardClick(card: Card): void {
        this.playCardFromHand(card.id);
    }
  	
    public playCardFromHand(id: number) {
        if(!(this as any).checkAction('playCardFromHand')) {
            return;
        }

        this.takeAction('playCardFromHand', {
            id
        });
    }
  	
    public chooseMarketCardLine() {
        if(!(this as any).checkAction('chooseMarketCardLine')) {
            return;
        }

        this.takeAction('chooseMarketCardLine', {
            id: this.selectedCardId,
        });
    }
  	
    public chooseMarketCardHand() {
        if(!(this as any).checkAction('chooseMarketCardHand')) {
            return;
        }

        this.takeAction('chooseMarketCardHand', {
            id: this.selectedCardId,
        });
    }
  	
    public closeLine() {
        if(!(this as any).checkAction('closeLine')) {
            return;
        }

        this.takeAction('closeLine');
    }
  	
    public pass() {
        if(!(this as any).checkAction('pass')) {
            return;
        }

        this.takeAction('pass');
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/lineit/lineit/${action}.html`, data, this, () => {});
    }

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your pylos.game.php file.

    */
    setupNotifications() {
        //log( 'notifications subscriptions setup' );

        const notifs = [
            ['newMarket', ANIMATION_MS],
            ['chooseMarketCardHand', ANIMATION_MS],
            ['jackpotRemaining', 100],
            ['discardRemaining', 100],
            ['newFirstPlayer', ANIMATION_MS],
            ['playCard', ANIMATION_MS],
            ['applyJackpot', ANIMATION_MS],
            ['betResult', ANIMATION_MS],
            ['closeLine', ANIMATION_MS],
        ];
    
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, `notif_${notif[0]}`);
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });
    }

    notif_newMarket(notif: Notif<NotifNewMarketArgs>) {
        this.tableCenter.newMarket(notif.args.cards);
        this.tableCenter.setDeck(notif.args.deck);
    }

    notif_chooseMarketCardHand(notif: Notif<NotifChooseMarketCardHandArgs>) {
        if (notif.args.playerId == this.getPlayerId()) {
            this.getPlayerTable(notif.args.playerId).hand.addCard(notif.args.card);
        } else {
            this.tableCenter.market.removeCard(notif.args.card);
        }
    }

    notif_jackpotRemaining(notif: Notif<NotifJackpotRemainingArgs>) {
        console.log('jackpotRemaining', notif.args);
    }

    notif_discardRemaining(notif: Notif<NotifJackpotRemainingArgs>) {
        console.log('discardRemaining', notif.args);
    }

    notif_newFirstPlayer(notif: Notif<NotifNewFirstPlayerArgs>) {
        const firstPlayerToken = document.getElementById('first-player-token');
        const destinationId = `first-player-token-wrapper-${notif.args.playerId}`;
        const originId = firstPlayerToken.parentElement.id;
        if (destinationId !== originId) {
            this.animationManager.attachWithSlideAnimation(
                firstPlayerToken,
                document.getElementById(destinationId),
                { zoom: 1 },
            );
        }
    }

    notif_playCard(notif: Notif<NotifPlayCardArgs>) {
        this.getPlayerTable(notif.args.playerId).hand.addCard(notif.args.card);
    }

    notif_applyJackpot(notif: Notif<NotifApplyJackpotArgs>) {
        console.log('applyJackpot', notif.args);
    }

    notif_betResult(notif: Notif<NotifBetResultArgs>) {
        console.log('betResult', notif.args);
    }

    notif_closeLine(notif: Notif<NotifApplyJackpotArgs>) {
        console.log('closeLine', notif.args);
    }


    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (args.cardValue == '' && args.card) {
                    args.cardValue = `<strong data-color="${args.card.color}">${args.card.type == 2 && args.card.number > 0 ? '+' : ''}${args.card.number}</strong>`;
                }
                if (typeof args.colorName == 'string' && args.colorName[0] !== '<' && args.color) {
                    args.colorName = `<div class="jackpot-icon" data-color="${args.color}"></div>`;
                }
            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return (this as any).inherited(arguments);
    }
}