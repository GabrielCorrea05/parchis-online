<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $game_id
 * @property int $sequence
 * @property string $type
 * @property array<string, mixed>|null $payload
 */
class GameEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'game_id',
        'sequence',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
