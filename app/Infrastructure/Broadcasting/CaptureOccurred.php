<?php

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CaptureOccurred implements ShouldBroadcastNow
{
    use BroadcastsAsShortName;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $captured
     */
    public function __construct(
        public int $gameId,
        public array $snapshot,
        public int $playerId,
        public array $captured,
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
            'playerId' => $this->playerId,
            'captured' => $this->captured,
        ];
    }
}
