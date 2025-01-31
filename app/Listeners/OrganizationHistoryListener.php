<?php

namespace App\Listeners;

use App\Events\OrganizationHistoryEvent;
use App\Traits\TrackHistoryTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class OrganizationHistoryListener
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
    public function handle(OrganizationHistoryEvent $event): void
    {
        $this->update($event->organization, $event->authId);
    }
}
