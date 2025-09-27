<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\StateType;
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
        $variant = $this->game->getBoardNumber() === 2;
        $playersIds = $this->game->getPlayersIds();

        $walls = [];
        foreach ($playersIds as $playerId) {
            $walls[$playerId] = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('wall'.$playerId));
        }
        
        $fastScoring = $this->game->isFastScoring();
        if ($fastScoring) {
            $this->endScoreNotifs($playersIds, $walls, $variant);
        } else {
            foreach($playersIds as $playerId) {
                $this->endScoreNotifs([$playerId], $walls, $variant);
            }
        }

        return ST_END_GAME;
    }

    private function endScoreNotifs(array $playersIds, array $walls, bool $variant) {
        // Gain points for each complete star on your wall.
        for ($star = 0; $star <= 6; $star++) {
            $this->notifCompleteStar($playersIds, $walls, $star, $variant);
        }
        // Gain 4/8/12/16 points for complete sets of 1/2/3/4.
        for ($number = 1; $number <= 4; $number++) {
            $this->notifCompleteNumbers($playersIds, $walls, $number, $variant);
        }
    }

    function notifCompleteStar(array $playersIds, array $walls, int $star, bool $variant) {                
        $scoresNotif = [];
        foreach ($playersIds as $playerId) {
            $playerTiles = array_values(array_filter($walls[$playerId], fn($tile) => $tile->star == $star));
            
            if (count($playerTiles) == 6) {
                $color = $this->game->STANDARD_FACE_STAR_COLORS[$star];
                if ($variant) {
                    if ($playerTiles[0]->type != $playerTiles[1]->type) {
                        $color = 0;
                    } else {
                        $color = $playerTiles[0]->type;
                    }
                }

                $obj = new \stdClass();
                $obj->star = $star;
                $obj->tiles = $playerTiles;
                $obj->points = $this->game->FULL_STAR_POINTS_BY_COLOR[$color];

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

                $obj = new \stdClass();
                $obj->star = 0;
                $obj->tiles = $playerTiles;
                $obj->points = $number * 4;

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
}
