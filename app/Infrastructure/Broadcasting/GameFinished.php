<?php

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GameFinished implements ShouldBroadcastNow
{
    use BroadcastsAsShortName;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public int $gameId,
        public array $snapshot,
        public array $result,
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
            'result' => $this->result,
        ];
    }
}
