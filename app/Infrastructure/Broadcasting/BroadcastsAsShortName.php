<?php

namespace App\Infrastructure\Broadcasting;

/**
 * Los eventos se emiten con su nombre corto (por ejemplo, "PlayerJoined")
 * para que el cliente no dependa del namespace de PHP.
 */
trait BroadcastsAsShortName
{
    public function broadcastAs(): string
    {
        return class_basename($this);
    }
}
