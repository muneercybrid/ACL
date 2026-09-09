<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ACLi\ProviderManager;
use App\Services\ACLi\Providers\NvidiaProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class, function ($app) {
            $manager = new ProviderManager();

            $manager->register(new NvidiaProvider());

            return $manager;
        });
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
