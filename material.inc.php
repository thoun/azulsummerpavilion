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
 * material.inc.php
 *
 * AzulSummerPavilion game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

 $this->factoriesByPlayers = [
  2 => 5,
  3 => 7,
  4 => 9,
];

$this->STANDARD_FACE_STAR_COLORS = [
  0 => 0,
  1 => 1,
  2 => 3,
  3 => 6,
  4 => 5, 
  5 => 4, 
  6 => 2,
];


$this->FULL_STAR_POINTS_BY_COLOR = [
  0 => 12,
  1 => 20,
  2 => 18,
  3 => 17,
  4 => 16, 
  5 => 15, 
  6 => 14,
];