<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\Boards;

// Glazed Pavilion: orange side
class Board3 extends Board {

    public function getStars(): array {
        return [
            0 => [
                1 => ['color' => 1, 'number' => 1],
                2 => ['color' => 2, 'number' => 2],
                3 => ['color' => 3, 'number' => 3],
                4 => ['color' => 4, 'number' => 4],
                5 => ['color' => 5, 'number' => 5],
                6 => ['color' => 6, 'number' => 6],
            ],
            1 => [
                1 => ['color' => 1, 'number' => 1],
                2 => ['color' => 1, 'number' => 2],
                3 => ['color' => 1, 'number' => 3],
                4 => ['color' => 1, 'number' => 4],
                5 => ['color' => 1, 'number' => 5],
                6 => ['color' => 1, 'number' => 6],
            ],
            2 => [
                1 => ['color' => 3, 'number' => 1],
                2 => ['color' => 3, 'number' => 2],
                3 => ['color' => 3, 'number' => 3],
                4 => ['color' => 3, 'number' => 4],
                5 => ['color' => 3, 'number' => 5],
                6 => ['color' => 3, 'number' => 6],
            ],
            3 => [
                1 => ['color' => 6, 'number' => 1],
                2 => ['color' => 6, 'number' => 2],
                3 => ['color' => 6, 'number' => 3],
                4 => ['color' => 6, 'number' => 4],
                5 => ['color' => 6, 'number' => 5],
                6 => ['color' => 6, 'number' => 6],
            ],
            4 => [
                1 => ['color' => 5, 'number' => 1],
                2 => ['color' => 5, 'number' => 2],
                3 => ['color' => 5, 'number' => 3],
                4 => ['color' => 5, 'number' => 4],
                5 => ['color' => 5, 'number' => 5],
                6 => ['color' => 5, 'number' => 6],
            ],
            5 => [
                1 => ['color' => 4, 'number' => 1],
                2 => ['color' => 4, 'number' => 2],
                3 => ['color' => 4, 'number' => 3],
                4 => ['color' => 4, 'number' => 4],
                5 => ['color' => 4, 'number' => 5],
                6 => ['color' => 4, 'number' => 6],
            ],
            6 => [
                1 => ['color' => 2, 'number' => 1],
                2 => ['color' => 2, 'number' => 2],
                3 => ['color' => 2, 'number' => 3],
                4 => ['color' => 2, 'number' => 4],
                5 => ['color' => 2, 'number' => 5],
                6 => ['color' => 2, 'number' => 6],
            ],
        ];
    }

    public function hasFountains(): bool {
        return true;
    }

    public function getFullStarPoints(int $color): int {
        return [
            0 => 15,
            1 => 20,
            2 => 18,
            3 => 17,
            4 => 16, 
            5 => 15, 
            6 => 14,
        ][$color];
    }

    public function getStructureSetPoints(): int {
        return 15;
    }
}