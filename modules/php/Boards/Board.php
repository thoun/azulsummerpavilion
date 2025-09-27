<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\Boards;

abstract class Board {
    public function hasFountains(): bool {
        return false;
    }

    abstract function getStars(): array;

    public function getWildColors(): array {
        return [
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4, 
            5 => 5, 
            6 => 6,
        ];
    }

    public function getFullStarPoints(int $color): int {
        return [
            0 => 12,
            1 => 20,
            2 => 18,
            3 => 17,
            4 => 16, 
            5 => 15, 
            6 => 14,
        ][$color];
    }

    public function getAllNumberPoints(int $number): int {
        return [
            1 => 4,
            2 => 8,
            3 => 12,
            4 => 16,
        ][$number];
    }

    public function getStructureSetPoints(): int {
        return 0;
    }
}