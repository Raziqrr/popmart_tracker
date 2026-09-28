<?php

namespace App\Services\Api\PopMart;

use App\Services\Api\Endpoint;
use App\Services\Api\RequiresAuthentication;
use App\Services\Locator\Country;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class PopMartEndpoint extends Endpoint
{
    protected string $client = 'popmart';

    protected PopMartSegment $segment = PopMartSegment::Rpc;

    // Set by each concrete endpoint.
    protected PopMartDomain $domain;

    // Set by each concrete endpoint, e.g. 'sku/public_CheckSkuStock'.
    protected string $path;

    public function __construct(protected Country $area = Country::Malaysia)
    {
    }

    protected function segments(): array
    {
        return [
            $this->segment->value, 
            $this->domain->value, 
            $this->path
        ];
    }

    protected function headers(): array
    {
        return array_merge(parent::headers(), [
            'Origin' => 'https://m.popmart.com',
            'Referer' => 'https://m.popmart.com/',
            'X-Area' => $this->area->value,
            'X-Device-Type' => 'web-mobile',
        ]);
    }

    // Pop Mart's RPC layer wraps every POST body and JSON response in a "json" key (tRPC convention) —
    // confirmed against a real captured public_CheckSkuStock call/response.
    public function send(array $payload = []): Response
    {
        if ($this instanceof RequiresAuthentication && $this->sessionCookie === null) {
            throw new RuntimeException(static::class.' requires a session — call ->withSession($cookie) before send().');
        }

        $request = Http::withHeaders($this->headers());

        return match ($this->method) {
            'GET' => $request->get($this->url($payload)),
            // Cast to object so an empty payload encodes as {} — a bare empty PHP array
            // would otherwise serialize to JSON [] and fail Pop Mart's schema validation.
            'POST' => $request->post($this->url(), ['json' => (object) $payload]),
            default => parent::send($payload),
        };
    }

    public function data(array $payload = []): ?array
    {
        return $this->send($payload)->json('json');
    }
}
