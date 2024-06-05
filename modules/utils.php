<?php

function getIdPredicate($tile) {
    return $tile->id;
};

function sortByStar($a, $b) {
    if ($a->star == $b->star) {
        return 0;
    }
    return ($a->star < $b->star) ? -1 : 1;
}

function sortBySpace($a, $b) {
    if ($a->space == $b->space) {
        return 0;
    }
    return ($a->space < $b->space) ? -1 : 1;
}

trait UtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function array_find(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return $value;
            }
        }
        return null;
    }

    function array_find_index(array $array, callable $fn) {
        foreach ($array as $index => $value) {
            if($fn($value)) {
                return $index;
            }
        }
        return null;
    }

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return true;
            }
        }
        return false;
    }
        
    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if(!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    function setGlobalVariable(string $name, /*object|array*/ $obj) {
        /*if ($obj == null) {
            throw new \Error('Global Variable null');
        }*/
        $jsonObj = json_encode($obj);
        self::DbQuery("INSERT INTO `global_variables`(`name`, `value`)  VALUES ('$name', '$jsonObj') ON DUPLICATE KEY UPDATE `value` = '$jsonObj'");
    }

    function getGlobalVariable(string $name, $asArray = null) {
        $json_obj = self::getUniqueValueFromDB("SELECT `value` FROM `global_variables` where `name` = '$name'");
        if ($json_obj) {
            $object = json_decode($json_obj, $asArray);
            return $object;
        } else {
            return null;
        }
    }

    function isVariant() {
        return intval(self::getGameStateValue(VARIANT_OPTION)) === 2;
    }

    function allowUndo() {
        return intval(self::getGameStateValue(UNDO)) === 1;
    }

    function isFastScoring() {
        return intval(self::getGameStateValue(FAST_SCORING)) === 1;
    }

    function getFactoryNumber($playerNumber = null) {
        if ($playerNumber == null) {
            $playerNumber = intval(self::getUniqueValueFromDB("SELECT count(*) FROM player "));
        }

        return $this->factoriesByPlayers[$playerNumber];
    }

    function getPlayerName(int $playerId) {
        return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = $playerId");
    }

    function getPlayerScore(int $playerId) {
        return intval(self::getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function setPlayerScore(int $playerId, int $score) {
        self::DbQuery("UPDATE player SET player_score = $score WHERE player_id = $playerId");
    }

    function incPlayerScore(int $playerId, int $incScore) {
        self::DbQuery("UPDATE player SET player_score = player_score + $incScore WHERE player_id = $playerId");
    }

    function decPlayerScore(int $playerId, int $decScore) {
        $newScore = max(1, $this->getPlayerScore($playerId) - $decScore);
        self::DbQuery("UPDATE player SET player_score = $newScore WHERE player_id = $playerId");
        return $newScore;
    }

    function getRound() {
        return intval(self::getStat('roundsNumber'));
    }

    function getWildColor() {
        return intval(self::getStat('roundsNumber'));
    }

    function getTileFromDb($dbTile) {
        if (!$dbTile || !array_key_exists('id', $dbTile)) {
            throw new Error('tile doesn\'t exists '.json_encode($dbTile));
        }
        return new Tile($dbTile);
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
        self::setGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN, $playerId);

        $points = count($selectedTiles) - 1;
        $this->decPlayerScore($playerId, $points);

        self::notifyAllPlayers('firstPlayerToken', clienttranslate('${player_name} took First Player tile and will start next round, losing ${points} points for taking ${points} tiles'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'points' => $points, // for logs
            'decScore' => $points,
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

    function notifCompleteStar(array $playersIds, array $walls, int $star, bool $variant) {                
        $scoresNotif = [];
        foreach ($playersIds as $playerId) {
            $playerTiles = array_values(array_filter($walls[$playerId], fn($tile) => $tile->star == $star));
            
            if (count($playerTiles) == 6) {
                $color = $this->STANDARD_FACE_STAR_COLORS[$star];
                if ($variant) {
                    if ($playerTiles[0]->type != $playerTiles[1]->type) {
                        $color = 0;
                    } else {
                        $color = $playerTiles[0]->type;
                    }
                }

                $obj = new stdClass();
                $obj->star = $star;
                $obj->tiles = $playerTiles;
                $obj->points = $this->FULL_STAR_POINTS_BY_COLOR[$color];

                $scoresNotif[$playerId] = $obj;

                $this->incPlayerScore($playerId, $obj->points);
                
                self::incStat($obj->points, 'pointsCompleteStars');
                self::incStat($obj->points, 'pointsCompleteStars', $playerId);
                self::incStat($obj->points, 'pointsCompleteStars'.$color);
                self::incStat($obj->points, 'pointsCompleteStars'.$color, $playerId);
            }
        }

        if (count($scoresNotif) > 0) {
            self::notifyAllPlayers('endScore', '', [
                'scores' => $scoresNotif,
            ]);

            foreach ($scoresNotif as $playerId => $notif) {
                self::notifyAllPlayers('completeStarLogDetails', clienttranslate('${player_name} gains ${points} points with complete star ${color}'), [
                    'player_name' => $this->getPlayerName($playerId),
                    'color' => $this->getColor($color),
                    'i18n' => ['color'],
                    'points' => $notif->points,
                ]);
            }
        }
    }

    function notifCompleteNumbers(array $playersIds, array $walls, int $number, bool $variant) {                
        $scoresNotif = [];
        foreach ($playersIds as $playerId) {
            $playerTiles = [];
            if ($variant) {
                if ($number == 3) {
                    $playerTiles = array_values(array_filter($walls[$playerId], fn($tile) => $tile->star == 0 || $tile->space == 1));
                } else {
                    $space = [null, 3, 2, 1, 4][$number];
                    $playerTiles = array_values(array_filter($walls[$playerId], fn($tile) => $tile->star > 0 && $tile->space == $space));
                }
            } else {
                $playerTiles = array_values(array_filter($walls[$playerId], fn($tile) => $tile->space == $number));
            }
            
            $total = $variant ? ($number == 3 ? 12 : 6) : 7;
            if (count($playerTiles) == $total) {

                $obj = new stdClass();
                $obj->star = 0;
                $obj->tiles = $playerTiles;
                $obj->points = $number * 4;

                $scoresNotif[$playerId] = $obj;

                $this->incPlayerScore($playerId, $obj->points);  
                
                self::incStat($obj->points, 'pointsCompleteNumbers');
                self::incStat($obj->points, 'pointsCompleteNumbers', $playerId);
                self::incStat($obj->points, 'pointsCompleteNumbers'.$number);
                self::incStat($obj->points, 'pointsCompleteNumbers'.$number, $playerId);
            }
        }

        if (count($scoresNotif) > 0) {
            self::notifyAllPlayers('endScore', '', [
                'scores' => $scoresNotif,
            ]);

            foreach ($scoresNotif as $playerId => $notif) {
                self::notifyAllPlayers('completeNumberLogDetails', clienttranslate('${player_name} gains ${points} points with complete number ${number}'), [
                    'player_name' => $this->getPlayerName($playerId),
                    'number' => $number,
                    'points' => $notif->points,
                ]);
            }
        }
    }

    function fillSupply() {
        $newTiles = [];
        for ($i=1; $i<=10; $i++) {
            if (intval($this->tiles->countCardInLocation('supply', $i)) == 0) {
                $newTiles[] = $this->getTileFromDb($this->tiles->pickCardForLocation('deck', 'supply', $i));
            }
        }

        self::notifyAllPlayers("supplyFilled", '', [
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
                $iTile = $this->array_find($starTiles, fn($tile) => $tile->space == $iSpace);
                if ($iTile && !$this->array_find($scoredTiles, fn($tile) => $tile->id == $iTile->id)) {
                    $scoredTiles[] = $iTile;
                } else {
                    break;
                }
            }
            
            for ($i = $placedTile->space - 1; $i >= $placedTile->space - 5; $i--) {
                $iSpace = (($i + 11) % 6) + 1;
                $iTile = $this->array_find($starTiles, fn($tile) => $tile->space == $iSpace);
                if ($iTile && !$this->array_find($scoredTiles, fn($tile) => $tile->id == $iTile->id)) {
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

        if ($placedTile->star > 0) {
            if (in_array($placedTile->space, [1, 2])) { // statue
                $otherTile = $this->array_find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 3 - $placedTile->space);
                if ($otherTile) {
                    $otherStar = ($placedTile->star % 6) + 1;
                    $space3 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 3);
                    $space4 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 4);
                    if ($space3 && $space4) {
                        $additionalTiles += 2;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space3, $space4]);
                    }
                }
            }
            if (in_array($placedTile->space, [3, 4])) { // statue
                $otherTile = $this->array_find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 7 - $placedTile->space);
                if ($otherTile) {
                    $otherStar = (($placedTile->star + 4) % 6) + 1;
                    $space1 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 1);
                    $space2 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 2);
                    if ($space1 && $space2) {
                        $additionalTiles += 2;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space1, $space2]);
                    }
                }
            }
            if (in_array($placedTile->space, [5, 6])) { // window
                $otherTile = $this->array_find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 11 - $placedTile->space);
                if ($otherTile) {
                    $additionalTiles += 3;
                    $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile]);
                }
            }
            if (in_array($placedTile->space, [2, 3])) { // pillar
                $otherTile = $this->array_find($wall, fn($tile) => $tile->star == $placedTile->star && $tile->space == 5 - $placedTile->space);
                if ($otherTile) {
                    $space1 = $this->array_find($wall, fn($tile) => $tile->star == 0 && $tile->space == (($placedTile->star + 3) % 6 + 1));
                    $space2 = $this->array_find($wall, fn($tile) => $tile->star == 0 && $tile->space == (($placedTile->star + 4) % 6 + 1));
                    if ($space1 && $space2) {
                        $additionalTiles += 1;
                        $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space1, $space2]);
                    }
                }
            }
        } else { // star 0, pillar
            $spaceBefore = (($placedTile->space + 4) % 6) + 1;           
            $otherTile = $this->array_find($wall, fn($tile) => $tile->star == 0 && $tile->space == $spaceBefore);
            if ($otherTile) {
                $otherStar = ($placedTile->space % 6) + 1;
                $space2 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 2);
                $space3 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 3);
                if ($space2 && $space3) {
                    $additionalTiles += 1;
                    $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space2, $space3]);
                }
            }
            $spaceAfter = ($placedTile->space % 6) + 1;
            $otherTile = $this->array_find($wall, fn($tile) => $tile->star == 0 && $tile->space == $spaceAfter);
            if ($otherTile) {
                $otherStar = (($placedTile->space + 2) % 6) + 1;
                $space2 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 2);
                $space3 = $this->array_find($wall, fn($tile) => $tile->star == $otherStar && $tile->space == 3);
                if ($space2 && $space3) {
                    $additionalTiles += 1;
                    $highlightedTiles = array_merge($highlightedTiles, [$placedTile, $otherTile, $space2, $space3]);
                }
            }
        }

        return [
            'count' => $additionalTiles,
            'highlightedTiles' => $highlightedTiles,
        ];
    }
}
