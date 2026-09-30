<?php

namespace App\Services\Api\PopMart\Endpoints\Draw;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Locks boxes for the session. Payload: { spuId, setNo, boxNos: [boxNo, ...] }
// (an array; a bare boxNo is rejected with 422). One call can lock every free box in
// a set, and later calls add to what the session already holds. Each newly locked
// box gets its own ~300s hold; boxes already held keep their running timer, so
// re-sending a held box does NOT extend it. The response's lockRemainingSeconds is
// the soonest-expiring hold. Confirmed live on 2026-09-29.
class EnterBox extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Draw;

    protected string $path = 'box/enterBox';
}
