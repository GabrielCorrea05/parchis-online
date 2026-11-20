<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $game_id
 * @property int|null $winner_player_id
 * @property int $board_arms
 * @property int $duration_seconds
 * @property int $turns
 * @property int $total_rolls
 * @property int $total_captures
 * @property array<string, mixed> $ranking
 * @property array<string, mixed> $payload
 * @property Carbon|null $finished_at
 */
class GameResult extends Model
{
    protected $fillable = [
        'game_id',
        'winner_player_id',
        'board_arms',
        'duration_seconds',
        'turns',
        'total_rolls',
        'total_captures',
        'ranking',
        'payload',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'ranking' => 'array',
            'payload' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }
}
