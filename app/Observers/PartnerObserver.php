<?php

namespace App\Observers;

use App\Models\Partner;
use App\Traits\TrackHistoryTrait;
use Illuminate\Support\Facades\Auth;

class PartnerObserver
{
    use TrackHistoryTrait;
    /**
     * Handle the Client "created" event.
     */
    public function created(Partner $partner): void
    {
        $this->create($partner, Auth::id());
    }

    /**
     * Handle the Client "updated" event.
     */
    public function updated(Partner $partner): void
    {
        $this->update($partner, Auth::id());
    }

    /**
     * Handle the Client "deleted" event.
     */
    public function deleted(Partner $partner): void
    {
        $this->delete($partner, Auth::id());
    }

    /**
     * Handle the Client "restored" event.
     */
    public function restored(Partner $partner): void
    {
        $this->restore($partner, Auth::id());
    }

    /**
     * Handle the Client "force deleted" event.
     */
    public function forceDeleted(Partner $partner): void
    {
        $this->forceDelete($partner, Auth::id());
    }
}
