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
  * azulsummerpavilion.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */
namespace Bga\Games\AzulSummerPavilion;

require_once('framework-prototype/Helpers/Arrays.php');

require_once('constants.inc.php');
require_once('tile.php');
require_once('undo.php');
require_once('utils.php');

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Components\Deck;
use Bga\GameFramework\Table;
use Bga\GameFrameworkPrototype\Helpers\Arrays;
use Bga\Games\AzulSummerPavilion\Boards\Board;
use Bga\Games\AzulSummerPavilion\States\ChooseKeptTiles;
use Bga\Games\AzulSummerPavilion\States\ChoosePlace;
use Bga\Games\AzulSummerPavilion\States\ConfirmPass;
use Bga\Games\AzulSummerPavilion\States\ConfirmPlay;
use Bga\Games\AzulSummerPavilion\States\FillFactories;
use Bga\Games\AzulSummerPavilion\States\NextPlayerAcquire;
use Bga\Games\AzulSummerPavilion\States\NextPlayerPlay;
use Bga\Games\AzulSummerPavilion\States\TakeBonusTiles;
use UndoPlace;

class Game extends Table {
    use \UtilTrait;
    use DebugUtilTrait;

    public Deck $tiles;
    public Board $board;

    public array $factoriesByPlayers;

	function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels([
            FIRST_PLAYER_FOR_NEXT_TURN => 10,
        ]);

