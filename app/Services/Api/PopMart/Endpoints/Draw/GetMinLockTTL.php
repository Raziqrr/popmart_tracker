<?php

namespace App\Services\Api\PopMart\Endpoints\Draw;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Time left on the soonest-expiring of the given held boxes, in one call; the checkout
// page polls it. Payload: { spuId, setNo, boxNos: [...] } -> { lockRemainingSeconds }.
// Cheaper than one CheckSetBoxLock per box when all you need is the deadline.
class GetMinLockTTL extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Draw;

    protected string $path = 'box/getMinLockTTL';
}
