class TableCenter {

    public market: LineStock<Card>;

    constructor(private game: LineItGame, gamedatas: LineItGamedatas) {
        document.getElementById(`market-title`).innerHTML = _('Market');

        this.market = new LineStock<Card>(this.game.cardsManager, document.getElementById(`market`));
        this.market.onCardClick = (card: Card) => this.game.onMarketCardClick(card);
        
        this.market.addCards(gamedatas.market);
    }
}