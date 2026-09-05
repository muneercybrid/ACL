<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureLocalDevelopmentUrls();
        $this->registerAdministratorBypass();
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

    /**
     * Platform administrators pass every authorization check.
     *
     * This replaces a loop that defined one Gate per row in the permissions
     * table on each boot. Nothing consumed those gates, they cost a schema
     * lookup plus a full table read on every request, and they made the
     * container unbootable whenever the database was unreachable.
     */
    private function registerAdministratorBypass(): void
    {
        Gate::before(function (User $user) {
            // Return null, not false, so that a non-administrator falls
            // through to the policy instead of being denied outright.
            return $user->isPlatformAdministrator() ? true : null;
        });
    }
}
