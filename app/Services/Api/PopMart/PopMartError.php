<?php

namespace App\Services\Api\PopMart;

/**
 * Why a Pop Mart call failed. The value is the `error.code` our API returns,
 * mirrored by ApiErrorCode in resources/js/lib/api.ts.
 */
enum PopMartError: string
{
    case SessionExpired = 'popmart_session_expired';
    case RateLimited = 'popmart_rate_limited';
    case Unreachable = 'popmart_unreachable';
    case Rejected = 'popmart_rejected';
    case UnexpectedResponse = 'popmart_unexpected_response';

    /** HTTP status our own API answers with. */
    public function httpStatus(): int
    {
        return match ($this) {
            self::SessionExpired => 409,
            self::RateLimited => 429,
            self::Unreachable => 503,
            self::Rejected, self::UnexpectedResponse => 502,
        };
    }
}
