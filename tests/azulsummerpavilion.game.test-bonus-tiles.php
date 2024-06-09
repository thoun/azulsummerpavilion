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

    function testInvalidPillar23star() {
        $result = $this->additionalTilesDetail([
            TileBuilder(0, 1),
            TileBuilder(0, 2),
            TileBuilder(4, 3),
            TileBuilder(4, 2)
        ], TileBuilder(4, 2));

        $expected = 0;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testInvalidPillar23star: PASSED\n";
        } else {
            echo "testInvalidPillar23star: FAILED\n";
            echo "Expected: $expected, value: ".$result['count']."\n";
        }
    }

    function testPillarStar2() {
        $result = $this->additionalTilesDetail([
            TileBuilder(2, 2),
            TileBuilder(2, 3),
            TileBuilder(0, 6),
            TileBuilder(0, 1)
        ], TileBuilder(0, 1));

        $expected = 1;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testPillarStar2: PASSED\n";
        } else {
            echo "testPillarStar2: FAILED\n";
            echo "Expected: $expected, value: ".$result['count']."\n";
        }
    }

    function testInvalidPillarStar2() {
        $result = $this->additionalTilesDetail([
            TileBuilder(2, 2),
            TileBuilder(2, 3),
            TileBuilder(0, 6),
            TileBuilder(0, 5)
        ], TileBuilder(0, 5));

        $expected = 0;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testInvalidPillarStar2: PASSED\n";
        } else {
            echo "testInvalidPillarStar2: FAILED ==> ";
            echo "Expected: $expected, value: ".$result['count']."\n";
        }
    }

    function testPillarsWithStarTile(int $star, int $shift, int $starTile) {
        $center1 = ((($star + $shift - 1) + 5) % 6) + 1;
        $center2 = ((($star + $shift - 2) + 5) % 6) + 1;
        $result = $this->additionalTilesDetail([
            TileBuilder(0, $center1), // 0
            TileBuilder(0, $center2), // -1
            TileBuilder($star, 3),
            TileBuilder($star, 2)
        ], TileBuilder($star, $starTile));

        $expected = $shift === 0 ? 1 : 0;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testPillarsWithStarTile star $star (shift $shift): PASSED\n";
        } else {
            echo "testPillarsWithStarTile star $star (shift $shift): FAILED ==> Expected: $expected, value: ".$result['count'].", $center1 $center2 ($starTile) \n";
        }
    }

    function testPillarsWithNewCenterTile(int $star, int $shift, int $centerTile) {
        $center1 = ((($star + $shift - 1) + 5) % 6) + 1;
        $center2 = ((($star + $shift - 2) + 5) % 6) + 1;
        $result = $this->additionalTilesDetail([
            TileBuilder(0, $center1), // 0
            TileBuilder(0, $center2), // -1
            TileBuilder($star, 3),
            TileBuilder($star, 2)
        ], TileBuilder(0, $centerTile == 1 ? $center1 : $center2));

        $expected = $shift === 0 ? 1 : 0;
        $equal = $result['count'] == $expected;

        if ($equal) {
            echo "testPillarsWithNewCenterTile star $star (shift $shift): PASSED\n";
        } else {
            echo "testPillarsWithNewCenterTile star $star (shift $shift): FAILED ==> Expected: $expected, value: ".$result['count'].", $center1 $center2 ($centerTile) \n";
        }
    }

    function testAllPillars() {
        $this->testPillar23center();
        $this->testPillar23star();
        $this->testInvalidPillar23star();
        $this->testPillarStar2();
        $this->testInvalidPillarStar2();

        for ($star = 1; $star <= 6; $star++) {
            for ($shift = -1; $shift <= 1; $shift++) {
                for ($starTile = 2; $starTile <= 3; $starTile++) {
                    $this->testPillarsWithStarTile($star, $shift, $starTile);
                }
            }
        }

        for ($star = 1; $star <= 6; $star++) {
            for ($shift = -1; $shift <= 1; $shift++) {
                for ($centerTile = 1; $centerTile <= 2; $centerTile++) {
                    $this->testPillarsWithNewCenterTile($star, $shift, $centerTile);
                }
            }
        }
    }

    function testAll() {
        $this->testAllPillars();
    }
}

$test1 = new AzulSummerPavilionTestBonusTiles();
$test1->testAll();