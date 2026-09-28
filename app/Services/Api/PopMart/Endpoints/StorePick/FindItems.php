<?php

namespace App\Services\Api\PopMart\Endpoints\StorePick;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

class FindItems extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::StorePick;

    protected string $path = 'storePickCart/findItems';
}
