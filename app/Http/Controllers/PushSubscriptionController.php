<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Save the browser's push subscription for the signed-in user. A browser that was
     * signed in as someone else before now gets this user's notifications instead.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        PushSubscription::updateOrCreate(['endpoint' => $validated['endpoint']], [
            'user_id' => $request->user()->id,
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
        ]);

        return response()->noContent();
    }

    /**
     * Forget the browser's push subscription, e.g. after the delegate turns notifications off.
     */
    public function destroy(Request $request): Response
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        $request->user()->pushSubscriptions()->where('endpoint', $validated['endpoint'])->delete();

        return response()->noContent();
    }
}
