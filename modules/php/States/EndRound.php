<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class EndRound extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_END_ROUND, 
            type: StateType::GAME,
        );
    }

    function onEnteringState() {
        $firstPlayerTile = $this->game->getTilesFromDb($this->game->tiles->getCardsOfType(0))[0];
        $this->game->tiles->moveCard($firstPlayerTile->id, 'factory', 0);

        $round = $this->game->getRound();
        return $round < 6 ? FillFactories::class : EndScore::class;
    }
}
