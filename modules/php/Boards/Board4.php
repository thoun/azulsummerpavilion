<?php
declare(strict_types=1);

namespace Bga\Games\AzulSummerPavilion\Boards;

// Glazed Pavilion: purple side
class Board4 extends Board {

    public function getStars(): array {
        return [
            0 => [
                1 => ['color' => 3, 'number' => 3],
                2 => ['color' => 6, 'number' => 3],
                3 => ['color' => 5, 'number' => 3],
                4 => ['color' => 4, 'number' => 3],
                5 => ['color' => 2, 'number' => 3],
                6 => ['color' => 1, 'number' => 3],
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

    public function hasFountains(): bool {
        return true;
    }

    public function getWildColors(): array {
        return [
            1 => 3,
            2 => 6,
            3 => 5,
            4 => 4, 
            5 => 2, 
            6 => 1,
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

    public function getAllNumberPoints(int $number): int {
        return [
            1 => 3,
            2 => 6,
            3 => 18,
            4 => 12,
        ][$number];
    }

    public function getStructureSetPoints(): int {
        return 12;
    }
}