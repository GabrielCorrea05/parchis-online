<?php

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TurnAdvanced implements ShouldBroadcastNow
{
    use BroadcastsAsShortName;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $gameId,
        public array $snapshot,
        public int $fromPlayerId,
        public int $playerId,
        public string $reason,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('game.'.$this->gameId);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'snapshot' => $this->snapshot,
            'fromPlayerId' => $this->fromPlayerId,
            'playerId' => $this->playerId,
            'reason' => $this->reason,
        ];
    }
}
