class TableCenter {
    public market: LineStock<Card>;

    private deckCounter: Counter;
    private jackpotCounters: Counter[] = [];

    constructor(private game: LineItGame, gamedatas: LineItGamedatas) {
        document.getElementById(`deck`).dataset.count = `${gamedatas.deck}`;
        this.deckCounter = new ebg.counter();
        this.deckCounter.create(`deck-counter`);
        this.deckCounter.setValue(gamedatas.deck);

        let html = ``;

        for (let i=1; i<=4; i++) {
            html += `
            <div id="jackpot${i}" class="deck" data-count="${gamedatas.jackpots[i].length}">
                <div class="jackpot-token" data-color="${i}"></div>
                <span id="jackpot${i}-counter" class="deck-counter"></span>
            </div>
            `;
        }
        document.getElementById(`decks`).insertAdjacentHTML('beforeend', html);
        

        for (let i=1; i<=4; i++) {
            this.jackpotCounters[i] = new ebg.counter();
            this.jackpotCounters[i].create(`jackpot${i}-counter`);
            this.jackpotCounters[i].setValue(gamedatas.jackpots[i].length);
        }

        document.getElementById(`market-title`).innerHTML = _('Market');

        this.market = new LineStock<Card>(this.game.cardsManager, document.getElementById(`market`));
        this.market.onCardClick = (card: Card) => this.game.onMarketCardClick(card);
        
        this.market.addCards(gamedatas.market);
    }

    public setSelectable(selectable: boolean, selectableCards: Card[] | null = null) {
        this.market.setSelectionMode(selectable ? 'single' : 'none');
        this.market.getCards().forEach(card => {
            const element = this.market.getCardElement(card);
            const disabled = selectable && selectableCards != null && !selectableCards.some(s => s.id == card.id);
            element.classList.toggle('disabled', disabled);
            element.classList.toggle('selectable', !disabled);
        });
    }

    public newMarket(cards: Card[]) {
        this.market.removeAll();
        this.market.addCards(cards, {
            originalSide: 'back'
        }, undefined, 50);
    }

    public setDeck(deck: number) {
        this.deckCounter.toValue(deck);
        document.getElementById(`deck`).dataset.count = `${deck}`;
    }

    public setJackpot(color: number, count: number) {
        this.jackpotCounters[color].toValue(count);
        document.getElementById(`jackpot${color}`).dataset.count = `${count}`;
    }
}