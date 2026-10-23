<?php

namespace App\Auth;

use App\Models\User;

/**
 * Who's logged in and what they can do: AuthState in resources/js/lib/capabilities.ts.
 * Served by GET /api/me and shared with every Inertia page as `auth`.
 * Display only: every gated route still checks for itself.
 */
final class AuthState
{
    /** @return array{user: array|null, popmart: array|null, capabilities: list<string>} */
    public static function for(?User $user): array
    {
        $user?->load('popmartAccounts');
        $account = $user?->livePopmartAccount() ?? $user?->popmartAccounts->sortByDesc('last_synced_at')->first();

        return [
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null,
            'popmart' => $account ? [
                'account_id' => $account->id,
                'session_valid' => $account->sessionIsValid(),
                'session_expires_at' => $account->session_expires_at,
            ] : null,
            'capabilities' => Capability::allowedFor($user),
        ];
    }
}
