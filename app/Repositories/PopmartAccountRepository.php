<?php

namespace App\Repositories;

use App\Models\PopmartAccount;
use App\Models\User;
use Carbon\Carbon;

class PopmartAccountRepository
{
    public function connect(User $user, string $cookie, ?string $expiresAt): PopmartAccount
    {
        // TODO: upsert on (user_id, popmart_member_id) once the extension captures
        // the real member ID — for now every connect creates a new row so testing
        // with multiple Pop Mart accounts doesn't clobber the previous one.
        return PopmartAccount::create([
            'user_id' => $user->id,
            'session_cookie' => $cookie,
            'session_expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
            'last_synced_at' => now(),
        ]);
    }
}
