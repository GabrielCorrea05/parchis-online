<?php

namespace App\Console\Commands;

use App\Domain\Game\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Console\Command;

final class CleanupAbandonedRooms extends Command
{
    protected $signature = 'parchis:cleanup-abandoned-rooms
                            {--hours= : Horas de inactividad; por defecto config/parchis.php}';

    protected $description = 'Borra las salas en espera abandonadas y todos sus datos asociados';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? config('parchis.abandoned_room_hours', 24));
        $cutoff = now()->subHours(max(1, $hours));

        $query = Game::query()
            ->where('status', GameStatus::Waiting)
            ->where('updated_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No hay salas abandonadas que borrar.');

            return self::SUCCESS;
        }

        // players, game_events y game_results se borran en cascada (FK).
        $query->delete();

        $this->info("Se borraron {$count} sala(s) abandonada(s) sin actividad desde {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
