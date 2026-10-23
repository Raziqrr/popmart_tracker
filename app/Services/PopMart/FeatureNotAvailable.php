<?php

namespace App\Services\PopMart;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Built, but waiting on a Pop Mart call nobody has captured yet. Answers 501 not_available. */
class FeatureNotAvailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'not_available', 'message' => $this->getMessage()]], 501);
    }
}
