<?php

namespace App\Services\Api\PopMart\Endpoints\Activity;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Claims a completed task's reward into the Lucky Points gauge. Takes the
// full userTask object (from GetTasksByActivityAndChannel) as payload, not just an ID.
class ClaimTask extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Activity;

    protected string $path = 'task/draw';
}
