<?php

namespace App\Services\Api\PopMart\Endpoints\Activity;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

class GetTasksByActivityAndChannel extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Activity;

    protected string $path = 'task/public_getTasksByActivityAndChannel';
}
