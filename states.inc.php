<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * LineIt implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * states.inc.php
 *
 * LineIt game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/
require_once("modules/php/constants.inc.php");

$basicGameStates = [

    // The initial state. Please do not modify.
    ST_BGA_GAME_SETUP => [
        "name" => "gameSetup",
        "description" => clienttranslate("Game setup"),
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => [ "" => ST_NEW_ROUND ]
    ],
   
    // Final state.
    // Please do not modify.
    ST_END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],
];

$playerActionsGameStates = [

    ST_PLAYER_CHOOSE_MARKET_CARD => [
        "name" => "chooseMarketCard",
        "description" => clienttranslate('${actplayer} must choose a market card'),
        "descriptionmyturn" => clienttranslate('${you} must choose a market card'),
        "type" => "activeplayer",
        "args" => "argChooseMarketCard",
        "action" => "stChooseMarketCard",
        "possibleactions" => [ 
            "playCardFromHand",
            "closeLine",
            "chooseMarketCardLine",
            "chooseMarketCardHand",
        ],
        "transitions" => [
            "next" => ST_PLAYER_PLAY_CARD,
            "stay" => ST_PLAYER_CHOOSE_MARKET_CARD,
        ]
    ],

    ST_PLAYER_PLAY_CARD => [
        "name" => "playCard",
        "description" => clienttranslate('${actplayer} can play a card or close the line'),
        "descriptionmyturn" => clienttranslate('${you} can play a card or close the line'),
        "descriptionOnlyClose" => clienttranslate('${actplayer} can close the line'),
        "descriptionmyturnOnlyClose" => clienttranslate('${you} can close the line'),
        "descriptionForced" => clienttranslate('${actplayer} must close the line'),
        "descriptionmyturnForced" => clienttranslate('${you} must close the line'),
        "type" => "activeplayer",    
        "args" => "argPlayCard",
        "action" => "stPlayCard",
        "possibleactions" => [ 
            "playCardFromHand",
            "closeLine",
            "pass",
        ],
        "transitions" => [
            "next" => ST_NEXT_PLAYER,
            "stay" => ST_PLAYER_PLAY_CARD,
        ],
    ],

    ST_PLAYER_END_PLAY_HAND_CARD => [
        "name" => "playHandCard",
        "description" => clienttranslate('${actplayer} can play a card (end game)'),
        "descriptionmyturn" => clienttranslate('${you} can play a card (end game)'),
        "type" => "activeplayer",    
        "args" => "argPlayHandCard",
        "action" => "stPlayHandCard",
        "possibleactions" => [ 
            "playCardFromHand",
            "pass",
        ],
        "transitions" => [
            "next" => ST_END_NEXT_PLAYER,
        ],
    ],
];

$gameGameStates = [

    ST_NEW_ROUND => [
        "name" => "newRound",
        "description" => "",
        "type" => "game",
        "action" => "stNewRound",
        "updateGameProgression" => true,
        "transitions" => [
            "next" => ST_PLAYER_CHOOSE_MARKET_CARD,
        ],
    ],

    ST_NEXT_PLAYER => [
        "name" => "nextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayer",
        "transitions" => [
            "nextPlayer" => ST_PLAYER_CHOOSE_MARKET_CARD,
            "endRound" => ST_END_ROUND,
        ],
    ],

    ST_END_ROUND => [
        "name" => "endRound",
        "description" => "",
        "type" => "game",
        "action" => "stEndRound",
        "updateGameProgression" => true,
        "transitions" => [
            "newRound" => ST_NEW_ROUND,
            "endDeck" => ST_END_DECK,
        ],
    ],

    ST_END_DECK => [
        "name" => "endDeck",
        "description" => "",
        "type" => "game",
        "action" => "stEndDeck",
        "updateGameProgression" => true,
        "transitions" => [
            "next" => ST_PLAYER_END_PLAY_HAND_CARD,
            "endScore" => ST_END_SCORE,
        ],
    ],

    ST_END_NEXT_PLAYER => [
        "name" => "endNextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stEndNextPlayer",
        "transitions" => [
            "nextPlayer" => ST_PLAYER_END_PLAY_HAND_CARD,
            "endScore" => ST_END_SCORE,
        ],
    ],

    ST_END_SCORE => [
        "name" => "endScore",
        "description" => "",
        "type" => "game",
        "action" => "stEndScore",
        "transitions" => [
            "endGame" => ST_END_GAME,
        ],
    ],
];
 
$machinestates = $basicGameStates + $playerActionsGameStates + $gameGameStates;



