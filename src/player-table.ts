const isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;;
const log = isDebug ? console.log.bind(window.console) : function () { };

class PlayerTable {
    public playerId: number;
    public hand?: LineStock<Card>;
    public line: LineStock<Card>;

    private currentPlayer: boolean;

    constructor(private game: LineItGame, player: LineItPlayer) {
        this.playerId = Number(player.id);
        this.currentPlayer = this.playerId == this.game.getPlayerId();

        let html = `
        <div id="player-table-${this.playerId}" class="player-table" style="--player-color: #${player.color};">
            <div class="name-wrapper">${player.name}</div>
        `;
        if (this.currentPlayer) {
            html += `
            <div class="hand-wrapper">
                <div class="your-hand">${_('Your hand')}</div>
                <div id="player-table-${this.playerId}-hand" class="hand cards"></div>
            </div>`;
        }
        html += `
            <div id="player-table-${this.playerId}-line" class="line cards"></div>
        </div>
        `;
        dojo.place(html, document.getElementById('tables'));

        if (this.currentPlayer) {
            this.hand = new LineStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-hand`));
            this.hand.onCardClick = (card: Card) => this.game.onHandCardClick(card);
            
            this.hand.addCards(player.hand);
        }
        
        this.line = new LineStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-line`));
        
        this.line.addCards(player.line);
    }
}