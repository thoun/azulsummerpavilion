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


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

require_once('modules/php/framework-prototype/table-options.php');

require_once('modules/constants.inc.php');
require_once('modules/tile.php');
require_once('modules/undo.php');
require_once('modules/utils.php');
require_once('modules/actions.php');
require_once('modules/args.php');
require_once('modules/states.php');
require_once('modules/debug-util.php');

use \Bga\GameFrameworkPrototype\TableOptions;

class AzulSummerPavilion extends Table {
    use UtilTrait;
    use ActionTrait;
    use ArgsTrait;
    use StateTrait;
    use DebugUtilTrait;

    public $tiles;

    public TableOptions $tableOptions;

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

            VARIANT_OPTION => 100,
            FAST_SCORING => 102,
        ]);

        $this->tableOptions = new TableOptions($this);

        $this->tiles = self::getNew("module.common.deck");
        $this->tiles->init("tile");
        $this->tiles->autoreshuffle = true;      
	}
	
    protected function getGameName() {
		// Used for translations and stuff. Please do not modify.
        return "azulsummerpavilion";
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
            'bonusTilesCollected', 'bonusTile1', 'bonusTile2', 'bonusTile3',
            'pointsWallTile', 'pointsLossDiscardedTiles', 'pointsLossFirstTile',
            'pointsCompleteStars', 'pointsCompleteStars0', 'pointsCompleteStars1', 'pointsCompleteStars2', 'pointsCompleteStars3', 'pointsCompleteStars4', 'pointsCompleteStars5', 'pointsCompleteStars6',
            'pointsCompleteNumbers', 'pointsCompleteNumbers1', 'pointsCompleteNumbers2', 'pointsCompleteNumbers3', 'pointsCompleteNumbers4',
            ] as $statName) {
                $this->initStat($statType, $statName, 0);
            }
        }

        $this->setupTiles();

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        // TODO TEMP to test
        $this->debugSetup();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas() {
        $result = [];
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo, passed, auto_pass autoPass FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        $result['factoryNumber'] = $this->getFactoryNumber(count($result['players']));
        $result['firstPlayerTokenPlayerId'] = intval(self::getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));
        $isVariant = $this->isVariant();
        $result['variant'] = $isVariant;

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

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////
    
        /*
            zombieTurn:
            
            This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
            You can do whatever you want in order to make sure the turn of this player ends appropriately
            (ex: pass).
            
            Important: your zombie code will be called when the player leaves the game. This action is triggered
            from the main site and propagated to the gameserver from a server, not from a browser.
            As a consequence, there is no current player associated to this action. In your zombieTurn function,
            you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
        */
    
        function zombieTurn($state, $active_player) {
            $statename = $state['name'];
            
            if ($state['type'] === "activeplayer") {
                switch ($statename) {
                    case 'chooseTile':
                        $factoryTiles = $this->getTilesFromDb($this->tiles->getCardsInLocation('factory'));
                        $tiles = array_values(array_filter($factoryTiles, fn($tile) => $tile->type > 0));
                        $round = $this->getRound();
                        $normalTiles = array_values(array_filter($factoryTiles, fn($tile) => $tile->type != $round));
                        if (count($normalTiles) > 0) {
                            $this->takeTiles($normalTiles[bga_rand(1, count($normalTiles)) - 1]->id, true);
                        } else {
                            $this->takeTiles($tiles[bga_rand(1, count($tiles)) - 1]->id, true);
                        }
                        break;
                    case 'confirmAcquire':
                        $this->applyConfirmTiles($active_player);
                        break;
                    case 'choosePlace':
                        $this->applyPass($active_player);
                        break;
                    case 'chooseColor':
                        $this->selectColor(0);
                        break;
                    case 'playTile':
                        $this->playTile(0, true);
                        break;
                    case 'confirmPlay':
                        $this->applyConfirmPlay($active_player);
                        break;
                    case 'chooseKeptTiles':
                        $this->applySelectKeptTiles($active_player, []);
                        break;
                    case 'confirmPass':
                        $this->applyConfirmPass($active_player);
                        break;
                    default:
                        $this->gamestate->nextState("nextPlayer"); // all player actions got nextPlayer action as a "zombiePass"
                        break;
                }
    
                return;
            }
    
            if ($state['type'] === "multipleactiveplayer") {
                // Make sure player is in a non blocking status for role turn
                $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
                
                return;
            }
    
            throw new feException( "Zombie mode not supported at this game state: ".$statename );
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
            
            if ($from_version <= 2408031258) {
                // ! important ! Use <table_name> for all tables    
                $sql = "ALTER TABLE DBPREFIX_player ADD `auto_pass` tinyint(1) NOT NULL DEFAULT FALSE";
                $this->applyDbUpgradeToAllDB($sql);
            }
        }    
}
