<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\StateType;
use Bga\GameFrameworkPrototype\Helpers\Arrays;
use Bga\Games\AzulSummerPavilion\Boards\Board;
use Bga\Games\AzulSummerPavilion\Game;

class EndScore extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_END_SCORE, 
            type: StateType::GAME,
        );
    }

    function onEnteringState() {
        $playersIds = $this->game->getPlayersIds();

        $walls = [];
        foreach ($playersIds as $playerId) {
            $walls[$playerId] = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('wall'.$playerId));
        }
        

        $board = $this->game->getBoard();
        $fastScoring = $this->game->isFastScoring();
        if ($fastScoring) {
            $this->endScoreNotifs($playersIds, $walls, $board);
        } else {
            foreach($playersIds as $playerId) {
                $this->endScoreNotifs([$playerId], $walls, $board);
            }
        }

        return ST_END_GAME;
    }

    private function endScoreNotifs(array $playersIds, array $walls, Board $board) {
        // Gain points for each complete star on your wall.
        for ($star = 0; $star <= 6; $star++) {
            $this->notifCompleteStar($playersIds, $walls, $star, $board);
        }
        // Gain 4/8/12/16 points for complete sets of 1/2/3/4.
        for ($number = 1; $number <= 4; $number++) {
            $this->notifCompleteNumbers($playersIds, $walls, $number, $board);
        }
        if ($board->getStructureSetPoints() > 0) {
            $this->notifCompleteStructureSet($playersIds, $walls, $board);
        }
    }

    function notifCompleteStar(array $playersIds, array $walls, int $star, Board $board) {                
        $scoresNotif = [];
        foreach ($playersIds as $playerId) {
            $playerTiles = array_values(array_filter($walls[$playerId], fn($tile) => $tile->star == $star));
            
            if (count($playerTiles) == 6) {
                if ($playerTiles[0]->type != $playerTiles[1]->type) {
                    $color = 0;
                } else {
                    $color = $playerTiles[0]->type;
                }

                $obj = new \stdClass();
                $obj->star = $star;
                $obj->tiles = $playerTiles;
                $obj->points = $board->getFullStarPoints($color);

                $scoresNotif[$playerId] = $obj;

                $this->game->incPlayerScore($playerId, $obj->points);
                
                $this->game->incStat($obj->points, 'pointsCompleteStars');
                $this->game->incStat($obj->points, 'pointsCompleteStars', $playerId);
                $this->game->incStat($obj->points, 'pointsCompleteStars'.$color);
                $this->game->incStat($obj->points, 'pointsCompleteStars'.$color, $playerId);
            }
        }

        if (count($scoresNotif) > 0) {
            $this->notify->all('endScore', '', [
                'scores' => $scoresNotif,
            ]);

            foreach ($scoresNotif as $playerId => $notif) {
                $this->notify->all('completeStarLogDetails', clienttranslate('${player_name} gains ${points} points with complete star ${color}'), [
                    'playerId' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                    'color' => $this->game->getColor($color),
                    'i18n' => ['color'],
                    'points' => $notif->points,
                    'newScore' =>  $this->game->getPlayerScore($playerId),
                ]);
            }
        }
    }

    function notifCompleteNumbers(array $playersIds, array $walls, int $number, Board $board) {                
        $scoresNotif = [];
        $board = $this->game->getBoard();
        foreach ($playersIds as $playerId) {
            $allFilled = true;
            $playerTiles = [];
            foreach($board->getStars() as $starIndex => $starData) {
                foreach($starData as $spaceIndex => $spaceData) {
                    $spaceNumber = $spaceData['number'];
                    if ($spaceNumber === $number) {
                        $tile = Arrays::find($walls[$playerId], fn($t) => $t->star == $starIndex && $t->space == $spaceIndex);
                        if ($tile) {
                            $playerTiles[] = $tile;
                        } else {
                            $allFilled = false;
                        }
                    }
                }
            }
            
            if ($allFilled) {
                $obj = new \stdClass();
                $obj->star = 0;
                $obj->tiles = $playerTiles;
                $obj->points = $board->getAllNumberPoints($number);

                $scoresNotif[$playerId] = $obj;

                $this->game->incPlayerScore($playerId, $obj->points);  
                
                $this->game->incStat($obj->points, 'pointsCompleteNumbers');
                $this->game->incStat($obj->points, 'pointsCompleteNumbers', $playerId);
                $this->game->incStat($obj->points, 'pointsCompleteNumbers'.$number);
                $this->game->incStat($obj->points, 'pointsCompleteNumbers'.$number, $playerId);
            }
        }

        if (count($scoresNotif) > 0) {
            $this->notify->all('endScore', '', [
                'scores' => $scoresNotif,
            ]);

            foreach ($scoresNotif as $playerId => $notif) {
                $this->notify->all('completeNumberLogDetails', clienttranslate('${player_name} gains ${points} points with complete number ${number}'), [
                    'playerId' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                    'number' => $number,
                    'points' => $notif->points,
                    'newScore' =>  $this->game->getPlayerScore($playerId),
                ]);
            }
        }
    }

    function notifCompleteStructureSet(array $playersIds, array $walls, Board $board) {                
        $scoresNotif = [];
        foreach ($playersIds as $playerId) {
            // For efficient lookups, we'll map the wall into a 2D array: $lookup[star][space] = true
            $lookup = [
                0 => [],
                1 => [],
                2 => [],
                3 => [],
                4 => [],
                5 => [],
                6 => [],
            ];
            foreach ($walls[$playerId] as $tile) {
                // Ensure star and space are treated as integers if they come from a database as strings
                $lookup[(int)$tile->star][(int)$tile->space] = true;
            }
            
            $completedStructures = [];
            foreach (['pillar', 'statue', 'window', 'fountain'] as $structure) {
                $completedStructures[$structure] = $this->getCompletedStructures($structure, $lookup);
            }
            $completedSets = min($completedStructures);
            
            if ($completedSets > 0) {
                $obj = new \stdClass();
                $obj->completedSets = $completedSets;
                $obj->points = $board->getStructureSetPoints() * $completedSets;

                $scoresNotif[$playerId] = $obj;

                $this->game->incPlayerScore($playerId, $obj->points);
                
                $this->game->incStat($obj->points, 'pointsCompleteStructureSets');
                $this->game->incStat($obj->points, 'pointsCompleteStructureSets', $playerId);
            }
        }

        if (count($scoresNotif) > 0) {
            $this->notify->all('endScore', '', [
                'scores' => $scoresNotif,
            ]);

            foreach ($scoresNotif as $playerId => $notif) {
                $this->notify->all('completeStructureSetLogDetails', clienttranslate('${player_name} gains ${points} points with ${number} complete structure set'), [
                    'playerId' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                    'number' => $notif->completedSets,
                    'points' => $notif->points,
                    'newScore' =>  $this->game->getPlayerScore($playerId),
                ]);
            }
        }
    }

    function getCompletedStructures(string $structure, array $lookup) {
        $count = 0;
        switch ($structure) {
            case 'window':
                // A window is formed by two tiles of the same star (1-6) on spaces 5 and 6.
                for ($star = 1; $star <= 6; $star++) {
                    if (isset($lookup[$star][5]) && isset($lookup[$star][6])) {
                        $count++;
                    }
                }
                break;

            case 'statue':
                // A statue is formed by four tiles:
                // - star N on spaces 1 and 2
                // - star N+1 (wrapping 6->1) on spaces 3 and 4
                for ($star = 1; $star <= 6; $star++) {
                    $nextStar = ($star % 6) + 1;
                    if (
                        isset($lookup[$star][1]) && isset($lookup[$star][2]) &&
                        isset($lookup[$nextStar][3]) && isset($lookup[$nextStar][4])
                    ) {
                        $count++;
                    }
                }
                break;

            case 'fountain':
                // A fountain is formed by four tiles:
                // - star N on spaces 1 and 6
                // - star N+1 (wrapping 6->1) on spaces 4 and 5
                for ($star = 1; $star <= 6; $star++) {
                    $nextStar = ($star % 6) + 1;
                    if (
                        isset($lookup[$star][1]) && isset($lookup[$star][6]) &&
                        isset($lookup[$nextStar][4]) && isset($lookup[$nextStar][5])
                    ) {
                        $count++;
                    }
                }
                break;

            case 'pillar':
                // A pillar is formed by four tiles:
                // - star N on spaces 2 and 3
                // - star 0 on two specific, consecutive spaces determined by N.
                for ($star = 1; $star <= 6; $star++) {
                    $starZeroSpace1 = ($star + 3) % 6 + 1;
                    $starZeroSpace2 = ($star + 4) % 6 + 1;
                    
                    if (
                        isset($lookup[$star][2]) && isset($lookup[$star][3]) &&
                        isset($lookup[0][$starZeroSpace1]) && isset($lookup[0][$starZeroSpace2])
                    ) {
                        $count++;
                    }
                }
                break;
        }

        return $count;
    }
}
