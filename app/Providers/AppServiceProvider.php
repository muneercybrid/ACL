<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ACLi\AcliCapabilityService;
use App\Services\ACLi\AcliContextService;
use App\Services\ACLi\AcliEntitlementService;
use App\Services\ACLi\AcliOrchestrator;
use App\Services\ACLi\CurriculumContextService;
use App\Services\ACLi\ImageGenerationService;
use App\Services\ACLi\ProviderManager;
use App\Services\ACLi\Providers\CloudflareProvider;
use App\Services\ACLi\Providers\OmniRouteProvider;
use App\Services\ACLi\Providers\PollinationsImageProvider;
use App\Services\ACLi\Providers\TokenHarborProvider;
use App\Services\ACLi\Providers\UnifiedProvider;
use App\Services\EntitlementService;
use App\Services\StudentDashboardService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ACLi Provider Manager. The unified provider is a
        // failover chain over Token Harbor, Cloudflare and
        // OmniRoute; the individual providers are registered
        // too so they stay resolvable by name for tooling and
        // for the superadmin model tests.
        $this->app->singleton(ProviderManager::class, function ($app) {
            $manager = new ProviderManager();
            $manager->register(new UnifiedProvider());
            $manager->register(new TokenHarborProvider());
            $manager->register(new CloudflareProvider());
            $manager->register(new OmniRouteProvider());
            return $manager;
        });

        // ACLi Services
        // Image generation is separate from the chat chain: none of the
        // text backends can render images on a free plan, so image
        // requests get their own service and provider.
        $this->app->singleton(PollinationsImageProvider::class, fn ($app) => new PollinationsImageProvider());
        $this->app->singleton(ImageGenerationService::class, fn ($app) => new ImageGenerationService(
            $app->make(PollinationsImageProvider::class)
        ));

        $this->app->singleton(AcliCapabilityService::class, fn ($app) => new AcliCapabilityService());
        $this->app->singleton(AcliEntitlementService::class, fn ($app) => new AcliEntitlementService(
            $app->make(EntitlementService::class)
        ));
        $this->app->singleton(AcliContextService::class, fn ($app) => new AcliContextService(
            $app->make(StudentDashboardService::class),
            $app->make(CurriculumContextService::class),
        ));
        $this->app->singleton(AcliOrchestrator::class, fn ($app) => new AcliOrchestrator(
            $app->make(ProviderManager::class),
            $app->make(AcliCapabilityService::class),
            $app->make(AcliEntitlementService::class),
            $app->make(AcliContextService::class),
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
