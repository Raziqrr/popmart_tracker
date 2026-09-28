<?php

namespace App\Services\Api\Server;

use App\Services\Api\Endpoint;

abstract class ServerEndpoint extends Endpoint
{
    protected string $client = 'server';

    // Set by each concrete endpoint, e.g. 'stock/check'.
    protected string $path;

    protected function segments(): array
    {
        return [$this->path];
    }
}
