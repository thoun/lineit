class CardsManager extends CardManager<Card> {
    constructor (public game: LineItGame) {
        super(game, {
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
                }
            },
            setupBackDiv: (card: Card, div: HTMLElement) => {}
        });
    }

    public placeHelmetOnCard(card: Card, playerId: number) {
        /*const cardType = card.mimicType || card.type;

        if (![28, 41].includes(cardType)) {
            return;
        }

        const divId = this.getId(card);
        const div = document.getElementById(divId).getElementsByClassName('front')[0] as HTMLDivElement;
        if (!div) {
            return;
        }
        const cardPlaced: CardPlacedTokens = div.dataset.placed ? JSON.parse(div.dataset.placed) : { tokens: []};
        const placed: PlacedTokens[] = cardPlaced.tokens;


        // remove tokens
        for (let i = card.tokens; i < placed.length; i++) {
            if (cardType === 28 && playerId) {
                (this.game as any).slideToObjectAndDestroy(`${divId}-token${i}`, `energy-counter-${playerId}`);
            } else {
                (this.game as any).fadeOutAndDestroy(`${divId}-token${i}`);
            }
        }
        placed.splice(card.tokens, placed.length - card.tokens);

        // add tokens
        for (let i = placed.length; i < card.tokens; i++) {
            const newPlace = this.getPlaceOnCard(cardPlaced);

            placed.push(newPlace);
            let html = `<div id="${divId}-token${i}" style="left: ${newPlace.x - 16}px; top: ${newPlace.y - 16}px;" class="card-token `;
            if (cardType === 28) {
                html += `energy-cube cube-shape-${Math.floor(Math.random()*5)}`;
            } else if (cardType === 41) {
                html += `smoke-cloud token`;
            }
            html += `"></div>`;
            div.insertAdjacentHTML('beforeend', html);
        }

        div.dataset.placed = JSON.stringify(cardPlaced);*/
    }
}