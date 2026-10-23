<?php

use App\Services\Api\Endpoint;
use App\Services\Api\RequiresAuthentication;
use Illuminate\Support\Facades\Http;

it('throws an exception if an authenticated endpoint is sent without a session', function () {
    $endpoint = new class extends Endpoint implements RequiresAuthentication {
        protected string $client = 'dummy';

        protected function segments(): array
        {
            return ['dummy'];
        }
    };

    expect(fn () => $endpoint->send())
        ->toThrow(RuntimeException::class, get_class($endpoint).' requires a session — call ->withSession($cookie) before send().');
});

it('throws an exception for unsupported HTTP methods', function () {
    Http::fake();
    $endpoint = new class extends Endpoint {
        protected string $client = 'dummy';
        protected string $method = 'PUT';

        protected function segments(): array
        {
            return ['dummy'];
        }
    };

    expect(fn () => $endpoint->send())
        ->toThrow(RuntimeException::class, "Unsupported HTTP method [PUT] on ".get_class($endpoint));
});
