<?php
define("APP_GAMEMODULE_PATH", "../misc/"); // include path to stubs, which defines "table.game.php" and other classes
require_once ('../azulsummerpavilion.game.php');

function TileBuilder(int $star, int $space) {
    return new Tile(['id' => 0, 'type' => 1, 'location' => 'wall', 'location_arg' => $star*100+$space]);
}

class AzulSummerPavilionTestBonusTiles extends AzulSummerPavilion { // this is your game class defined in ggg.game.php
    function __construct() {
        // parent::__construct();
        include '../material.inc.php';// this is how this normally included, from constructor
    }

    // class tests

    function testPillar23center() {
        $result = $this->additionalTilesDetail([
            TileBuilder(0, 3),
            TileBuilder(0, 2),
            TileBuilder(4, 3),
            TileBuilder(4, 2)
        ], TileBuilder(0, 3));

        $expected = 1;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testPillar23center: PASSED\n";
        } else {
            echo "testPillar23center: FAILED\n";
            echo "Expected: $expected, value: ".$result['count']."\n";
        }
    }

    function testPillar23star() {
        $result = $this->additionalTilesDetail([
            TileBuilder(0, 3),
            TileBuilder(0, 2),
            TileBuilder(4, 3),
            TileBuilder(4, 2)
        ], TileBuilder(4, 2));

        $expected = 1;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testPillar23star: PASSED\n";
        } else {
            echo "testPillar23star: FAILED\n";
            echo "Expected: $expected, value: ".$result['count']."\n";
        }
    }

    function testPillars() {
        $this->testPillar23center();
        $this->testPillar23star();
    }

    function testAll() {
        $this->testPillars();
    }
}

$test1 = new AzulSummerPavilionTestBonusTiles();
$test1->testAll();