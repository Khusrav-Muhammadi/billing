<?php

namespace App\Providers;

use App\Events\ClientHistoryEvent;
use App\Events\CommercialOfferAiTopUpPaidStatusEvent;
use App\Events\CommercialOfferExtraServicesPaidStatusEvent;
use App\Events\CommercialOfferPaidStatusEvent;
use App\Events\CommercialOfferRenewalNoChangePaidStatusEvent;
use App\Events\CommercialOfferRenewalPaidStatusEvent;
use App\Events\OrganizationHistoryEvent;
use App\Listeners\ClientHistoryListener;
use App\Listeners\CommercialOfferAiTopUpPaidStatusListener;
use App\Listeners\CommercialOfferExtraServicesPaidStatusListener;
use App\Listeners\CommercialOfferPaidStatusListener;
use App\Listeners\CommercialOfferRenewalNoChangePaidStatusListener;
use App\Listeners\CommercialOfferRenewalPaidStatusListener;
use App\Listeners\OrganizationHistoryListener;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Partner;
use App\Observers\ClientObserver;
use App\Observers\OrganizationObserver;
use App\Observers\PartnerObserver;
use App\Models\Ai\AiTariffPlan;
use App\Observers\AiTariffPlanObserver;
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
        ],

        CommercialOfferPaidStatusEvent::class => [
            CommercialOfferPaidStatusListener::class,
        ],

        CommercialOfferExtraServicesPaidStatusEvent::class => [
            CommercialOfferExtraServicesPaidStatusListener::class,
        ],

        CommercialOfferRenewalPaidStatusEvent::class => [
            CommercialOfferRenewalPaidStatusListener::class,
        ],

        CommercialOfferRenewalNoChangePaidStatusEvent::class => [
            CommercialOfferRenewalNoChangePaidStatusListener::class,
        ],

        CommercialOfferAiTopUpPaidStatusEvent::class => [
            CommercialOfferAiTopUpPaidStatusListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Client::observe(ClientObserver::class);
        Organization::observe(OrganizationObserver::class);
        Partner::observe(PartnerObserver::class);
        AiTariffPlan::observe(AiTariffPlanObserver::class);

        // Тёплые лиды: новая заявка на демо без партнёра/менеджера уходит партнёру в очереди.
        // Задержка — чтобы ProvisionDemoAccountJob успел создать клиента (его закрепим за партнёром).
        \App\Models\DemoRequest::created(function (\App\Models\DemoRequest $request) {
            if (\App\Services\PartnerProgram\PartnerLeadService::enabled() && !$request->partner_id && !$request->manager_id) {
                \App\Jobs\CreatePartnerLeadJob::dispatch($request)->delay(now()->addMinute());
            }
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
