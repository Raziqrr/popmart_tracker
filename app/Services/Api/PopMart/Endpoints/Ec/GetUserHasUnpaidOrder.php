<?php

namespace App\Services\Api\PopMart\Endpoints\Ec;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Payload: { orderType: 'draw' } -> { hasUnpaidOrder, closeCountdown }. closeCountdown is
// presumably the seconds left to pay an unpaid order; not yet seen non-zero.
class GetUserHasUnpaidOrder extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Ec;

    protected string $path = 'order/getUserHasUnpaidOrder';
}
