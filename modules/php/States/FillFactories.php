<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

class FillFactories extends \Bga\GameFramework\States\GameState
{

    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_FILL_FACTORIES, 
            type: StateType::GAME,

            updateGameProgression: true,
        );
    }

    function onEnteringState() {        
        $playerNumber = intval($this->game->getUniqueValueFromDB("SELECT count(*) FROM player "));

        $factories = [];

        $firstPlayerTile = $this->game->getTilesFromDb($this->game->tiles->getCardsOfType(0, null))[0];
        $this->game->tiles->moveCard($firstPlayerTile->id, 'factory', 0);
        $factories[0] = [$firstPlayerTile];

        $factoryNumber = $this->game->getFactoryNumber($playerNumber);
        for ($factory=1; $factory<=$factoryNumber; $factory++) {
            $factories[$factory] = $this->game->getTilesFromDb($this->game->tiles->pickCardsForLocation(4, 'deck', 'factory', $factory));
        }

        $this->gamestate->changeActivePlayer($this->game->getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN));

        $this->game->incStat(1, 'roundsNumber');
        $this->game->incStat(1, 'firstPlayer', intval($this->game->getGameStateValue(FIRST_PLAYER_FOR_NEXT_TURN)));

        $this->game->DbQuery("UPDATE player SET passed = false, auto_pass = false");

        $this->notify->all("factoriesFilled", clienttranslate('Round ${round_number}/6 begins !'), [
            'factories' => $factories,
            'remainingTiles' => intval($this->game->tiles->countCardInLocation('deck')),
            'roundNumber' => intval($this->game->getStat('roundsNumber')),
            'round_number' => intval($this->game->getStat('roundsNumber')), // for logs
        ]);


        // place stored tiles in hand
        $playersIds = $this->game->getPlayersIds();
        foreach ($playersIds as $playerId) {
            if (intval($this->game->tiles->countCardInLocation('corner', $playerId)) > 0) {
                $tiles = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('corner', $playerId));
                $this->game->tiles->moveAllCardsInLocation('corner', 'hand', $playerId, $playerId);

                $this->notify->all("cornerToHand", '', [
                    'playerId' => $playerId,
                    'tiles' => $tiles,
                ]);
            }
        }

        return ST_PLAYER_CHOOSE_TILE;
    }
}
