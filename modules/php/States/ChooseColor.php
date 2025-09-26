<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class ChooseColor extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CHOOSE_COLOR, 
            type: StateType::ACTIVE_PLAYER,
            name: 'chooseColor',
            description: clienttranslate('${actplayer} must choose a color to place'),
            descriptionMyTurn: clienttranslate('${you} must choose a color to place'),
        );
    }

    function getArgs(int $activePlayerId) {
        return $this->game->argChooseColor($activePlayerId);
    }

    #[PossibleAction]
    function actSelectColor(int $color, int $activePlayerId) {
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

    #[PossibleAction]
    function actUndoPlayTile(int $activePlayerId) {
        $this->game->actUndoPlayTile($activePlayerId);
    }

    #[PossibleAction]
    function actPass(int $activePlayerId) {
        return $this->game->applyPass($activePlayerId);
    }

    function zombie(int $playerId) {
        return $this->actSelectColor(0, $playerId);
    }
}
