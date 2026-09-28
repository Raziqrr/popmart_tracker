<?php

namespace App\Services\Api\PopMart\Endpoints\Ec;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

class HasDrawOrder extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Ec;

    protected string $path = 'order/hasDrawOrder';
}
