<?php

namespace App\Http\Controllers;

use App\Models\PopNowSet;
use App\Services\PopMart\Sync\PopNowSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/pop-now-sets/{set}/refresh: re-check a set's boxes with the
 * viewer's own Pop Mart session (read-only CheckSetBoxLock, never AssignSet).
 * capability:refresh_popnow guarantees a live session.
 */
class PopNowRefreshController extends Controller
{
    public function __invoke(PopNowSet $popNowSet, Request $request, PopNowSync $sync): JsonResponse
    {
        $result = $sync->refreshForViewer($popNowSet, $request->user()->livePopmartAccount());

        return response()->json([...$result, 'checked_at' => $popNowSet->fresh()->last_seen_at]);
    }
}
