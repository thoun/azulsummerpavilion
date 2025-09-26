<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class ConfirmPass extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CONFIRM_PASS, 
            type: StateType::ACTIVE_PLAYER,
            name: 'confirmPass',
            description: clienttranslate('${actplayer} must confirm ending the round'),
            descriptionMyTurn: clienttranslate('${you} must confirm ending the round'),
        );
    }

    #[PossibleAction]
    function actConfirmPass(int $activePlayerId) {
        return $this->game->applyConfirmPass($activePlayerId);
    }

    #[PossibleAction]
    function actUndoPass(int $activePlayerId) {
        $this->game->actUndoPass($activePlayerId);
    }

    function zombie(int $playerId) {
        return $this->actConfirmPass($playerId);
    }
}
