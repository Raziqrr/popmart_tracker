<?php

namespace App\Services\Api;

use App\Helpers\EndpointBuilder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class Endpoint
{
    protected string $client;

    protected string $method = 'POST';

    protected ?string $sessionCookie = null;

    /**
     * @return array<int, string> path parts after the base_url, in order
     */
    abstract protected function segments(): array;

    public function url(array $query = []): string
    {
        return (new EndpointBuilder($this->client))->build($this->segments(), $query);
    }

    public function method(): string
    {
        return $this->method;
    }

    // Attach a captured Pop Mart session cookie (see popmart_accounts.session_cookie).
    // Only required on endpoints implementing RequiresAuthentication.
    public function withSession(string $cookie): static
    {
        $this->sessionCookie = $cookie;

        return $this;
    }

    protected function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->sessionCookie !== null) {
            $headers['Cookie'] = $this->sessionCookie;
        }

        return $headers;
    }

    public function send(array $payload = []): Response
    {
        if ($this instanceof RequiresAuthentication && $this->sessionCookie === null) {
            throw new RuntimeException(static::class.' requires a session — call ->withSession($cookie) before send().');
        }

        $request = Http::withHeaders($this->headers());

        return match ($this->method) {
            'GET' => $request->get($this->url($payload)),
            'POST' => $request->post($this->url(), $payload),
            default => throw new RuntimeException("Unsupported HTTP method [{$this->method}] on ".static::class),
        };
    }
}
