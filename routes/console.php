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
 | activado en el panel. En Wasmer se ejecuta el propio comando mediante un job
 | diario, sin `schedule:run`, para no consumir las horas de cómputo incluidas.
 | Las partidas terminadas se conservan para el historial público.
 */
Schedule::command(CleanupAbandonedRooms::class)
    ->dailyAt('03:00')
    ->withoutOverlapping(10)
    ->onOneServer();
