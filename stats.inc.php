<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * AzulSummerPavilion implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * stats.inc.php
 *
 * AzulSummerPavilion game statistics description
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
    "turnsNumber" => [
        "id" => 10,
        "name" => totranslate("Number of turns"),
        "type" => "int"
    ], 
    "normalTilesCollected" => [
        "id" => 12,
        "name" => totranslate("Normal tiles collected"),
        "type" => "int"
    ],
    "wildTilesCollected" => [
        "id" => 13,
        "name" => totranslate("Wild tiles collected"),
        "type" => "int"
    ],
    "bonusTilesCollected" => [
        "id" => 14,
        "name" => totranslate("Bonus tiles collected"),
        "type" => "int"
    ],
    "bonusTile1" => [
        "id" => 16,
        "name" => totranslate("Bonus tiles with pillars"),
        "type" => "int"
    ],
    "bonusTile2" => [
        "id" => 17,
        "name" => totranslate("Bonus tiles with statues"),
        "type" => "int"
    ],
    "bonusTile3" => [
        "id" => 18,
        "name" => totranslate("Bonus tiles with windows"),
        "type" => "int"
    ],

    "pointsWallTile" => [
        "id" => 20,
        "name" => totranslate("Points gained with placed tiles"),
        "type" => "int"
    ], 
    "pointsLossDiscardedTiles" => [
        "id" => 25,
        "name" => totranslate("Points lost with discarded tiles"),
        "type" => "int"
    ], 
    "pointsLossFirstTile" => [
        "id" => 26,
        "name" => totranslate("Points lost with taken First Player tile"),
        "type" => "int"
    ], 

    "pointsCompleteStars" => [
        "id" => 29,
        "name" => totranslate("Points gained with complete stars"),
        "type" => "int"
    ],  
    "pointsCompleteStars0" => [
        "id" => 30,
        "name" => totranslate("Points gained with complete multicolor stars"),
        "type" => "int"
    ], 
    "pointsCompleteStars1" => [
        "id" => 31,
        "name" => totranslate("Points gained with complete Fuschia stars"),
        "type" => "int"
    ], 
    "pointsCompleteStars2" => [
        "id" => 32,
        "name" => totranslate("Points gained with complete Green stars"),
        "type" => "int"
    ], 
    "pointsCompleteStars3" => [
        "id" => 33,
        "name" => totranslate("Points gained with complete Orange stars"),
        "type" => "int"
    ], 
    "pointsCompleteStars4" => [
        "id" => 34,
        "name" => totranslate("Points gained with complete Yellow stars"),
        "type" => "int"
    ], 
    "pointsCompleteStars5" => [
        "id" => 35,
        "name" => totranslate("Points gained with complete Blue stars"),
        "type" => "int"
    ], 
    "pointsCompleteStars6" => [
        "id" => 36,
        "name" => totranslate("Points gained with complete Red stars"),
        "type" => "int"
    ], 

    "pointsCompleteNumbers" => [
        "id" => 40,
        "name" => totranslate("Points gained with complete numbers"),
        "type" => "int"
    ],  
    "pointsCompleteNumbers1" => [
        "id" => 41,
        "name" => totranslate("Points gained with complete numbers 1"),
        "type" => "int"
    ], 
    "pointsCompleteNumbers2" => [
        "id" => 42,
        "name" => totranslate("Points gained with complete numbers 2"),
        "type" => "int"
    ], 
    "pointsCompleteNumbers3" => [
        "id" => 43,
        "name" => totranslate("Points gained with complete numbers 3"),
        "type" => "int"
    ], 
    "pointsCompleteNumbers4" => [
        "id" => 44,
        "name" => totranslate("Points gained with complete numbers 4"),
        "type" => "int"
    ], 
];

$stats_type = [

    // Statistics global to table
    "table" => $commonStats + [
        "roundsNumber" => [
            "id" => 11,
            "name" => totranslate("Number of rounds"),
            "type" => "int"   
        ],
    ],
    
    // Statistics existing for each player
    "player" => $commonStats + [
        "firstPlayer" => [
            "id" => 60,
            "name" => totranslate("Number of rounds as first player"),
            "type" => "int"   
        ],
    ],

];