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
 * states.inc.php
 *
 * AzulSummerPavilion game states description
 *
 */

use Bga\GameFramework\GameStateBuilder;

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

//    !! It is not a good idea to modify this file when a game is running !!
require_once("modules/php/constants.inc.php");

$basicGameStates = [

    // The initial state. Please do not modify.
    ST_BGA_GAME_SETUP => GameStateBuilder::gameSetup(ST_FILL_FACTORIES)->build(),
];

$playerActionsGameStates = [

    ST_PLAYER_CONFIRM_ACQUIRE => [
        "name" => "confirmAcquire",
        "description" => clienttranslate('${actplayer} must confirm acquired tiles'),
        "descriptionmyturn" => clienttranslate('${you} must confirm acquired tiles'),
        "type" => "activeplayer",
        "possibleactions" => [ 
            "actConfirmAcquire",
            "actUndoTakeTiles",
         ],
        "transitions" => [
            "nextPlayer" => ST_NEXT_PLAYER_ACQUIRE,
            "undo" => ST_PLAYER_CHOOSE_TILE,
        ],
    ],

    ST_PLAYER_CHOOSE_PLACE => [
        "name" => "choosePlace",
        "description" => clienttranslate('${actplayer} must choose a space to place a tile'),
        "descriptionmyturn" => clienttranslate('${you} must choose a space to place a tile'),
        "type" => "activeplayer",
        "args" => "argChoosePlace",
        "action" => "stChoosePlace",
        "possibleactions" => [ 
            "actSelectPlace",
            "actPass",
         ],
        "transitions" => [],
    ],

    ST_PLAYER_CHOOSE_KEPT_TILES => [
        "name" => "chooseKeptTiles",
        "description" => clienttranslate('${actplayer} may choose up to 4 tiles to keep'),
        "descriptionmyturn" => clienttranslate('${you} may choose up to 4 tiles to keep'),
        "type" => "activeplayer",
        "possibleactions" => [ 
            "actSelectKeptTiles",
            "actUndoPass",
        ],
        "transitions" => [
            "undo" => ST_PLAYER_CHOOSE_PLACE,
        ],
    ],

    ST_PLAYER_CONFIRM_PASS => [
        "name" => "confirmPass",
        "description" => clienttranslate('${actplayer} must confirm ending the round'),
        "descriptionmyturn" => clienttranslate('${you} must confirm ending the round'),
        "type" => "activeplayer",
        "possibleactions" => [ 
            "actConfirmPass",
            "actUndoPass",
         ],
        "transitions" => [
            "undo" => ST_PLAYER_CHOOSE_PLACE,
        ],
    ],

    ST_PLAYER_CHOOSE_COLOR => [
        "name" => "chooseColor",
        "description" => clienttranslate('${actplayer} must choose a color to place'),
        "descriptionmyturn" => clienttranslate('${you} must choose a color to place'),
        "type" => "activeplayer",
        "args" => "argChooseColor",
        "possibleactions" => [ 
            "actSelectColor",
            "actUndoPlayTile",
            "actPass",
         ],
        "transitions" => [
            "undo" => ST_PLAYER_CHOOSE_PLACE,
        ],
    ],

    ST_PLAYER_PLAY_TILE => [
        "name" => "playTile",
        "description" => clienttranslate('${actplayer} must choose the number of wild tiles to use'),
        "descriptionmyturn" => clienttranslate('${you} must choose the number of wild tiles to use'),
        "type" => "activeplayer",
        "args" => "argPlayTile",
        "possibleactions" => [ 
            "actPlayTile",
            "actUndoPlayTile",
         ],
        "transitions" => [
            "undo" => ST_PLAYER_CHOOSE_PLACE,
        ],
    ],

    ST_PLAYER_TAKE_BONUS_TILES => [
        "name" => "takeBonusTiles",
        "description" => clienttranslate('${actplayer} must take ${number} bonus tiles'),
        "descriptionmyturn" => clienttranslate('${you} must take ${number} bonus tiles'),
        "type" => "activeplayer",
        "args" => "argTakeBonusTiles",
        "possibleactions" => [ 
            "actTakeBonusTiles",
            "actUndoPlayTile",
         ],
        "transitions" => [
            "undo" => ST_PLAYER_CHOOSE_PLACE,
        ],
    ],

    ST_PLAYER_CONFIRM_PLAY => [
        "name" => "confirmPlay",
        "description" => clienttranslate('${actplayer} must confirm played tile'),
        "descriptionmyturn" => clienttranslate('${you} must confirm played tile'),
        "type" => "activeplayer",
        "args" => "argConfirmPlay",
        "possibleactions" => [ 
            "actConfirmPlay",
            "actUndoPlayTile",
         ],
        "transitions" => [
            "undo" => ST_PLAYER_CHOOSE_PLACE,
        ],
    ],
];
 
$machinestates = $basicGameStates + $playerActionsGameStates;
