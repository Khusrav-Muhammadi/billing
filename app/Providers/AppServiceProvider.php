<?php

namespace App\Providers;

use App\Repositories\ClientRepository;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\OrganizationRepository;
use App\Repositories\PartnerRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->singleton(PartnerRepositoryInterface::class, PartnerRepository::class);
        $this->app->singleton(OrganizationRepositoryInterface::class, OrganizationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
