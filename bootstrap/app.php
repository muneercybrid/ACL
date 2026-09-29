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
            'force.edit.profile' => \App\Http\Middleware\ForceEditProfile::class,
        ]);

        // ForceEditProfile is composed with the authentication middleware
        // rather than appended to a group named 'auth'.
        //
        // In Laravel 11 and later 'auth' is a middleware *alias*, not a group.
        // Calling appendToGroup('auth', ...) therefore defines a new group
        // under that name, and route middleware resolves groups before aliases
        // — so every route declaring ->middleware('auth') began resolving to
        // this middleware alone. Authenticate stopped running entirely and an
        // unauthenticated request reached controllers that call Auth::user(),
        // which is a null-dereference 500 rather than a redirect to login.
        //
        // Declaring the group explicitly with Authenticate first keeps the
        // alias working, restores the authentication check, and runs the
        // profile guard immediately after it.
        $middleware->group('auth', [
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ForceEditProfile::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
