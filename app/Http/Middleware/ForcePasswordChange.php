<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects any authenticated user with must_change_password=true to the
 * password-change page (P2B) -- a server-side redirect, not merely a UI
 * warning, so a user cannot reach normal RSMS workflows on a temporary
 * password by skipping past a banner. Logout and the password-change page
 * itself sit outside the route group this middleware is applied to (see
 * routes/web.php), so both remain reachable without a special-case here.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->must_change_password) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
