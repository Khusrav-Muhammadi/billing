<?php

namespace App\Events;

use App\Models\Client;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientHistoryEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Client $client;
    public int $authId;

    /**
     * Create a new event instance.
     */
    public function __construct(Client $client, int $authId)
    {
        $this->client = $client;
        $this->authId = $authId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
