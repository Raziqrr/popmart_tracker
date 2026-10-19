<?php

namespace App\Services\Api\PopMart;

use App\Services\Api\Endpoint;
use App\Services\Api\RequiresAuthentication;
use App\Services\Locator\Country;
use App\Services\PopMart\PayloadInspector;
use App\Services\PopMart\PopMartThrottle;
use App\Services\PopMart\ThrottleLane;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
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

    protected bool $house = false;

    protected ?ThrottleLane $lane = null;

    public function __construct(protected Country $area = Country::Malaysia) {}

    protected function segments(): array
    {
        return [
            $this->segment->value,
            $this->domain->value,
            $this->path,
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
     * Every call: takes a slot from PopMartThrottle, is logged to popmart_calls,
     * and has new response fields recorded by PayloadInspector.
     *
     * @throws PopMartException when Pop Mart can't be reached or answers with an error
     */
    public function send(array $payload = []): Response
    {
        if ($this instanceof RequiresAuthentication && $this->sessionCookie === null) {
            throw new RuntimeException(static::class.' requires a session — call ->withSession($cookie) before send().');
        }

        if ($this->house && $this instanceof HoldsBoxes) {
            // The house account only watches; holding a box would also stop its AssignSet polling.
            throw new RuntimeException(static::class.' would hold boxes: never call it with the house account.');
        }

        $throttle = app(PopMartThrottle::class);
        $throttle->acquire($this->lane());

        $request = Http::withHeaders($this->headers())->timeout(self::TIMEOUT_SECONDS);
        $started = hrtime(true);
        $response = null;

        try {
            $response = match ($this->method) {
                'GET' => $request->get($this->url($payload)),
                // Cast to object so an empty payload encodes as {} — a bare empty PHP array
                // would otherwise serialize to JSON [] and fail Pop Mart's schema validation.
                'POST' => $request->post($this->url(), ['json' => (object) $payload]),
                default => throw new RuntimeException("Unsupported HTTP method [{$this->method}] on ".static::class),
            };

            $this->ensureSuccessful($response);
        } catch (ConnectionException $e) {
            $error = PopMartException::unreachable($e);
            $this->logCall($started, null, $error);

            throw $error;
        } catch (PopMartException $e) {
            if ($e->is(PopMartError::RateLimited)) {
                $throttle->pause($e->retryAfter);
            }
            $this->logCall($started, $response?->status(), $e);

            throw $e;
        }

        $this->logCall($started, $response->status());
        app(PayloadInspector::class)->record(class_basename(static::class), $response->json());

        return $response;
    }

    /** Call as the server's house account (POP NOW polling). It may never send HoldsBoxes endpoints. */
    public function asHouse(): static
    {
        $this->house = true;
        $this->lane = ThrottleLane::Background;

        return $this;
    }

    /** Draw from the background share of the budget even though a session is attached. */
    public function inBackground(): static
    {
        $this->lane = ThrottleLane::Background;

        return $this;
    }

    /** Unless set: calls with a user's session are user actions, everything else is background. */
    protected function lane(): ThrottleLane
    {
        return $this->lane ?? ($this->sessionCookie !== null ? ThrottleLane::User : ThrottleLane::Background);
    }

    private function logCall(int $started, ?int $status, ?PopMartException $error = null): void
    {
        if (! config('apis.popmart.log_calls', true)) {
            return;
        }

        DB::table('popmart_calls')->insert([
            'endpoint' => class_basename(static::class),
            'lane' => $this->lane()->value,
            'house' => $this->house,
            'status' => $status,
            'error' => $error?->error->value,
            'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'called_at' => now(),
        ]);
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

        // A 403 with a JSON body is Pop Mart's own "not allowed". A 403 that isn't JSON is
        // a CDN or bot-protection block: it says nothing about the session, and calling it
        // "expired" would mark a good account dead (PopmartAccount::usingSession).
        $isJson = is_array($response->json());

        if ($status === 401 || ($status === 403 && $isJson) || in_array($trpcCode, ['UNAUTHORIZED', 'FORBIDDEN'], true)) {
            throw PopMartException::sessionExpired();
        }

        if ($status === 403) {
            throw PopMartException::blocked();
        }

        if ($status === 429 || $trpcCode === 'TOO_MANY_REQUESTS') {
            $retryAfter = (int) $response->header('Retry-After');

            throw PopMartException::rateLimited($retryAfter > 0 ? $retryAfter : null);
        }

        $reason = $response->json('error.json.message') ?? $response->json('error.message') ?? $response->json('message');

        throw PopMartException::rejected($status, is_string($reason) ? $reason : null);
    }
}
