<?php

namespace App\Observers;

use App\Models\Organization;
use App\Traits\TrackHistoryTrait;
use Illuminate\Support\Facades\Auth;

class OrganizationObserver
{
    use TrackHistoryTrait;
    /**
     * Handle the Client "created" event.
     */
    public function created(Organization $organization): void
    {
        $this->create($organization, Auth::id());
    }

    /**
     * Handle the Client "updated" event.
     */
    public function updated(Organization $organization): void
    {
        $this->update($organization, Auth::id());
    }

    /**
     * Handle the Client "deleted" event.
     */
    public function deleted(Organization $organization): void
    {
        $this->delete($organization, Auth::id());
    }

    /**
     * Handle the Client "restored" event.
     */
    public function restored(Organization $organization): void
    {
        $this->restore($organization, Auth::id());
    }

    /**
     * Handle the Client "force deleted" event.
     */
    public function forceDeleted(Organization $organization): void
    {
        $this->forceDelete($organization, Auth::id());
    }
}
