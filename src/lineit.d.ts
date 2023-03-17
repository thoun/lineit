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
    firstPlayerId: number;
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

interface EnteringChooseMarketCardArgs {
    canPlaceOnLine: Card[];
    canAddToLine: boolean;
    canAddToHand: boolean;
}

interface EnteringPlayCardArgs {
    canPlaceOnLine: Card[];
    canClose: boolean;
    mustClose: boolean;
}

interface EnteringPlayHandCardArgs {
    canPlaceOnLine: Card[];
}

// newMarket
interface NotifNewMarketArgs {
    cards: Card[];
    deck: number;
}

// chooseMarketCardHand
interface NotifChooseMarketCardHandArgs {
    playerId: number;
    card: Card;
}

// jackpotRemaining, discardRemaining
interface NotifJackpotRemainingArgs {
    color: number;
    card: Card;
}
// newFirstPlayer
interface NotifNewFirstPlayerArgs {
    playerId: number;
}  

// playCard
interface NotifPlayCardArgs {
    playerId: number;
    card: Card;
} 

// applyJackpot
interface NotifApplyJackpotArgs {
    playerId: number;
    color: number;
    count: number | string;
}

// betResult
interface NotifBetResultArgs {
    playerId: number;
    value: number;
}

// closeLine
interface NotifApplyJackpotArgs {
    playerId: number;
    count: number | string;
    removed: number | string;
}