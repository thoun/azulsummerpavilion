<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\Boards;

// base game: gray side
class Board2 extends Board {

    public function getStars(): array {
        return [
            0 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 3],
                3 => ['color' => 0, 'number' => 3],
                4 => ['color' => 0, 'number' => 3],
                5 => ['color' => 0, 'number' => 3],
                6 => ['color' => 0, 'number' => 3],
            ],
            1 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 2],
                3 => ['color' => 0, 'number' => 1],
                4 => ['color' => 0, 'number' => 4],
                5 => ['color' => 0, 'number' => 5],
                6 => ['color' => 0, 'number' => 6],
            ],
            2 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 2],
                3 => ['color' => 0, 'number' => 1],
                4 => ['color' => 0, 'number' => 4],
                5 => ['color' => 0, 'number' => 5],
                6 => ['color' => 0, 'number' => 6],
            ],
            3 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 2],
                3 => ['color' => 0, 'number' => 1],
                4 => ['color' => 0, 'number' => 4],
                5 => ['color' => 0, 'number' => 5],
                6 => ['color' => 0, 'number' => 6],
            ],
            4 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 2],
                3 => ['color' => 0, 'number' => 1],
                4 => ['color' => 0, 'number' => 4],
                5 => ['color' => 0, 'number' => 5],
                6 => ['color' => 0, 'number' => 6],
            ],
            5 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 2],
                3 => ['color' => 0, 'number' => 1],
                4 => ['color' => 0, 'number' => 4],
                5 => ['color' => 0, 'number' => 5],
                6 => ['color' => 0, 'number' => 6],
            ],
            6 => [
                1 => ['color' => 0, 'number' => 3],
                2 => ['color' => 0, 'number' => 2],
                3 => ['color' => 0, 'number' => 1],
                4 => ['color' => 0, 'number' => 4],
                5 => ['color' => 0, 'number' => 5],
                6 => ['color' => 0, 'number' => 6],
            ],
        ];
    }

    public function getStarColor(int $star): int {
        return [
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0, 
            5 => 0, 
            6 => 0,
        ][$star];
    }
}