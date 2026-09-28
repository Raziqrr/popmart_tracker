<?php

namespace App\Services\Api\PopMart\Endpoints\Store;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;

class FindPickupStoresByCoord extends PopMartEndpoint
{
    protected PopMartDomain $domain = PopMartDomain::Store;

    protected string $path = 'public_findPickupStoresByCoord';
}
