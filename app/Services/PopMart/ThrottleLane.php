<?php

namespace App\Services\PopMart;

/** Which share of the Pop Mart call budget a call draws from (PopMartThrottle). */
enum ThrottleLane: string
{
    /** Scheduled sync and the house account. Capped below the full budget. */
    case Background = 'background';

    /** Something a user just asked for (lock, claim, refresh). Can use the whole budget. */
    case User = 'user';
}
