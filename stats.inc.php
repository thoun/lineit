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
 * stats.inc.php
 *
 * LineIt game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/

$commonStats = [
    "increasingLines" => [
        "id" => 15,
        "name" => totranslate("Increasing numbers lines"),
        "type" => "int"
    ],
    "decreasingLines" => [
        "id" => 16,
        "name" => totranslate("Decreasing numbers lines"),
        "type" => "int"
    ],

    "marketToHand" => [
        "id" => 20,
        "name" => totranslate("Market cards added to hand"),
        "type" => "int"
    ],
    "marketToLine" => [
        "id" => 21,
        "name" => totranslate("Market cards added to hand"),
        "type" => "int"
    ],
    "playedCardFromHand" => [
        "id" => 22,
        "name" => totranslate("Cards played from hand"),
        "type" => "int"
    ],

    "closedLines" => [
        "id" => 30,
        "name" => totranslate("Closed lines"),
        "type" => "int"
    ],
    "closedLinesForced" => [
        "id" => 31,
        "name" => totranslate("Closed lines (forced)"),
        "type" => "int"
    ],
    
    "betCardsPlayed" => [
        "id" => 40,
        "name" => totranslate("Bet cards played"),
        "type" => "int"
    ],
    "betWon" => [
        "id" => 41,
        "name" => totranslate("Bet won"),
        "type" => "int"
    ],
    "betLost" => [
        "id" => 42,
        "name" => totranslate("Bet lost"),
        "type" => "int"
    ],
    
    "jackpotCollected" => [
        "id" => 50,
        "name" => totranslate("Jackpot collected"),
        "type" => "int"
    ],

    "pointsFromJackpots" => [
        "id" => 60,
        "name" => totranslate("Points from jackpot cards"),
        "type" => "int"
    ],
    "pointsFromLines" => [
        "id" => 61,
        "name" => totranslate("Points from scored line cards"),
        "type" => "int"
    ],
    "pointsFromBet" => [
        "id" => 62,
        "name" => totranslate("Points from bets"),
        "type" => "int"
    ],
];

$stats_type = [
    // Statistics global to table
    "table" => $commonStats + [
        "roundNumber" => [
            "id" => 10,
            "name" => totranslate("Number of rounds"),
            "type" => "int"
        ],
    ],
    
    // Statistics existing for each player
    "player" => $commonStats + [
    ],
];
