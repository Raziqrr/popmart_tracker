<?php

namespace App\Services\Api\PopMart\Endpoints\Auth;

use App\Services\Api\Endpoint;
use App\Services\Api\PopMart\PopMartSegment;
use App\Services\Api\RequiresAuthentication;

class GetSession extends Endpoint implements RequiresAuthentication
{
    protected string $client = 'popmart';

    protected string $method = 'GET';

    protected function segments(): array
    {
        return [PopMartSegment::Auth->value, 'get-session'];
    }
}
