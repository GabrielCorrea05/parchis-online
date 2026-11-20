<?php

use App\Console\Commands\CleanupAbandonedRooms;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Laravel Cloud ejecuta `schedule:run` cada minuto cuando el scheduler está
 | activado en el panel. Borra salas en espera abandonadas; las partidas
 | terminadas se conservan para el historial público.
 */
Schedule::command(CleanupAbandonedRooms::class)
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
