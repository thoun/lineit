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
            <div class="block-with-text hand-wrapper">
                <div class="block-label">${_('Your hand')}</div>
                <div id="player-table-${this.playerId}-hand" class="hand cards"></div>
            </div>            
            <div class="block-with-text">
                <div class="block-label your-line">${_('Your line')}</div>`;
        }
        html += `
                <div id="player-table-${this.playerId}-line" class="line cards"></div>
                `;
        if (this.currentPlayer) {
            html += `
            </div>`;
        }
        html += `
            </div>
        </div>
        `;
        dojo.place(html, document.getElementById('tables'));

        if (this.currentPlayer) {
            this.hand = new LineStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-hand`));
            this.hand.onCardClick = (card: Card) => this.game.onHandCardClick(card);
            
            this.hand.addCards(player.hand);
        }
        
        this.line = new LineStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-line`));
        if (this.currentPlayer) {
            this.line.onCardClick = (card: Card) => this.game.onLineCardClick(card);
        }
        
        this.line.addCards(player.line);
    }

    public setSelectable(selectable: boolean, selectableCards: Card[] | null = null) {
        this.hand.setSelectionMode(selectable ? 'single' : 'none');
        this.hand.getCards().forEach(card => {
            const element = this.hand.getCardElement(card);
            const disabled = selectable && selectableCards != null && !selectableCards.some(s => s.id == card.id);
            element.classList.toggle('bga-cards_disabled-card', disabled);
            element.classList.toggle('bga-cards_selectable-card', selectable && !disabled);
        });
    }
    
    public addCardsPlaceholders(canPlaceCardOnLine: boolean, canPlaceCardOnHand: boolean) {
        const linePlaceholder = this.getPlaceholderCard('line');
        if (canPlaceCardOnLine) {
            this.line.addCard(linePlaceholder);
            this.line.getCardElement(linePlaceholder).classList.add('bga-cards_selectable-card');
        } else {
            this.line.removeCard(linePlaceholder);
        }

        const handPlaceholder = this.getPlaceholderCard('hand');
        if (canPlaceCardOnHand) {
            this.hand.addCard(handPlaceholder);
            this.hand.getCardElement(handPlaceholder).classList.add('bga-cards_selectable-card');
        } else {
            this.hand.removeCard(handPlaceholder);
        }
    }

    private getPlaceholderCard(destination: 'hand' | 'line'): Card {
        const id = destination == 'line' ? -1 : -2;
        return {
            id: id,
            type: 0,
            number: id,
        } as Card;
    }
}