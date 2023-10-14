<?php

/*
 * State constants
 */
const ST_BGA_GAME_SETUP = 1;

const ST_FILL_FACTORIES = 10;

const ST_PLAYER_CHOOSE_TILE = 20;
const ST_PLAYER_CONFIRM_ACQUIRE = 25;

const ST_NEXT_PLAYER_ACQUIRE = 30;

const ST_PLAYER_CHOOSE_PLACE = 40;
const ST_PLAYER_PLAY_TILE = 42;
const ST_PLAYER_CONFIRM_PLAY = 70;

const ST_NEXT_PLAYER_PLAY = 80;

const ST_END_ROUND = 90;

const ST_END_SCORE = 95;

const ST_END_GAME = 99;
const END_SCORE = 100;

/*
 * Options
 */

define('VARIANT_OPTION', 'VariantOption');
define('UNDO', 'Undo');
define('FAST_SCORING', 'FastScoring');

/*
 * Variables
 */

const FIRST_PLAYER_FOR_NEXT_TURN = 'FirstPlayerForNextTurn';

/*
 * Global variables
 */

const UNDO_SELECT = 'UndoSelect';
const UNDO_PLACE = 'UndoPlace';
const SELECTED_PLACE = 'SelectedPlace';

?>
