<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\LogoutResponse as FortifyLogoutResponse;

/**
 * After logging out, go back to the password link the person was opening, if that is why
 * they logged out. Only our own /reset-password/ addresses are allowed, never other sites.
 */
class LogoutResponse extends FortifyLogoutResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $continue = (string) $request->input('continue');

        if (preg_match('#^/reset-password/[A-Za-z0-9]+(\?email=[^\s<>"]*)?$#', $continue)) {
            return redirect($continue);
        }

        return redirect(Fortify::redirects('logout', '/'));
    }
}
