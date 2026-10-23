<?php

namespace App\Services\PopMart;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The same Idempotency-Key is still being worked on: answer 409 and let the client retry. */
class ActionInProgress extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'action_in_progress', 'message' => 'That request is still running. Try again in a moment.'],
        ], 409);
    }
}
