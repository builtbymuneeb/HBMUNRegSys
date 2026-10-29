<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Middleware\PreviewRole;
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
        ]);

        $role = UserRole::from($validated['role']);
        $delegateId = null;

        if ($role === UserRole::Delegate) {
            $delegateId = $validated['delegate']
                ?? Delegate::whereNotNull('allocation_published_at')->latest('allocation_published_at')->value('id')
                ?? Delegate::latest('id')->value('id');
        }

        $request->session()->put([
            PreviewRole::SESSION_ROLE => $role->value,
            PreviewRole::SESSION_DELEGATE => $delegateId,
        ]);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([PreviewRole::SESSION_ROLE, PreviewRole::SESSION_DELEGATE]);

        return redirect()->route('dashboard');
    }
}
