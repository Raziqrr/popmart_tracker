<?php

namespace App\Services\Api\PopMart\Endpoints\Ec;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;

class FindDrawSpusByCollectionId extends PopMartEndpoint
{
    protected PopMartDomain $domain = PopMartDomain::Ec;

    protected string $path = 'spu/public_FindDrawSpusByCollectionId';
}
