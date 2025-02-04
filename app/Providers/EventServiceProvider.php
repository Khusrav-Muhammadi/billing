<?php

namespace App\Providers;

use App\Events\ClientHistoryEvent;
use App\Events\OrganizationHistoryEvent;
use App\Listeners\ClientHistoryListener;
use App\Listeners\OrganizationHistoryListener;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Partner;
use App\Observers\ClientObserver;
use App\Observers\OrganizationObserver;
use App\Observers\PartnerObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        ClientHistoryEvent::class => [
            ClientHistoryListener::class
        ],

        OrganizationHistoryEvent::class => [
            OrganizationHistoryListener::class
        ]
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Client::observe(ClientObserver::class);
    //    Organization::observe(OrganizationObserver::class);
        Partner::observe(PartnerObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
