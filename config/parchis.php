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
    'max_players' => 6,

    // Una sala en waiting sin actividad durante estas horas se considera abandonada.
    'abandoned_room_hours' => (int) env('PARCHIS_ABANDONED_ROOM_HOURS', 24),

    // Wasmer Hobby no tiene cron jobs: si no existen las tablas, migrar en el
    // primer arranque. Desactivado por defecto; Cloud/self-host siguen usando
    // `php artisan migrate --force` en el release.
    'auto_migrate' => (bool) env('PARCHIS_AUTO_MIGRATE', false),

    /*
    |--------------------------------------------------------------------------
    | Tablero parametrizado por número de brazos
    |--------------------------------------------------------------------------
    |
    | Una única definición genérica. Nunca se escriben tres tableros a mano.
    |
    */

    'view_box' => 800,
    'center' => ['x' => 400, 'y' => 400],
    'outer_radius' => 300,
    'inner_radius' => 205,
    'cell_size' => 16,
    'home_length' => 6,

    // Salida clásica de cada brazo. El color elegido fija el asiento.
    'color_order' => ['yellow', 'blue', 'red', 'green', 'orange', 'purple'],

    'boards' => [
        4 => [
            'cells_per_arm' => 17,
            'geometry' => 'cross',
            'cell_size' => 34,
            'home_length' => 7,
            // 3 seguras por brazo: salida + dos estrellas (12 en total),
            // incluso cuando un brazo no tiene jugador.
            'all_arms_safe' => true,
            'safe_offsets' => [0, 7, 12],
            // El índice interno 0 es la casilla clásica 5; así s_a = a*17.
            'label_offset' => 4,
            // Una única plantilla de 17 celdas, rotada 90° por brazo.
            // Procede del SVG clásico de 68 casillas (rejilla 21x21):
            // la salida 5 está en (+2,+6) y la casilla 68 en (0,+10).
            'cross_arm_offsets' => [
                [2, 6], [2, 5], [2, 4], [2, 3],
                [3, 2], [4, 2], [5, 2], [6, 2],
                [7, 2], [8, 2], [9, 2], [10, 2],
                [10, 0], [10, -1], [9, -1], [8, -1], [7, -1],
            ],
        ],
        6 => ['cells_per_arm' => 10, 'geometry' => 'star'],
    ],

    'colors' => [
        'red' => '#ef4444',
        'yellow' => '#eab308',
        'blue' => '#3b82f6',
        'green' => '#22c55e',
        'orange' => '#f97316',
        'purple' => '#a855f7',
    ],
];
