<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ACLi\AcliCapabilityService;
use App\Services\ACLi\AcliEntitlementService;
use App\Services\ACLi\AcliOrchestrator;
use App\Services\ACLi\ProviderManager;
use App\Services\ACLi\Providers\OmniRouteProvider;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ACLi Provider Manager
        $this->app->singleton(ProviderManager::class, function ($app) {
            $manager = new ProviderManager();
            $manager->register(new OmniRouteProvider());
            return $manager;
        });

        // ACLi Services
        $this->app->singleton(AcliCapabilityService::class, fn ($app) => new AcliCapabilityService());
        $this->app->singleton(AcliEntitlementService::class, fn ($app) => new AcliEntitlementService(
            $app->make(EntitlementService::class)
        ));
        $this->app->singleton(AcliOrchestrator::class, fn ($app) => new AcliOrchestrator(
            $app->make(ProviderManager::class),
            $app->make(AcliCapabilityService::class),
            $app->make(AcliEntitlementService::class),
        ));
    }

    public function boot(): void
    {
        $this->configureLocalDevelopmentUrls();
    }

    private function configureLocalDevelopmentUrls(): void
    {
        if (! app()->environment('local') || app()->runningInConsole()) {
            return;
        }

        // getHttpHost() includes the port if it's non-standard (e.g., localhost:8000)
        $host = request()->getHttpHost();

        $scheme = request()->header('x-forwarded-proto')
            ?? (str_ends_with($host, '.app.github.dev') ? 'https' : 'http');

        URL::forceRootUrl($scheme.'://'.$host);
    }
}
