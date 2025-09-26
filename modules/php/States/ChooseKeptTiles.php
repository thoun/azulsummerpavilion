<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class ChooseKeptTiles extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_CHOOSE_KEPT_TILES, 
            type: StateType::ACTIVE_PLAYER,
            name: 'chooseKeptTiles',
            description: clienttranslate('${actplayer} may choose up to 4 tiles to keep'),
            descriptionMyTurn: clienttranslate('${you} may choose up to 4 tiles to keep'),
        );
    }

    #[PossibleAction]
    function actSelectKeptTiles(#[IntArrayParam] array $ids, int $activePlayerId) {
        if (count($ids) > 4) {
            throw new \BgaUserException("You cannot keep more than 4 tiles");
        }

        return $this->game->applySelectKeptTiles($activePlayerId, $ids);
    }

    #[PossibleAction]
    function actUndoPass(int $activePlayerId) {
        return $this->game->actUndoPass($activePlayerId);
    }

    function zombie(int $playerId) {
        return $this->game->applySelectKeptTiles($playerId, []);
    }
}
