<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFrameworkPrototype\Helpers\Arrays;
use Bga\Games\AzulSummerPavilion\Game;

class ChooseTile extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CHOOSE_TILE, 
            type: StateType::ACTIVE_PLAYER,
            name: 'chooseTile',
            description: clienttranslate('${actplayer} must choose tiles'),
            descriptionMyTurn: clienttranslate('${you} must choose tiles'),
        );
    }

    function getArgs() {
        return [
            'wildColor' => $this->game->getWildColor(),
        ];
    }

    function onEnteringState(int $activePlayerId) {
        if (!$this->tableOptions->isTurnBased()) {
            return;
        }

        $factoriesTiles = array_filter($this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('factory')), fn($tile) => $tile->type > 0);

        $possibleTile = null;

        $factories = array_values(array_unique(array_map(fn($tile) => $tile->space, $factoriesTiles)));
        
        $wildColor = $this->game->getWildColor();
        foreach ($factories as $factory) {
            $factoryTiles = array_filter($factoriesTiles, fn($tile) => $tile->space === $factory);

            foreach($factoryTiles as $tile) {
                $isWild = $tile->type == $wildColor;
                if ($isWild && Arrays::some($factoryTiles, fn($factoryTile) => !in_array($factoryTile->type, [0, $wildColor]))) {
                    continue; // ignore wilds we cannot take
                }

                if ($possibleTile === null) {
                    $possibleTile = $tile;
                } else if ($tile->type !== $possibleTile->type || $tile->space !== $possibleTile->space) {
                    // already another possible tile, we can't play automatically
                    return;
                }
            }
        }

        if ($possibleTile !== null) { // play automatically this tile
            $this->actTakeTiles($possibleTile->id, $activePlayerId, true);
        }
    }

    #[PossibleAction]
    function actTakeTiles(int $id, int $activePlayerId, bool $automatic = false) {
        // for undo
        $previousFirstPlayer = intval($this->game->getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));
        $previousScore = $this->game->getPlayerScore($activePlayerId);

        $tile = $this->game->getTileFromDb($this->game->tiles->getCard($id));

        if ($tile->location !== 'factory') {
            throw new \BgaUserException("Tile is not in a factory");
        }
        if ($tile->type === 0) {
            throw new \BgaUserException("Tile is First Player token");
        }

        $factory = $tile->space;
        $factoryTiles = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('factory', $factory));

        $wildColor = $this->game->getWildColor();
        $isWild = $tile->type == $wildColor;
        if ($isWild) {
            if (Arrays::some($factoryTiles, fn($factoryTile) => !in_array($factoryTile->type, [0, $wildColor]))) {
                throw new \BgaUserException(clienttranslate("You cannot take a wild tile"));
            }
        }

        $wildTiles = array_values(array_filter($factoryTiles, fn($factoryTile) => $factoryTile->type == $wildColor));

        $additionalWildTile = null;        
        $firstPlayerTokens = [];
        $selectedNormalTiles = 0;
        $selectedTiles = [];
        $discardedTiles = [];
        $hasFirstPlayer = false;
        $pointsLossFirstTile = 0;

        if (!$isWild) {
            foreach($factoryTiles as $factoryTile) {
                if ($tile->type == $factoryTile->type) {
                    $selectedTiles[] = $factoryTile;
                    $selectedNormalTiles++;
                }
            }
        }
        if (count($wildTiles) > 0) {
            $selectedTiles[] = $wildTiles[0];

            if (!$isWild) {
                $additionalWildTile = $wildTiles[0];
            }
        }

        if ($factory == 0) {
            $firstPlayerTokens = array_values(array_filter($factoryTiles, fn($fpTile) => $fpTile->type == 0));
            $hasFirstPlayer = count($firstPlayerTokens) > 0;

            if ($hasFirstPlayer) {
                $selectedTiles[] = $firstPlayerTokens[0];
                $pointsLossFirstTile = $this->game->putFirstPlayerTile($activePlayerId, $selectedTiles);
            }
        } else {
            foreach($factoryTiles as $factoryTile) {
                if (!Arrays::some($selectedTiles, fn($selectedTile) => $selectedTile->id == $factoryTile->id)) {
                    $discardedTiles[] = $factoryTile;
                }
            }
            $this->game->tiles->moveCards(array_map('getIdPredicate', $discardedTiles), 'factory', 0);
        }
        $this->game->tiles->moveCards(array_map('getIdPredicate', $selectedTiles), 'hand', $activePlayerId);

        
        if ($hasFirstPlayer) {
            $message = clienttranslate('${player_name} takes ${number} ${color} ${wild} and First Player tile');
        } else {
            $message = clienttranslate('${player_name} takes ${number} ${color} ${wild}');
        }

        $this->notify->all('tilesSelected', $message, [
            'playerId' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'number' => $selectedNormalTiles,
            'color' => $this->game->getColor($tile->type),   
            'wild' => $additionalWildTile != null ? $this->game->getColor($additionalWildTile->type) : '',   
            'i18n' => ['color'],           
            'type' => $tile->type,    
            'typeWild' => $additionalWildTile != null ? $additionalWildTile->type : null,
            'preserve' => [ 'type', 'typeWild' ],
            'selectedTiles' => $selectedTiles,
            'discardedTiles' => $discardedTiles,
            'fromFactory' => $factory,
            '_bga_automatic_action' => $automatic,
        ]);

        $this->game->setGlobalVariable(UNDO_SELECT, new \UndoSelect(
            array_merge($selectedTiles, $discardedTiles, $firstPlayerTokens),
            $selectedNormalTiles,
            $additionalWildTile != null,
            $pointsLossFirstTile,
            $factory, 
            $previousFirstPlayer,
            $previousScore,
        ));

        if ($this->game->isUndoActivated($activePlayerId) && !$automatic) {
            return ST_PLAYER_CONFIRM_ACQUIRE;
        } else {            
            $this->game->applyConfirmTiles($activePlayerId);
        }
    }

    function zombie(int $playerId) {
        $factoryTiles = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('factory'));
        $tiles = Arrays::filter($factoryTiles, fn($tile) => $tile->type > 0);
        $round = $this->game->getRound();
        $normalTiles = Arrays::filter($tiles, fn($tile) => $tile->type != $round);
        $possibleChoices = count($normalTiles) > 0 ? $normalTiles : $tiles;
        $zombieChoice = $this->getRandomZombieChoice(Arrays::map($possibleChoices, fn($t) => $t->id));
        return $this->actTakeTiles($zombieChoice, $playerId, true);
    }
}
