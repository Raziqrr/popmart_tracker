<?php

namespace App\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by RequireCapability. Renders as { error: { code, message, capability } }
 * in the same shape as PopMartException, so the frontend's ApiError handles both.
 */
class CapabilityException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly ?Capability $capability = null,
    ) {
        parent::__construct($message);
    }

    public static function loginRequired(Capability $capability): self
    {
        return new self('unauthenticated', 401, "Log in to {$capability->label()}.", $capability);
    }

    public static function popMartRequired(Capability $capability): self
    {
        return new self('popmart_not_connected', 409, "Connect your Pop Mart account to {$capability->label()}.", $capability);
    }

    public static function notYourAccount(): self
    {
        return new self('forbidden', 403, 'That Pop Mart account is not connected to you.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'capability' => $this->capability?->value,
            ],
        ], $this->status);
    }
}
