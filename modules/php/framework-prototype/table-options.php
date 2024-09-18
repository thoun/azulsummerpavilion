<?php
declare(strict_types=1);

namespace Bga\GameFrameworkPrototype;

class TableOptions {

    function __construct(
        private $game,
    ) {}

    public function get(int $optionId): int {
        return (int)($this->game->gamestate->table_globals[$optionId]);
    }

    function isTurnBased(): bool {
        return $this->get(200) >= 10;
    }

    function isRealTime(): bool {
        return !$this->isTurnBased();
    }
}
