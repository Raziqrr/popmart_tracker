<?php

namespace App\Policies;

use App\Models\PopmartAccount;
use App\Models\User;

class PopmartAccountPolicy
{
    /** Act on Pop Mart with this account (claim, lock, redeem…): only its owner. */
    public function use(User $user, PopmartAccount $account): bool
    {
        return (int) $account->user_id === (int) $user->id;
    }
}