        $this->tiles = $this->deckFactory->createDeck("tile");
        $this->tiles->autoreshuffle = true; 
        
        
        $this->factoriesByPlayers = [
            2 => 5,
            3 => 7,
            4 => 9,
        ];
	}

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = []) {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, player_score) VALUES ";
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."', 5)";
        }
        $sql .= implode(',', $values);
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue(FIRST_PLAYER_FOR_NEXT_TURN, intval(array_keys($players)[0]));
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        self::initStat('table', 'roundsNumber', 0);        
        self::initStat('player', 'firstPlayer', 0);

        foreach(['table', 'player'] as $statType) {
            foreach(['turnsNumber', 'normalTilesCollected', 'wildTilesCollected',
            'bonusTilesCollected', 'bonusTile_pillar', 'bonusTile_statue', 'bonusTile_window',
            'pointsWallTile', 'pointsLossDiscardedTiles', 'pointsLossFirstTile',
            'pointsCompleteStars', 'pointsCompleteStars0', 'pointsCompleteStars1', 'pointsCompleteStars2', 'pointsCompleteStars3', 'pointsCompleteStars4', 'pointsCompleteStars5', 'pointsCompleteStars6',
            'pointsCompleteNumbers', 'pointsCompleteNumbers1', 'pointsCompleteNumbers2', 'pointsCompleteNumbers3', 'pointsCompleteNumbers4',
            'pointsCompleteStructureSets',
            ] as $statName) {
                $this->initStat($statType, $statName, 0);
            }
        }
        if (in_array($this->getBoardNumber(), [3, 4])) {
            foreach(['table', 'player'] as $statType) {
                $this->initStat($statType, 'bonusTile_fountain', 0);
            }
        }

        $this->setupTiles();

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        return FillFactories::class;

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas(): array {
        $result = [];
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo, passed, auto_pass autoPass FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        $result['factoryNumber'] = $this->getFactoryNumber(count($result['players']));
        $result['firstPlayerTokenPlayerId'] = intval(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));
        $result['boardNumber'] = $this->getBoardNumber();
        $result['stars'] = $this->getBoard()->getStars();
        $result['wildColors'] = $this->getBoard()->getWildColors();
        

        $factories = [];
        $factoryNumber = $result['factoryNumber'];
        for ($factory=0; $factory<=$factoryNumber; $factory++) {
            $factories[$factory] = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory', $factory));
        }
        $result['factories'] = $factories;

        foreach($result['players'] as $playerId => &$player) {
            $player['wall'] = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
            $player['playerNo'] = intval($player['playerNo']);
            $player['hand'] = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
            $player['corner'] = $this->getTilesFromDb($this->tiles->getCardsInLocation('corner', $playerId));
            $player['passed'] = boolval($player['passed']);
            $player['autoPass'] = boolval($player['autoPass']);
        }

        $result['fastScoring'] = $this->isFastScoring();
        $result['remainingTiles'] = intval($this->tiles->countCardInLocation('deck'));
        $result['round'] = $this->getRound();
        $result['supply'] = $this->getTilesFromDb($this->tiles->getCardsInLocation('supply'));
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
        $round = $this->getRound();
        $expectedTiles = $this->getFactoryNumber() * 4;
        $factoryTileCount = $this->tiles->countCardInLocation('factory');

        $inRoundProgress = 0; // 0 to 0.5 : take tiles -- 0.5 to 1 : place tiles
        if ($factoryTileCount > 0) {
            $inRoundProgress = 0.5 - min(0.5, max(0, 0.5 * $factoryTileCount / $expectedTiles));
        } else {
            $handTileCount = $this->tiles->countCardInLocation('hand');
            $inRoundProgress = 1 - min(0.5, max(0, 0.5 * $handTileCount / $expectedTiles));
        }

        return ($round - 1 + $inRoundProgress) * 100 / 6;
    }

    function argChoosePlaceForPlayer(int $playerId) {
        $placedTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $wildColor = $this->getWildColor();
        $possibleSpaces = [];
        $board = $this->getBoard();
        $remainingColorTiles = count(array_filter($hand, fn($tile) => $tile->type > 0));
        $skipIsFree = $remainingColorTiles <= ($this->getRound() >= 6 ? 0 : 4);

        for ($star = 0; $star <= 6; $star++) {

            for ($space = 1; $space <= 6; $space++) {
                if (Arrays::some($placedTiles, fn($placedTile) => $placedTile->star == $star && $placedTile->space == $space)) {
                    continue;
                }
                $spaceData = $board->getStars()[$star][$space];
                $spaceColor = $spaceData['color'];
                $spaceNumber = $spaceData['number'];

                $colors = [$spaceColor];
                if ($spaceColor <= 0) {
                    $starTiles = array_values(array_filter($placedTiles, fn($placedTile) => $placedTile->star == $star));
                    $starColors = array_map(fn($starTile) => $starTile->type, $starTiles);
                    $colors = [1, 2, 3, 4, 5, 6];
                    if ($spaceColor == -1) {
                        // non forced color, but must all be different
                        $colors = array_diff([1, 2, 3, 4, 5, 6], $starColors);
                    } else {
                        if (count($starColors) >= 2) {
                            if ($starColors[0] == $starColors[1]) {
                                $colors = [$starColors[0]];
                            } else {
                                $colors = Arrays::diff($colors, $starColors);
                            }
                        }
                    }
                }

                if (Arrays::some($colors, fn($color) => $this->getMaxWildTiles($hand, $spaceNumber, $color, $wildColor) !== null)) {
                    $possibleSpaces[] = $star * 100 + $space;
                }
            }
        }

        return [
            'possibleSpaces' => $possibleSpaces,
            'skipIsFree' => $skipIsFree,
        ];
    }

    function argChooseColor(int $activePlayerId, int $star, int $space) {
        $board = $this->getBoard();
        $spaceData = $board->getStars()[$star][$space];
        $spaceColor = $spaceData['color'];
        $spaceNumber = $spaceData['number'];

        $possibleColors = [];
        if ($spaceColor <= 0) {
            $placedTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$activePlayerId));
            $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $activePlayerId));
            $wildColor = $this->getWildColor();
            $starTiles = array_values(array_filter($placedTiles, fn($placedTile) => $placedTile->star == $star));
            $starColors = array_map(fn($starTile) => $starTile->type, $starTiles);
            $colors = array_diff([1, 2, 3, 4, 5, 6], $starColors);
            if ($spaceColor == -1) {
                // non forced color, but must all be different
            } else {
                if (count($starColors) <= 1) {
                    $colors = [1, 2, 3, 4, 5, 6];
                } else if (count($starColors) >= 2 && $starColors[0] == $starColors[1]) {
                    $colors = [$starColors[0]];
                }
            }

            foreach ($colors as $possibleColor) {
                if ($this->getMaxWildTiles($hand, $spaceNumber, $possibleColor, $wildColor) !== null) {
                    $possibleColors[] = $possibleColor;
                }
            }

        } else {
            $possibleColors = [$spaceColor];
        }
        return [
            'playerId' => $activePlayerId,
            'possibleColors' => $possibleColors,
            'star' => $star,
            'space' => $space,
            '_private' => $this->argAutopass(),
        ];
    }

    function getMaxWildTiles(array $hand, int $cost, int $color, int $wildColor) { // null if cannot pay, else number max of wild tiles that can be used (0 is still valid choice!)
        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $color));
        $wildTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $wildColor));

        if ($color == $wildColor) {
            return count($colorTiles) < $cost ? null : 0;
        } else if (count($colorTiles) + count($wildTiles) < $cost || count($colorTiles) < 1) {
            return null;
        } else {
            return min($cost - 1, count($wildTiles));
        }
    }

    function argPlayTile(int $activePlayerId) {
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $activePlayerId));

        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $star = $selectedPlace[0];
        $space = $selectedPlace[1];
        $selectedColor = $this->getGlobalVariable(SELECTED_COLOR);
        $wildColor = $this->getWildColor();

        $board = $this->getBoard();
        $spaceData = $board->getStars()[$star][$space];
        $spaceNumber = $spaceData['number'];

        $maxWildTiles = $this->getMaxWildTiles($hand, $spaceNumber, $selectedColor, $wildColor);
        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $selectedColor));

        return [
            'selectedPlace' => $selectedPlace,
            'number' => $spaceNumber,
            'color' => $selectedColor,
            'wildColor' => $wildColor,
            'maxColor' => count($colorTiles),
            'maxWildTiles' => $maxWildTiles,
            '_private' => $this->argAutopass(),
        ];
    }

    function applyConfirmPass(int $activePlayerId) {
        $undo = $this->getGlobalVariable(UNDO_PLACE);
        $count = $undo->points;
        if ($count > 0) {
            $this->incStat($count, 'pointsLossDiscardedTiles');
            $this->incStat($count, 'pointsLossDiscardedTiles', $activePlayerId);
        }

        return NextPlayerPlay::class;
    }

    function applyConfirmTiles(int $playerId) {
        $undo = $this->getGlobalVariable(UNDO_SELECT);
        $this->incStat($undo->normalTiles, 'normalTilesCollected');
        $this->incStat($undo->normalTiles, 'normalTilesCollected', $playerId);
        if ($undo->wildTile) {
            $this->incStat(1, 'wildTilesCollected');
            $this->incStat(1, 'wildTilesCollected', $playerId);
        }
        if ($undo->pointsLossFirstTile > 0) {
            $this->incStat($undo->pointsLossFirstTile, 'pointsLossFirstTile');
            $this->incStat($undo->pointsLossFirstTile, 'pointsLossFirstTile', $playerId);
            
        }
        
        return NextPlayerAcquire::class;
    }

    function applyPlayTile(int $playerId, int $wilds) {

        $variant = $this->getBoardNumber() === 2;

        // for undo
        $previousScore = $this->getPlayerScore($playerId);

        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));

        $selectedPlace = $this->getGlobalVariable(SELECTED_PLACE);
        $star = $selectedPlace[0];
        $space = $selectedPlace[1];
        $selectedColor = $this->getGlobalVariable(SELECTED_COLOR);
        $wildColor = $this->getWildColor();
        $number = $this->getSpaceNumber($star, $space, $variant);

        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $selectedColor));
        $wildTiles = array_values(array_filter($hand, fn($tile) => $tile->type == $wildColor));

        $tiles = array_merge(
            array_slice($colorTiles, 0, $number - $wilds),
            array_slice($wildTiles, 0, $wilds),
        );

        $placedTile = $tiles[0];
        $discardedTiles = array_slice($tiles, 1);
        $placedTile->star = $star;
        $placedTile->space = $space;
        $this->tiles->moveCard($placedTile->id, 'wall'.$playerId, $placedTile->star * 100 + $placedTile->space);
        $this->tiles->moveCards(array_map(fn($t) => $t->id, $discardedTiles), 'discard');

        $scoredTiles = $this->getScoredTiles($playerId, $placedTile);
        $points = count($scoredTiles);

        $this->incPlayerScore($playerId, $points);

        $this->notify->all('placeTileOnWall', clienttranslate('${player_name} places ${number} ${color} and gains ${points} point(s)'), [
            'placedTile' => $placedTile,
            'discardedTiles' => $discardedTiles,
            'scoredTiles' => $scoredTiles,
            'playerId' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'number' => 1,
            'color' => $this->getColor($placedTile->type),
            'i18n' => ['color'],
            'type' => $placedTile->type,
            'preserve' => [ 2 => 'type' ],
            'points' => $points,
            'newScore' => $this->getPlayerScore($playerId),
        ]);

        $this->setGlobalVariable(UNDO_PLACE, new UndoPlace($tiles, $previousScore, $points));

        $wall = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
        $additionalTiles = $this->additionalTilesDetail($wall, $placedTile);
        if ($additionalTiles['count'] > 0) {        
            $this->setGlobalVariable(ADDITIONAL_TILES_DETAIL, $additionalTiles);
        }

        if ($additionalTiles['count'] > 0) {
            return TakeBonusTiles::class;
        } else if ($this->isUndoActivated($playerId)) {
            return ConfirmPlay::class;
        } else {
            return $this->applyConfirmPlay($playerId);
        }
    }

    function applyConfirmPlay(int $playerId) {
        $undo = $this->getGlobalVariable(UNDO_PLACE);
        $count = count($undo->supplyTiles);
        if ($count > 0) {
            $this->incStat($count, 'bonusTilesCollected');
            $this->incStat($count, 'bonusTilesCollected', $playerId);

            $additionalTiles = $this->getGlobalVariable(ADDITIONAL_TILES_DETAIL);
            if (isset($additionalTiles->from)) {
                foreach ($additionalTiles->from as $from) {
                    $this->incStat($count, 'bonusTile_'.$from);
                    $this->incStat($count, 'bonusTile_'.$from, $playerId);
                }
            }
        }

        $this->incStat($undo->points, 'pointsWallTile');
        $this->incStat($undo->points, 'pointsWallTile', $playerId);

        return NextPlayerPlay::class;
    }

    function actUndoPlayTile(int $activePlayerId) {
        $undo = $this->getGlobalVariable(UNDO_PLACE);

        if ($undo) {
            $this->tiles->moveCards(array_map(fn($t) => $t->id, $undo->tiles), 'hand', $activePlayerId);

            foreach ($undo->supplyTiles as $tile) {
                $this->tiles->moveCard($tile->id, $tile->location, $tile->space);
            }

            $this->setPlayerScore($activePlayerId, $undo->previousScore);
        }

        $this->notify->all('undoPlayTile', clienttranslate('${player_name} cancels tile placement'), [
            'playerId' => $activePlayerId,
            'player_name' => $this->getPlayerNameById($activePlayerId),
            'undo' => $undo,
        ]);

        $this->setGlobalVariable(UNDO_PLACE, null);
        $this->setGlobalVariable(SELECTED_PLACE, null);
        $this->setGlobalVariable(SELECTED_COLOR, null);
        $this->setGlobalVariable(ADDITIONAL_TILES_DETAIL, null);
        
        return ChoosePlace::class;
    }

    function actUndoPass(int $activePlayerId) {
        $undo = $this->getGlobalVariable(UNDO_PLACE);

        if ($undo) {
            $this->tiles->moveCards(array_map(fn($t) => $t->id, $undo->tiles), 'hand', $activePlayerId);

            foreach ($undo->supplyTiles as $tile) {
                $this->tiles->moveCard($tile->id, $tile->location, $tile->space);
            }

            $this->setPlayerScore($activePlayerId, $undo->previousScore);
        }

        $this->DbQuery("UPDATE player SET passed = FALSE WHERE player_id = $activePlayerId" );

        $this->notify->all('undoPlayTile', clienttranslate('${player_name} cancels ending the round'), [
            'playerId' => $activePlayerId,
            'player_name' => $this->getPlayerNameById($activePlayerId),
            'undo' => $undo,
        ]);

        $this->setGlobalVariable(UNDO_PLACE, null);
        
        return ChoosePlace::class;
    }

    function applyPass(int $playerId, bool $forceNoConfirm = false) {
        $this->DbQuery("UPDATE player SET passed = TRUE WHERE player_id = $playerId" );
        $this->notify->all('pass', clienttranslate('${player_name} passes'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
        ]);

        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $colorTiles = array_values(array_filter($hand, fn($tile) => $tile->type > 0));

        $lastRound = $this->getRound() >= 6;

        if (!$lastRound && count($colorTiles) > 4) {
            return ChooseKeptTiles::class;
        } else {
            return $this->applySelectKeptTiles($playerId, $lastRound ? [] : array_map(fn($t) => $t->id, $colorTiles), $forceNoConfirm);
        }

    }

    function applySelectKeptTiles(int $playerId, array $ids, bool $forceNoConfirm = false) {
        // for undo
        $previousScore = $this->getPlayerScore($playerId);

        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $keptTiles = [];
        $discardedTiles = [];
        foreach ($hand as $tile) {
            if ($tile->type > 0) {
                if (in_array($tile->id, $ids)) {
                    $keptTiles[] = $tile;
                } else {
                    $discardedTiles[] = $tile;
                }
            }
        }

        if (count($ids) != count($keptTiles)) {
            throw new \BgaUserException("You must select hand tiles");
        }

        $keptNumber = count($keptTiles);
        $discardedNumber = count($discardedTiles);

        $newScoreArgs = [];
        if ($discardedNumber > 0) {  
            $newScoreArgs['newScore'] = $this->decPlayerScore($playerId, $discardedNumber); 
        }

        if ($keptNumber > 0 || $discardedNumber > 0) {        
            $this->tiles->moveCards(array_map(fn($t) => $t->id, $keptTiles), 'corner', $playerId);
            $this->tiles->moveCards(array_map(fn($t) => $t->id, $discardedTiles), 'discard');

            $this->notify->all('putToCorner', clienttranslate('${player_name} keeps ${keptNumber} tiles and discards ${discardedNumber} tiles'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerNameById($playerId),
                'keptTiles' => $keptTiles,
                'discardedTiles' => $discardedTiles,
                'keptNumber' => $keptNumber, // for logs
                'discardedNumber' => $discardedNumber, // for logs
            ] + $newScoreArgs);
        }

        $this->setGlobalVariable(UNDO_PLACE, new UndoPlace($hand, $previousScore, count($discardedTiles)));

        if (!$forceNoConfirm && $this->isUndoActivated($playerId) && (count($ids) > 0 || ($this->getRound() >= 6 && count($discardedTiles) > 0))) {
            return ConfirmPass::class;
        } else {
            return $this->applyConfirmPass($playerId);
        }
    }

    #[CheckAction(false)]
    function actSetAutopass(bool $autopass, int $currentPlayerId) {
        if ($this->canSetAutopass($currentPlayerId)) {
            $this->DbQuery("UPDATE player SET auto_pass = ".($autopass ? 'TRUE' : 'FALSE')." WHERE player_id = $currentPlayerId" );
        }
        
        // dummy notif so player gets back hand
        $this->notify->player($currentPlayerId, "setAutopass", '', []);
    }

    function setGlobalVariable(string $name, /*object|array*/ $obj) {
        /*if ($obj == null) {
            throw new \Error('Global Variable null');
        }*/
        $jsonObj = json_encode($obj);
        $this->DbQuery("INSERT INTO `global_variables`(`name`, `value`)  VALUES ('$name', '$jsonObj') ON DUPLICATE KEY UPDATE `value` = '$jsonObj'");
    }

    function getGlobalVariable(string $name, $asArray = null) {
        $json_obj = $this->getUniqueValueFromDB("SELECT `value` FROM `global_variables` where `name` = '$name'");
        if ($json_obj) {
            $object = json_decode($json_obj, $asArray);
            return $object;
        } else {
            return null;
        }
    }

    function getBoardNumber(): int {
        return $this->tableOptions->get(100);
    }

    function getBoard(): Board {
        if (!isset($this->board)) {
            $boardNumber = $this->getBoardNumber();
            $className = "Bga\Games\AzulSummerPavilion\Boards\Board{$boardNumber}";
            $this->board = new $className;
        }
        return $this->board;
    }

    function isUndoActivated(int $player) {
        return $this->userPreferences->get($player, 101) === 1;
    }

    function isFastScoring() {
        return $this->tableOptions->get(102) === 1;
    }

    function getFactoryNumber($playerNumber = null) {
        if ($playerNumber == null) {
            $playerNumber = intval($this->getUniqueValueFromDB("SELECT count(*) FROM player "));
        }

        return $this->factoriesByPlayers[$playerNumber];
    }

    function getPlayerScore(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function setPlayerScore(int $playerId, int $score) {
        $this->DbQuery("UPDATE player SET player_score = $score WHERE player_id = $playerId");
    }

    function incPlayerScore(int $playerId, int $incScore) {
        $this->DbQuery("UPDATE player SET player_score = player_score + $incScore WHERE player_id = $playerId");
    }

    function decPlayerScore(int $playerId, int $decScore) {
        $newScore = max(1, $this->getPlayerScore($playerId) - $decScore);
        $this->DbQuery("UPDATE player SET player_score = $newScore WHERE player_id = $playerId");
        return $newScore;
    }

    function getRound() {
        return intval($this->getStat('roundsNumber'));
    }

    function getWildColor() {
        return $this->getBoard()->getWildColors()[$this->getRound()];
    }

    function getTileFromDb($dbTile) {
        if (!$dbTile || !array_key_exists('id', $dbTile)) {
            throw new \Error('tile doesn\'t exists '.json_encode($dbTile));
        }
        return new \Tile($dbTile);
    }

    function getTilesFromDb(array $dbTiles) {
        return array_map(fn($dbTile) => $this->getTileFromDb($dbTile), array_values($dbTiles));
    }

    function setupTiles() {
        $cards = [];
        $cards[] = [ 'type' => 0, 'type_arg' => null, 'nbr' => 1 ];
        for ($color=1; $color<=6; $color++) {
            $cards[] = [ 'type' => $color, 'type_arg' => null, 'nbr' => 22 ];
        }
        $this->tiles->createCards($cards, 'deck');
        $this->tiles->shuffle('deck');

        $this->fillSupply();
    }

    function putFirstPlayerTile(int $playerId, array $selectedTiles) {
        $this->setGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN, $playerId);

        $points = count($selectedTiles) - 1;
        $newScore = $this->decPlayerScore($playerId, $points);

        $this->notify->all('firstPlayerToken', clienttranslate('${player_name} took First Player tile and will start next round, losing ${points} points for taking ${points} tiles'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'points' => $points, // for logs
            'decScore' => $points,
            'newScore' => $newScore,
        ]);

        return $points;
    }

    function getColor(int $type) {
        $colorName = null;
        switch ($type) {
            case 0: $colorName = clienttranslate('Multicolor'); break; // for log about complete stars
            case 1: $colorName = clienttranslate('Fuschia'); break;
            case 2: $colorName = clienttranslate('Green'); break;
            case 3: $colorName = clienttranslate('Orange'); break;
            case 4: $colorName = clienttranslate('Yellow'); break;
            case 5: $colorName = clienttranslate('Blue'); break;
            case 6: $colorName = clienttranslate('Red'); break;
        }
        return $colorName;
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function fillSupply() {
        $newTiles = [];
        for ($i=1; $i<=10; $i++) {
            if (intval($this->tiles->countCardInLocation('supply', $i)) == 0) {
                $dbTile = $this->tiles->pickCardForLocation('deck', 'supply', $i);
                if ($dbTile) { // if the bag is empty, we can't refill
                    $newTiles[] = $this->getTileFromDb($dbTile);
                }
            }
        }

        $this->notify->all("supplyFilled", '', [
            'newTiles' => $newTiles,
            'remainingTiles' => intval($this->tiles->countCardInLocation('deck')),
        ]);
    }

    function getSpaceNumber(int $star, int $space, bool $variant) {
        if ($variant) {
            return $star == 0 ? 3 : [null, 3, 2, 1, 4, 5, 6][$space];
        } else {
            return $space;
        }
    }

    function getScoredTiles(int $playerId, $placedTile) {
        $scoredTiles = [$placedTile];

        $wall = $this->getTilesFromDb($this->tiles->getCardsInLocation('wall'.$playerId));
        $starTiles = array_values(array_filter($wall, fn($tile) => $tile->star == $placedTile->star));
        if (count($starTiles) >= 5) {
            $scoredTiles = $starTiles;
        } else {
            for ($i = $placedTile->space + 1; $i <= $placedTile->space + 5; $i++) {
                $iSpace = (($i - 1) % 6) + 1;
                $iTile = Arrays::find($starTiles, fn($tile) => $tile->space == $iSpace);
                if ($iTile && !Arrays::find($scoredTiles, fn($tile) => $tile->id == $iTile->id)) {
                    $scoredTiles[] = $iTile;
                } else {
                    break;
                }
            }
            
            for ($i = $placedTile->space - 1; $i >= $placedTile->space - 5; $i--) {
                $iSpace = (($i + 11) % 6) + 1;
                $iTile = Arrays::find($starTiles, fn($tile) => $tile->space == $iSpace);
                if ($iTile && !Arrays::find($scoredTiles, fn($tile) => $tile->id == $iTile->id)) {
                    $scoredTiles[] = $iTile;
                } else {
                    break;
                }
            }
        }

        return $scoredTiles;
    }

    function additionalTilesDetail(array $wall, $placedTile) {
        $additionalTiles = 0;
        $highlightedTiles = [];
        $from = [];
        $fountains = $this->getBoard()->hasFountains();

        if ($placedTile->star > 0) {
            if (in_array($placedTile->space, [1, 2])) { // statue
                $otherTile = Arrays::find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 3 - $placedTile->space);
                if ($otherTile) {
                    $otherStar = ($placedTile->star % 6) + 1;
                    $space3 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 3);
                    $space4 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 4);
                    if ($space3 && $space4) {
                        $additionalTiles += 2;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space3, $space4]);
                        $from[] = 'statue';
                    }
                }
            }
            if (in_array($placedTile->space, [3, 4])) { // statue
                $otherTile = Arrays::find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 7 - $placedTile->space);
                if ($otherTile) {
                    $otherStar = (($placedTile->star + 4) % 6) + 1;
                    $space1 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 1);
                    $space2 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 2);
                    if ($space1 && $space2) {
                        $additionalTiles += 2;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space1, $space2]);
                        $from[] = 'statue';
                    }
                }
            }
            if ($fountains && in_array($placedTile->space, [6,1])) { // fountain
                $otherTile = Arrays::find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 7 - $placedTile->space);
                if ($otherTile) {
                    $otherStar = ($placedTile->star % 6) + 1;
                    $space1 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 4);
                    $space2 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 5);
                    if ($space1 && $space2) {
                        $additionalTiles += 1;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space1, $space2]);
                        $from[] = 'fountain';
                    }
                }
            }
            if ($fountains && in_array($placedTile->space, [4,5])) { // fountain
                $otherTile = Arrays::find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 9 - $placedTile->space);
                if ($otherTile) {
                    $otherStar = (($placedTile->star + 4) % 6) + 1;
                    $space1 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 1);
                    $space2 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 6);
                    if ($space1 && $space2) {
                        $additionalTiles += 1;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space1, $space2]);
                        $from[] = 'fountain';
                    }
                }
            }
            if (in_array($placedTile->space, [5, 6])) { // window
                $otherTile = Arrays::find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 11 - $placedTile->space);
                if ($otherTile) {
                    $additionalTiles += 3;
                    $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile]);
                        $from[] = 'window';
                }
            }
            if (in_array($placedTile->space, [2, 3])) { // pillar
                $otherTile = Arrays::find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 5 - $placedTile->space);
                if ($otherTile) {
                    $space1 = Arrays::find($wall, fn($tile) => $tile->star == 0 && $tile->space == (($placedTile->star + 3) % 6 + 1));
                    $space2 = Arrays::find($wall, fn($tile) => $tile->star == 0 && $tile->space == (($placedTile->star + 4) % 6 + 1));
                    if ($space1 && $space2) {
                        $additionalTiles += 1;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space1, $space2]);
                        $from[] = 'pillar';
                    }
                }
            }
        } else { // star 0, pillar
            $spaceBefore = (($placedTile->space + 4) % 6) + 1;           
            $tileBefore = Arrays::find($wall, fn($tile) => $tile->star == 0 && $tile->space == $spaceBefore);
            if ($tileBefore) {
                $otherStar = (($placedTile->space + 0) % 6) + 1;
                $space2 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 2);
                $space3 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 3);
                if ($space2 && $space3) {
                    $additionalTiles += 1;
                    $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $tileBefore, $space2, $space3]);
                    $from[] = 'pillar';
                }
            }
            $spaceAfter = ($placedTile->space % 6) + 1;
            $tileAfter = Arrays::find($wall, fn($tile) => $tile->star == 0 && $tile->space == $spaceAfter);
            if ($tileAfter) {
                $otherStar = (($placedTile->space + 1) % 6) + 1;
                $space2 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 2);
                $space3 = Arrays::find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 3);
                if ($space2 && $space3) {
                    $additionalTiles += 1;
                    $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $tileAfter, $space2, $space3]);
                    $from[] = 'pillar';
                }
            }
            //echo json_encode([$spaceBefore, $placedTile->space, $spaceAfter, boolval($tileBefore), boolval($tileAfter)]);
        }

        return [
            'count' => $additionalTiles,
            'highlightedTiles' => $highlightedTiles,
            'from' => $from,
        ];
    }

    function canSetAutopass(int $playerId): bool {        
        if (boolval($this->getUniqueValueFromDB("SELECT passed FROM player WHERE player_id = $playerId"))) {
            return false;
        }
        
        $hand = $this->getTilesFromDb($this->tiles->getCardsInLocation('hand', $playerId));
        $tiles = count(array_filter($hand, fn($tile) => $tile->type > 0));
        if ($tiles == 0) {
            return true;
        } else {
            $lastRound = $this->getRound() >= 6;
            if ($lastRound) {
                $possibleSpaces = $this->argChoosePlaceForPlayer($playerId)['possibleSpaces'];
                return count($possibleSpaces) <= 0;
            } else {
                return $tiles <= 4;
            }
        }
    }

    function argAutopass(): array {
        $result = [];
        $playersIds = $this->getPlayersIds();
        foreach ($playersIds as $playerId) {
            $result[$playerId] = [
                'autopass' => boolval($this->getUniqueValueFromDB("SELECT auto_pass FROM player WHERE player_id = $playerId")),
                'canSetAutopass' => $this->canSetAutopass($playerId),
            ];
        }
        
        return $result;
    }
        
    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////
    
        /*
            upgradeTableDb:
            
            You don't have to care about this until your game has been published on BGA.
            Once your game is on BGA, this method is called everytime the system detects a game running with your old
            Database scheme.
            In this case, if you change your Database scheme, you just have to apply the needed changes in order to
            update the game database and allow the game to continue to run with your new version.
        
        */
        
        function upgradeTableDb($from_version) {
            // $from_version is the current version of this game database, in numerical form.
            // For example, if the game was running with a release of your game named "140430-1345",
            // $from_version is equal to 1404301345
            
            /*if ($from_version <= 2408031258) {
                // ! important ! Use <table_name> for all tables    
                $sql = "ALTER TABLE DBPREFIX_player ADD `auto_pass` tinyint(1) NOT NULL DEFAULT FALSE";
                $this->applyDbUpgradeToAllDB($sql);
            }*/
        }    
}
