<?php

namespace App\Services\Api\PopMart;

// The path segment right after the host. Almost everything is 'rpc'; only
// session/auth uses 'api/auth'. Kept as an enum so a future API version
// bump or added segment is a one-line change, not a find-and-replace.
enum PopMartSegment: string
{
    case Rpc = 'rpc';
    case Auth = 'api/auth';
}
