<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class ConfirmAcquire extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CONFIRM_ACQUIRE, 
            type: StateType::ACTIVE_PLAYER,
            name: 'confirmAcquire',
            description: clienttranslate('${actplayer} must confirm acquired tiles'),
            descriptionMyTurn: clienttranslate('${you} must confirm acquired tiles'),
        );
    }

    #[PossibleAction]
    function actConfirmAcquire(int $activePlayerId) {
        return $this->game->applyConfirmTiles($activePlayerId);
    }

    #[PossibleAction]
    function actUndoTakeTiles(int $activePlayerId) {
        $undo = $this->game->getGlobalVariable(UNDO_SELECT);
        $this->game->setPlayerScore($activePlayerId, $undo->previousScore);
        $factoryTilesBefore = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('factory', $undo->from));
        $this->game->tiles->moveCards(array_map(fn($t) => $t->id, $undo->tiles), 'factory', $undo->from);
        $this->game->setGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN, $undo->previousFirstPlayer);

        $this->notify->all('undoTakeTiles', clienttranslate('${player_name} cancels tile selection'), [
            'playerId' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'undo' => $undo,
            'factoryTilesBefore' => $factoryTilesBefore,
        ]);

        return ChooseTile::class;
    }

    function zombie(int $playerId) {
        return $this->actConfirmAcquire($playerId);
    }
}
