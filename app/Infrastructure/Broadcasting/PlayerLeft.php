<?php

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PlayerLeft implements ShouldBroadcastNow
{
    use BroadcastsAsShortName;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string, mixed> $room */
    public function __construct(
        public string $code,
        public array $room,
        public string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('room.'.$this->code);
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['room' => $this->room, 'message' => $this->message];
    }
}
