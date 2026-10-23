<?php

namespace App\Repositories;

use App\Models\PopmartAccount;
use App\Models\User;
use App\Services\Api\PopMart\PopMartSession;
use Carbon\Carbon;

class PopmartAccountRepository
{
    /**
     * Save a session Pop Mart has already confirmed (GetSession::fetch()).
     * Reconnecting the same Pop Mart member updates that row instead of adding one.
     */
    public function connect(User $user, string $cookie, PopMartSession $session, ?string $expiresAt): PopmartAccount
    {
        return PopmartAccount::updateOrCreate(
            ['user_id' => $user->id, 'popmart_member_id' => $session->userId],
            [
                'session_cookie' => $cookie,
                'session_expires_at' => $expiresAt ? Carbon::parse($expiresAt) : $session->expiresAt,
                'last_synced_at' => now(),
            ],
        );
    }
}
