<?php

namespace App\Listeners;

use App\Events\ClientHistoryEvent;
use App\Traits\TrackHistoryTrait;

class ClientHistoryListener
{
    use TrackHistoryTrait;
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ClientHistoryEvent $event): void
    {
        $this->update($event->client, $event->authId);
    }
}
