<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A delegate whose registration has been blocked, archived or binned can't use the signed-in
 * pages. They're sent to a page that says why and lets them appeal. Staff are never affected.
 */
class SendRestrictedDelegatesAway
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user
            || $user->isStaff()
            || ! $route
            || $request->routeIs('account.restricted', 'logout')
            || ! in_array('auth', $route->gatherMiddleware(), true)
            || ! $user->restrictedRegistration()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __('Your registration is on hold.'));
        }

        return redirect()->route('account.restricted');
    }
}
