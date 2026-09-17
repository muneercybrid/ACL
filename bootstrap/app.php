<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies (required for GitHub Codespaces port forwarding).
        // This forces Laravel to generate URLs using the forwarded Codespaces domain
        // (e.g., https://scaling-zebra...app.github.dev) instead of internal localhost.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\RequireSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
