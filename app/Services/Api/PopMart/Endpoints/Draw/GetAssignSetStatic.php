<?php

namespace App\Services\Api\PopMart\Endpoints\Draw;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;

// Static set config for a product (dimensions, skus, images) — no live boxNo/status data.
class GetAssignSetStatic extends PopMartEndpoint
{
    protected PopMartDomain $domain = PopMartDomain::Draw;

    protected string $path = 'set/public_getAssignSetStatic';
}
