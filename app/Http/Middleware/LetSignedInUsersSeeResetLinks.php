<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "guest" middleware, except that someone who is already logged in can still open a
 * password link. Instead of being bounced to their dashboard (which looked like a broken
 * link), the page tells them which account they are in and offers to log out and continue.
 */
class LetSignedInUsersSeeResetLinks extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->isMethod('GET') && $request->routeIs('password.reset')) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
