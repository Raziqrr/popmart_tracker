<?php

namespace App\Services\Api\PopMart\Endpoints\Draw;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Moves one held box to another. Payload: { spuId, setNo, currentBoxNo, direction, boxNo }
// where direction is 'left' | 'right' | 'direct' and boxNo is the target for 'direct'
// (only 'direct' is confirmed). The old box is released and the new one gets a fresh
// ~300s hold. Not a bulk lock: it takes no list. Confirmed live on 2026-09-29.
class SwitchBox extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Draw;

    protected string $path = 'box/switchBox';
}
