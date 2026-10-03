<?php

namespace App\Http\Controllers;

use App\Auth\Capability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/me: who's logged in and what they can do, for the frontend to
 * show or hide actions (resources/js/lib/capabilities.ts). Works for guests.
 * Display only: every gated route still checks for itself.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->loadMissing('popmartAccounts');
        $account = $user?->livePopmartAccount() ?? $user?->popmartAccounts->sortByDesc('last_synced_at')->first();

        return response()->json([
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null,
            'popmart' => $account ? [
                'account_id' => $account->id,
                'session_valid' => $account->sessionIsValid(),
                'session_expires_at' => $account->session_expires_at,
            ] : null,
            'capabilities' => Capability::allowedFor($user),
        ]);
    }
}
