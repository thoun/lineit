class CardsManager extends CardManager<Card> {
    constructor (public game: LineItGame) {
        super(game, {
            animationManager: game.animationManager,
            getId: (card) => `card-${card.id}`,
            setupDiv: (card: Card, div: HTMLElement) => {
                div.dataset.cardId = ''+card.id;
                div.dataset.type = ''+card.type;
                div.dataset.color = ''+card.color;
                div.dataset.number = ''+card.number;
            },
            setupFrontDiv: (card: Card, div: HTMLElement) => {  
                if (card.type == 1) {
                    div.innerHTML = `
                        <div class="center-number">${card.number}</div>
                        <div class="corner-number left">${card.number}</div>
                        <div class="corner-number right">${card.number}</div>
                        <div class="corner-number rotated">${card.number}</div>
                    `;
                } else if (card.type == 0) {
                    div.innerHTML = `
                        <div class="placeholder-text">${card.number == -1 ? _("Add selected card to line") : _("Add selected card to hand")}</div>
                    `;
                }
            },
            setupBackDiv: (card: Card, div: HTMLElement) => {},
            isCardVisible: (card) => card.type > 0 || card.number < 0,
        });
    }
}