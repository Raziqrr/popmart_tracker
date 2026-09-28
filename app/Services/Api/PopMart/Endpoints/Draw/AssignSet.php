<?php

namespace App\Services\Api\PopMart\Endpoints\Draw;

use App\Services\Api\PopMart\PopMartDomain;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\RequiresAuthentication;

// Assigns the requesting session a live set for a product and returns its
// current setNo + full box grid (status/lock state per box) — this is the
// real mechanism for discovering a fresh setNo, not something scraped off a page.
class AssignSet extends PopMartEndpoint implements RequiresAuthentication
{
    protected PopMartDomain $domain = PopMartDomain::Draw;

    protected string $path = 'set/public_assignSet';
}
