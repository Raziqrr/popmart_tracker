<?php

namespace App\Services\Api\PopMart;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * Thrown by every Pop Mart endpoint when a call fails (see PopMartEndpoint::send()).
 * Renders itself as { error: { code, message, retry_after } } for our API,
 * so controllers don't need their own try/catch.
 */
class PopMartException extends RuntimeException
{
    public function __construct(
        public readonly PopMartError $error,
        string $message,
        public readonly ?int $upstreamStatus = null,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function sessionExpired(): self
    {
        return new self(PopMartError::SessionExpired, 'Your Pop Mart session has expired. Reconnect your account.', 401);
    }

    public static function rateLimited(?int $retryAfter): self
    {
        return new self(PopMartError::RateLimited, 'Pop Mart is rate limiting requests. Try again shortly.', 429, $retryAfter);
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(PopMartError::Unreachable, 'Could not reach Pop Mart. Try again shortly.', previous: $previous);
    }

    public static function rejected(int $status, ?string $reason): self
    {
        return new self(PopMartError::Rejected, $reason ? "Pop Mart refused the request: {$reason}" : "Pop Mart refused the request (HTTP {$status}).", $status);
    }

    public static function unexpectedResponse(string $endpoint): self
    {
        return new self(PopMartError::UnexpectedResponse, "Pop Mart sent a response we don't understand ({$endpoint}).");
    }

    public function is(PopMartError $error): bool
    {
        return $this->error === $error;
    }

    /** Failures that would hit every following call too, so a batch should stop. */
    public function abortsBatch(): bool
    {
        return in_array($this->error, [PopMartError::SessionExpired, PopMartError::RateLimited, PopMartError::Unreachable], true);
    }

    public function render(): JsonResponse
    {
        $response = response()->json([
            'error' => [
                'code' => $this->error->value,
                'message' => $this->getMessage(),
                'retry_after' => $this->retryAfter,
            ],
        ], $this->error->httpStatus());

        if ($this->retryAfter !== null) {
            $response->header('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }
}
