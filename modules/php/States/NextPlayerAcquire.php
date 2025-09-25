<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class NextPlayerAcquire extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_NEXT_PLAYER_ACQUIRE, 
            type: StateType::GAME,

            updateGameProgression: true,
        );
    }

    function onEnteringState(int $activePlayerId) {        
        $this->game->setGlobalVariable(UNDO_SELECT, null);

        $factoriesAllEmpty = $this->game->tiles->countCardInLocation('factory') == 0;
        $this->game->giveExtraTime($activePlayerId);

        $this->game->incStat(1, 'turnsNumber');
        $this->game->incStat(1, 'turnsNumber', $activePlayerId);

        if ($factoriesAllEmpty) {
            $this->gamestate->changeActivePlayer($this->game->getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));
            return ST_PLAYER_CHOOSE_PLACE;
        } else {
            $this->game->activeNextPlayer();
            return ST_PLAYER_CHOOSE_TILE;
        }
    }
}
