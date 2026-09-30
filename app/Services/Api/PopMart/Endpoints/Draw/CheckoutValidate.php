<?php

namespace App\Services\Api\PopMart\Endpoints\Draw;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Sent by Pop Mart's "Buy now" before it opens checkout. Payload:
// { spuId, setNo, boxNos: [...], pageType: 'checkout' } -> { valid, lockRemainingSeconds }.
// Does NOT extend the hold: checkout runs on the same ~300s box timer (confirmed 2026-09-29).
class CheckoutValidate extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Draw;

    protected string $path = 'box/checkoutValidate';
}
