<?php

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RematchCreated implements ShouldBroadcastNow
{
    use BroadcastsAsShortName;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public string $oldCode,
        public string $newCode,
        public string $message,
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
            'oldCode' => $this->oldCode,
            'newCode' => $this->newCode,
            'message' => $this->message,
        ];
    }
}
