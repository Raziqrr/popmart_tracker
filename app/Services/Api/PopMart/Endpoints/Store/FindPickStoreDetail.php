<?php

namespace App\Services\Api\PopMart\Endpoints\Store;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;

class FindPickStoreDetail extends PopMartEndpoint
{
    protected PopMartDomain $domain = PopMartDomain::Store;

    protected string $path = 'public_findPickStoreDetail';
}
