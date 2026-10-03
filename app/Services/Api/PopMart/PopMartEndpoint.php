<?php

namespace App\Services\Api\PopMart;

use App\Services\Api\Endpoint;
use App\Services\Api\RequiresAuthentication;
use App\Services\Locator\Country;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class PopMartEndpoint extends Endpoint
{
    private const TIMEOUT_SECONDS = 15;

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
    /**
     * @throws PopMartException when Pop Mart can't be reached or answers with an error
     */
    public function send(array $payload = []): Response
    {
        if ($this instanceof RequiresAuthentication && $this->sessionCookie === null) {
            throw new RuntimeException(static::class.' requires a session — call ->withSession($cookie) before send().');
        }

        $request = Http::withHeaders($this->headers())->timeout(self::TIMEOUT_SECONDS);

        try {
            $response = match ($this->method) {
                'GET' => $request->get($this->url($payload)),
                // Cast to object so an empty payload encodes as {} — a bare empty PHP array
                // would otherwise serialize to JSON [] and fail Pop Mart's schema validation.
                'POST' => $request->post($this->url(), ['json' => (object) $payload]),
                default => throw new RuntimeException("Unsupported HTTP method [{$this->method}] on ".static::class),
            };
        } catch (ConnectionException $e) {
            throw PopMartException::unreachable($e);
        }

        return $this->ensureSuccessful($response);
    }

    /**
     * The tRPC "json" payload of a successful call.
     *
     * @throws PopMartException
     */
    public function data(array $payload = []): ?array
    {
        $body = $this->send($payload)->json();

        if (! is_array($body) || ! array_key_exists('json', $body)) {
            throw PopMartException::unexpectedResponse(class_basename(static::class));
        }

        return $body['json'];
    }

    /**
     * Maps a failed response to a PopMartException. tRPC errors look like
     * { error: { json: { message, data: { code: 'UNAUTHORIZED', httpStatus } } } }.
     */
    protected function ensureSuccessful(Response $response): Response
    {
        $trpcCode = $response->json('error.json.data.code');

        if ($response->successful() && $trpcCode === null) {
            return $response;
        }

        $status = $response->status();

        if (in_array($status, [401, 403], true) || in_array($trpcCode, ['UNAUTHORIZED', 'FORBIDDEN'], true)) {
            throw PopMartException::sessionExpired();
        }

        if ($status === 429 || $trpcCode === 'TOO_MANY_REQUESTS') {
            $retryAfter = (int) $response->header('Retry-After');

            throw PopMartException::rateLimited($retryAfter > 0 ? $retryAfter : null);
        }

        $reason = $response->json('error.json.message') ?? $response->json('error.message') ?? $response->json('message');

        throw PopMartException::rejected($status, is_string($reason) ? $reason : null);
    }
}
