<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Salas
    |--------------------------------------------------------------------------
    */

    'code_length' => 4,
    'code_alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    'min_players' => 2,
    'max_players' => 4,

    // Una sala en waiting sin actividad durante estas horas se considera abandonada.
    'abandoned_room_hours' => (int) env('PARCHIS_ABANDONED_ROOM_HOURS', 24),

    // Wasmer Hobby no tiene cron jobs: si no existen las tablas, migrar en el
    // primer arranque. Desactivado por defecto; Cloud/self-host siguen usando
    // `php artisan migrate --force` en el release.
    'auto_migrate' => (bool) env('PARCHIS_AUTO_MIGRATE', false),

    /*
    |--------------------------------------------------------------------------
    | Parchís español clásico
    |--------------------------------------------------------------------------
    |
    | Una sola plantilla de 19x19 para 2, 3 o 4 jugadores. No existen tableros
    | de 6 brazos ni colores adicionales. El recorrido es antihorario y se
    | deriva de las etiquetas clásicas 1..68 con una única fórmula por tramo.
    |
    */

    'grid_size' => 19,
    'view_box' => 800,
    'center' => ['x' => 400.0, 'y' => 400.0],

    'color_order' => ['yellow', 'blue', 'red', 'green'],

    'colors' => [
        'yellow' => '#976000',
        'blue' => '#2f4bb8',
        'red' => '#b32126',
        'green' => '#166f47',
    ],

    'boards' => [
        4 => [
            'cells_per_arm' => 17,
            'home_length' => 7,
            'geometry' => 'cross',
            'cell_size' => 42,
            'all_arms_safe' => true,
            'safe_offsets' => [0, 7, 12],
            // El índice interno 0 es la casilla clásica 5; así s_a = a*17.
            'label_offset' => 4,
        ],
    ],
];
