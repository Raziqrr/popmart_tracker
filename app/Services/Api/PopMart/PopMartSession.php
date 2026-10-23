<?php

namespace App\Services\Api\PopMart;

use Illuminate\Support\Carbon;

/** A Pop Mart session confirmed live by GetSession::fetch(). */
final readonly class PopMartSession
{
    public function __construct(
        public string $userId,
        public ?Carbon $expiresAt,
    ) {}
}
