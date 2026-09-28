<?php

namespace App\Services\Api;

// Marker interface: an endpoint that reads or mutates account-specific state
// (quota, cart, orders, tip cards) and must not be sent without a session cookie.
interface RequiresAuthentication
{
}
