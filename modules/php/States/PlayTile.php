<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class PlayTile extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_PLAY_TILE, 
            type: StateType::ACTIVE_PLAYER,
            name: 'playTile',
            description: clienttranslate('${actplayer} must choose the number of wild tiles to use'),
            descriptionMyTurn: clienttranslate('${you} must choose the number of wild tiles to use'),
        );
    }

    function getArgs(int $activePlayerId) {
        // TEMP FIX for stuck games
        if ($this->game->getGlobalVariable(SELECTED_PLACE) == null) {
            $this->gamestate->jumpToState(ChoosePlace::class);
            return [];
        }

        return $this->game->argPlayTile($activePlayerId);
    }

    #[PossibleAction]
    function actPlayTile(int $wilds, int $activePlayerId) {
        return $this->game->applyPlayTile($activePlayerId, $wilds);
    }

    #[PossibleAction]
    function actUndoPlayTile(int $activePlayerId) {
        return $this->game->actUndoPlayTile($activePlayerId);
    }

    function zombie(int $playerId) {
        return $this->actPlayTile(0, $playerId);
    }
}
