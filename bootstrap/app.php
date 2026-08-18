<?php

use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'force-password-change' => ForcePasswordChange::class,
        ]);

        // RSMS runs behind Coolify's Traefik reverse proxy; the app
        // container's own port is never reached directly from outside, and
        // Traefik's internal-network IP isn't static, so there's no fixed
        // address to whitelist -- trusting '*' is Laravel's own documented
        // configuration for exactly this topology (an app that is only
        // ever reached through a proxy whose address you don't control),
        // not a blanket bypass of anything. Without this, Laravel never
        // reads X-Forwarded-Proto and treats every request as plain HTTP
        // even when Traefik terminated real HTTPS in front of it, which is
        // why the login page rendered but its asset/URL generation silently
        // fell back to http:// and never loaded.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
