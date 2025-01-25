<?php

namespace App\Observers;

use App\Models\Client;
use App\Traits\TrackHistoryTrait;
use Illuminate\Support\Facades\Auth;

class ClientObserver
{
    use TrackHistoryTrait;
    /**
     * Handle the Client "created" event.
     */
    public function created(Client $client): void
    {
        $this->create($client, Auth::id());
    }

    /**
     * Handle the Client "updated" event.
     */
    public function updated(Client $client): void
    {
        if (isset($client->disableObserver) && $client->disableObserver) {
            return;
        }
        $this->update($client, Auth::id());
    }

    /**
     * Handle the Client "deleted" event.
     */
    public function deleted(Client $client): void
    {
        $this->delete($client, Auth::id());
    }

    /**
     * Handle the Client "restored" event.
     */
    public function restored(Client $client): void
    {
        $this->restore($client, Auth::id());
    }

    /**
     * Handle the Client "force deleted" event.
     */
    public function forceDeleted(Client $client): void
    {
        $this->forceDelete($client, Auth::id());
    }
}
