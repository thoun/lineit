/**
 * Your game interfaces
 */

interface Card {
    id: number;
    location: string;
    locationArg: number;
    type: number;
    color: number;
    number: number;
}

interface LineItPlayer extends Player {
    playerNo: number;
    hand: Card[];
    line: Card[];
}

interface LineItGamedatas {
    current_player_id: string;
    decision: {decision_type: string};
    game_result_neutralized: string;
    gamestate: Gamestate;
    gamestates: { [gamestateId: number]: Gamestate };
    neutralized_player_id: string;
    notifications: {last_packet_id: string, move_nbr: string}
    playerorder: (string | number)[];
    players: { [playerId: number]: LineItPlayer };
    tablespeed: string;

    // Add here variables you set up in getAllDatas
    market: Card[];
    jackpots: { [color: number]: Card[] };
    deck: number;
}

interface LineItGame extends Game {
    cardsManager: CardsManager;

    getPlayerId(): number;
    getPlayerColor(playerId: number): string;

    updateTableHeight(): void;
    setTooltip(id: string, html: string): void;
    onHandCardClick(card: Card): void;
    onMarketCardClick(card: Card): void;
}

interface EnteringChooseContinueArgs {
    shouldNotStop: boolean;
}

/*interface EnteringPlayCardArgs {
    _private?: {
        cards: Card[];
    }
    cards: Card[];
    discardNumber?: number;
    remainingCardsInDeck: number;
}*/

interface NotifCardInDiscardFromDeckArgs {
    card: Card;
    discardId: number;
    remainingCardsInDeck: number;
}

interface NotifCardInHandFromDiscardArgs {
    playerId: number;
    card: Card;
    discardId: number;
    newDiscardTopCard: Card | null;
    remainingCardsInDiscard: number;
}

interface NotifCardInHandFromPickArgs {
    playerId: number;
    card?: Card;
}

interface NotifCardInDiscardFromPickArgs {
    playerId: number;
    card: Card;
    discardId: number;
    remainingCardsInDiscard: number;
}

interface NotifScoreArgs {
    playerId: number;
    newScore: number;
    incScore: number;
}

interface NotifPlayCardsArgs {
    playerId: number;
    cards: Card[];
}

interface NotifRevealHandArgs extends NotifPlayCardsArgs {
    playerPoints: number;
}

interface NotifAnnounceEndRoundArgs {
    playerId: number;
    announcement: string;
}

interface NotifBetResultArgs {
    playerId: number;
    result: string;
}

interface NotifUpdateCardsPointsArgs {
    cardsPoints: number;
}

interface NotifStealCardArgs {
    playerId: number;
    opponentId: number;
    card: Card;
}
