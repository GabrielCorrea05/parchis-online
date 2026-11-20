<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    protected $fillable = [
        'game_id',
        'nickname',
        'color',
        'token_hash',
        'is_host',
        'is_connected',
        'seat',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_connected' => 'boolean',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isHostOf(Game $game): bool
    {
        return $game->host_player_id === $this->id;
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthIdentifierForBroadcasting(): mixed
    {
        return $this->getKey();
    }
}
