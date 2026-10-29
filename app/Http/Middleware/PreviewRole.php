<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Delegate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets an admin see the site as another role without a second account. The role
 * is swapped on the logged-in user for this request only, so every gate, policy
 * and menu sees the previewed role; nothing is written to the database.
 */
class PreviewRole
{
    public const SESSION_ROLE = 'preview_role';

    public const SESSION_DELEGATE = 'preview_delegate';

    public function handle(Request $request, Closure $next): Response
    {
        $role = UserRole::tryFrom((string) $request->session()->get(self::SESSION_ROLE));
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // The same user object can serve more than one request (tests, Octane), so start from the real role.
        if ($user->actualRole !== null) {
            $user->role = $user->actualRole;
            $user->actualRole = null;
            $user->syncOriginalAttribute('role');
            $user->unsetRelation('delegate');
        }

        if ($role === null) {
            return $next($request);
        }

        if ($user->role !== UserRole::Admin) {
            $request->session()->forget([self::SESSION_ROLE, self::SESSION_DELEGATE]);

            return $next($request);
        }

        $user->actualRole = $user->role;
        $user->role = $role;
        // Mark the swapped role as unchanged so a save() elsewhere can't write it.
        $user->syncOriginalAttribute('role');

        if ($role === UserRole::Delegate) {
            $user->setRelation('delegate', Delegate::find($request->session()->get(self::SESSION_DELEGATE)));
        }

        return $next($request);
    }
}
