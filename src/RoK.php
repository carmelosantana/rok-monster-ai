<?php

declare(strict_types=1);

namespace RoKMonster\AI;

class RoK
{
    public function actions($action, $alt = false)
    {
        $actions = [
            'gather-resource-cropland' => [
                'name' => 'Gather corn',
                'description' => 'Collect resources from Cropland.',
                'input' => [
                    self::input(0),
                    self::input(1),
                    self::input(2),
                    self::input(3),
                    // TODO: Similar image search find button on screen
                    self::input(4),
                    self::input(5),
                    self::input(6),
                    self::input(7),
                ]
            ]
        ];

        return $actions[$action] ?? $alt;
    }

    static public function coordinates($ui_element, $alt = false)
    {
        $coordinates = [
            'center' => [960, 540],
            'btn-map-castle' => [88, 980],
            'btn-search-build' => [106, 796],
            'btn-search-food' => [675, 929],
            'btn-search-food-plus' => [882, 600],
            'btn-search-food-search' => [664, 729],
            'btn-resource-gather' => [1443, 725],
            'btn-new-troops' => [1493, 226],
            'btn-new-troops-march' => [1389, 932],
        ];

        return $coordinates[$ui_element] ?? $alt;
    }

    static public function input($input, $alt = false)
    {
        $inputs = [
            [
                'args' => self::coordinates('btn-map-castle'),
                'fingerprint' => '231187747852b253',
            ],
            [
                'args' => self::coordinates('btn-search-build'),
                'fingerprint' => '63753e2e2c6c077b',
            ],
            [
                'args' => self::coordinates('btn-search-food'),
                'fingerprint' => '36263c2e2c6e2671',
            ],
            [
                'args' => self::coordinates('btn-search-food-plus'),
                'fingerprint' => '36263c2e2c6e2671',
            ],
            [
                'args' => self::coordinates('btn-search-food-search'),
                'fingerprint' => '332a3c3c3c6e2661',
            ],
            [
                'args' => self::coordinates('btn-resource-gather'),
                'fingerprint' => '3b11a5c480c0a57b',
                'sleep' => 3,
            ],
            [
                'args' => self::coordinates('btn-new-troops'),
                'fingerprint' => 'e8e2929a96d49b9b',
            ],
            [
                'args' => self::coordinates('btn-search-march'),
                'fingerprint' => 'd0dcb8b8bcbcfce8',
            ],
        ];

        // TODO: Add fuzzy search
        return $inputs[$input] ?? $alt;
    }

    public function library($action)
    {
        $actions = [
            0 => 'zoom-out-map',
            1 => 'find-build',
            2 => 'find-cropland',
            3 => 'find-cropland-6',
            4 => 'find-cropland-search',
            5 => 'find-resource-gather',
            6 => 'troops-new',
            7 => 'troops-march',
        ];
    }
}
