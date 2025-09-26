<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class NextPlayerPlay extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_NEXT_PLAYER_PLAY, 
            type: StateType::GAME,

            updateGameProgression: true,
        );
    }

    function onEnteringState(int $activePlayerId) {
        $this->game->setGlobalVariable(UNDO_PLACE, null);

        $allPassed = intval($this->game->getUniqueValueFromDB("SELECT count(*) FROM player WHERE passed = FALSE")) == 0;

        $this->game->incStat(1, 'turnsNumber');
        $this->game->incStat(1, 'turnsNumber', $activePlayerId);

        $this->game->fillSupply();

        if ($allPassed) {
            return EndRound::class;
        } else {
            $playerId = intval($this->game->activeNextPlayer());
        
            while (boolval($this->game->getUniqueValueFromDB("SELECT passed FROM player WHERE player_id = $playerId"))) {
                $playerId = intval($this->game->activeNextPlayer());
            }

            $autoPass = boolval($this->game->getUniqueValueFromDB("SELECT auto_pass FROM player WHERE player_id = $playerId"));
            if ($autoPass) {
                $this->game->DbQuery("UPDATE player SET auto_pass = FALSE WHERE player_id = $playerId" );
                return $this->game->applyPass($playerId, true); // handles the redirection
            } else {
                $this->game->giveExtraTime($playerId);
                return ST_PLAYER_CHOOSE_PLACE;
            }
        }
    }
}
