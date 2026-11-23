<?php

namespace App\Models;

use App\Domain\Game\Enums\GameStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $code
 * @property GameStatus $status
 * @property int|null $host_player_id
 * @property int|null $arms
 * @property array<string, mixed>|null $board_config
 * @property array<string, mixed>|null $state
 * @property int $version
 * @property int|null $winner_player_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class Game extends Model
{
    protected $fillable = [
        'code',
        'status',
        'host_player_id',
        'arms',
        'board_config',
        'state',
        'version',
        'winner_player_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            // En MySQL/PostgreSQL PDO puede devolver los IDs como cadenas.
            // Sin estos casts las comparaciones estrictas de host/turno fallan
            // en producción aunque pasen con SQLite en los tests.
            'host_player_id' => 'integer',
            'winner_player_id' => 'integer',
            'arms' => 'integer',
            'version' => 'integer',
            'board_config' => 'array',
            'state' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<Player, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('seat');
    }

    /** @return BelongsTo<Player, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'host_player_id');
    }

    /** @return HasMany<GameEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class)->orderBy('sequence');
    }

    /** @return HasOne<GameResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(GameResult::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function isWaiting(): bool
    {
        return $this->status->isWaiting();
    }

    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    public function isFinished(): bool
    {
        return $this->status === GameStatus::Finished;
    }
}
