<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Middleware\PreviewRole;
use App\Models\CampusAmbassador;
use App\Models\Delegate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePreviewController extends Controller
{
    /**
     * Start viewing the site as another role. For "Delegate", show a real delegate's
     * dashboard: the one picked, or else the latest one with a published allocation.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canPreviewRoles(), 403);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)->except([UserRole::Admin])],
            'delegate' => ['nullable', 'integer', 'exists:delegates,id'],
            'ambassador' => ['nullable', 'integer', 'exists:campus_ambassadors,id'],
        ]);

        $role = UserRole::from($validated['role']);
        $delegateId = null;

        if ($role === UserRole::Delegate) {
            $delegateId = $validated['delegate']
                ?? Delegate::whereNotNull('allocation_published_at')->latest('allocation_published_at')->value('id')
                ?? Delegate::latest('id')->value('id');
        }

        // For "Campus ambassador", show the portal of the one picked, or else the top referrer.
        $ambassadorId = null;

        if ($role === UserRole::Ambassador) {
            $ambassadorId = $validated['ambassador']
                ?? CampusAmbassador::withReferralCounts()->orderByDesc('verified_delegates_count')->orderBy('id')->value('id');
        }

        $request->session()->put([
            PreviewRole::SESSION_ROLE => $role->value,
            PreviewRole::SESSION_DELEGATE => $delegateId,
            PreviewRole::SESSION_AMBASSADOR => $ambassadorId,
        ]);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([PreviewRole::SESSION_ROLE, PreviewRole::SESSION_DELEGATE, PreviewRole::SESSION_AMBASSADOR]);

        return redirect()->route('dashboard');
    }
}
