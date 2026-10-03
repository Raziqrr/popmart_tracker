<?php

namespace App\Services\Api\PopMart\Endpoints\Auth;

use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Api\PopMart\PopMartSegment;
use App\Services\Api\PopMart\PopMartSession;
use App\Services\Api\RequiresAuthentication;
use Illuminate\Support\Carbon;

// The logged-in session behind a cookie: GET api/auth/get-session. Not an RPC
// call, so the body isn't wrapped in "json". An expired or unknown session
// answers with an empty body or no session.userId instead of an error status.
class GetSession extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartSegment $segment = PopMartSegment::Auth;

    protected string $method = 'GET';

    protected function segments(): array
    {
        return [$this->segment->value, 'get-session'];
    }

    /**
     * Checks the cookie with Pop Mart and returns whose session it is.
     *
     * @throws PopMartException SessionExpired when Pop Mart doesn't recognise the session
     */
    public function fetch(): PopMartSession
    {
        $body = $this->send()->json();
        $userId = is_array($body) ? data_get($body, 'session.userId') : null;

        if (! is_string($userId) || $userId === '') {
            throw PopMartException::sessionExpired();
        }

        $expiresAt = data_get($body, 'session.expiresAt');

        return new PopMartSession(
            userId: $userId,
            expiresAt: is_string($expiresAt) ? Carbon::parse($expiresAt) : null,
        );
    }
}
