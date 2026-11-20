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

    'boards' => [
        3 => ['cells_per_arm' => 12, 'geometry' => 'triangle'],
        4 => ['cells_per_arm' => 13, 'geometry' => 'cross'],
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
