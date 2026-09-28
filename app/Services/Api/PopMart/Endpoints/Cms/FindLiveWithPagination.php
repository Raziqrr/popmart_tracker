<?php

namespace App\Services\Api\PopMart\Endpoints\Cms;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;

class FindLiveWithPagination extends PopMartEndpoint
{
    protected PopMartDomain $domain = PopMartDomain::Cms;

    protected string $path = 'content/public_findLiveWithPagination';
}
