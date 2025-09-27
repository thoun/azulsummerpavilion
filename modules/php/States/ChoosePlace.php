<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFrameworkPrototype\Helpers\Arrays;
use Bga\Games\AzulSummerPavilion\Game;

use function Bga\Games\AzulSummerPavilion\debug;

class ChoosePlace extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CHOOSE_PLACE, 
            type: StateType::ACTIVE_PLAYER,
            name: 'choosePlace',
            description: clienttranslate('${actplayer} must choose a space to place a tile'),
            descriptionMyTurn: clienttranslate('${you} must choose a space to place a tile'),
        );
    }

    function getArgs(int $activePlayerId) {
        return $this->game->argChoosePlaceForPlayer($activePlayerId) + [
            '_private' => $this->game->argAutopass(),
        ];
    }

    function onEnteringState(int $activePlayerId) {
        $hand = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('hand', $activePlayerId));
        if (Arrays::count($hand, fn($tile) => $tile->type > 0) == 0) {
            return $this->game->applyPass($activePlayerId);
        }
    }

    #[PossibleAction]
    function actSelectPlace(int $star, int $space, int $activePlayerId, array $args) {
        if (!in_array($star * 100 + $space, $args['possibleSpaces'])) {
            throw new \BgaUserException('Space not available');
        }

        $this->game->setGlobalVariable(SELECTED_PLACE, [$star, $space]);
        $this->game->setGlobalVariable(UNDO_PLACE, null);

        // if only one option (no use of wilds), auto-play it
        $args = $this->game->argChooseColor($activePlayerId);
        if (count($args['possibleColors']) > 1) {
            return ChooseColor::class;
        } else {
            return $this->applySelectColor($args['possibleColors'][0], $activePlayerId);
        }
    }

    #[PossibleAction]
    function actPass(int $activePlayerId) {
        return $this->game->applyPass($activePlayerId);
    }

    public function zombie(int $playerId, array $args) {
        $possibleSpaces = $args['possibleSpaces'];
        $zombieChoice = $this->getRandomZombieChoice($possibleSpaces);
        if ($zombieChoice == null) {
            return $this->actPass($playerId);
        }
        $star = (int)floor($zombieChoice / 100);
        $space = $zombieChoice % 100;
        return $this->actSelectPlace($star, $space, $playerId, $args);
    }

    function applySelectColor(int $color, int $activePlayerId) {
        $this->game->setGlobalVariable(SELECTED_COLOR, $color);

        // if only one option (no wild, or exact count of color+wilds), auto-play it
        $args = $this->game->argPlayTile($activePlayerId);
        if ($args['maxWildTiles'] === 0) {
            return $this->game->applyPlayTile($activePlayerId, 0);
        } else if ($args['maxWildTiles'] + $args['maxColor'] == $args['number']) {
            return $this->game->applyPlayTile($activePlayerId, $args['maxWildTiles']);
        } else {
            return PlayTile::class;
        }
    }
}
