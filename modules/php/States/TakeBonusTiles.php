<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\AzulSummerPavilion\Game;

use function Bga\Games\AzulSummerPavilion\debug;

class TakeBonusTiles extends \Bga\GameFramework\States\GameState
{
    public function __construct(protected Game $game) {
        parent::__construct($game, 
            id: ST_PLAYER_TAKE_BONUS_TILES, 
            type: StateType::ACTIVE_PLAYER,
            name: 'takeBonusTiles',
            description: clienttranslate('${actplayer} must take ${number} bonus tiles'),
            descriptionMyTurn: clienttranslate('${you} must take ${number} bonus tiles'),
        );
    }

    function getArgs() {
        // TEMP FIX for stuck games
        if ($this->game->getGlobalVariable(UNDO_PLACE) == null) {
            $this->gamestate->jumpToState(ChoosePlace::class);
            return [];
        }

        $additionalTiles = $this->game->getGlobalVariable(ADDITIONAL_TILES_DETAIL);
        $number = $additionalTiles->count;
        $highlightedTiles = $additionalTiles->highlightedTiles;

        return [
            'number' => $number, // for title
            'count' => $number,
            'highlightedTiles' => $highlightedTiles,
            '_private' => $this->game->argAutopass(),
        ];
    }

    #[PossibleAction]
    function actTakeBonusTiles(#[IntArrayParam] array $ids, int $activePlayerId, array $args) {
        if (count($ids) != $args['count']) {
            throw new \BgaUserException("You must select ".$args['count']." tiles");
        }
        
        $supply = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('supply'));
        $selectedTiles = [];
        foreach ($supply as $tile) {
            if (in_array($tile->id, $ids)) {
                $selectedTiles[] = $tile;
            }
        }

        if (count($ids) != count($selectedTiles)) {
            throw new \BgaUserException("You must select supply tiles");
        }   

        $undo = $this->game->getGlobalVariable(UNDO_PLACE);
        $undo->supplyTiles = $selectedTiles;
        $this->game->setGlobalVariable(UNDO_PLACE, $undo);

        $this->game->tiles->moveCards(array_map(fn($t) => $t->id, $selectedTiles), 'hand', $activePlayerId);

        $this->notify->all('tilesSelected', clienttranslate('${player_name} takes ${number} tiles from supply'), [
            'playerId' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'number' => count($selectedTiles),
            'selectedTiles' => $selectedTiles,
            'fromSupply' => true,
        ]);

        if ($this->game->isUndoActivated($activePlayerId)) {
            return ConfirmPlay::class;
        } else {
            return $this->game->applyConfirmPlay($activePlayerId);
        }
    }

    #[PossibleAction]
    function actUndoPlayTile(int $activePlayerId) {
        return $this->game->actUndoPlayTile($activePlayerId);
    }

    function zombie(int $playerId, array $args) {
        $wildColor = $this->game->getWildColor();
        $supply = $this->game->getTilesFromDb($this->game->tiles->getCardsInLocation('supply'));
        shuffle($supply);
        usort($supply, fn($a, $b) => ($a->type == $wildColor ? 0 : 1) <=> ($b->type == $wildColor ? 0 : 1)); // take wilds in priority

        $ids = array_slice(array_map(fn($t) => $t->id, $supply), 0, $args['count']);
        return $this->actTakeBonusTiles($ids, $playerId, $args);
    }
}
