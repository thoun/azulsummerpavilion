<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class ConfirmPlay extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CONFIRM_PLAY, 
            type: StateType::ACTIVE_PLAYER,
            name: 'confirmPlay',
            description: clienttranslate('${actplayer} must confirm played tile'),
            descriptionMyTurn: clienttranslate('${you} must confirm played tile'),
        );
    }

    function getArgs() {
        return [
            '_private' => $this->game->argAutopass(),
        ];
    }

    #[PossibleAction]
    function actConfirmPlay(int $activePlayerId) { 
        return $this->game->applyConfirmPlay($activePlayerId);
    }

    #[PossibleAction]
    function actUndoPlayTile(int $activePlayerId) {
        $this->game->actUndoPlayTile($activePlayerId);
    }

    function zombie(int $playerId) {
        return $this->actConfirmPlay($playerId);
    }
}
