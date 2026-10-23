<?php

namespace App\Http\Controllers;

use App\Models\PopmartAccount;
use App\Services\Api\PopMart\PopMartException;
use App\Services\PopMart\LuckyPoints;
use App\Services\PopMartTaskClaimer;

class PopMartTaskController extends Controller
{
    public function __construct(
        private PopMartTaskClaimer $claimer,
        private LuckyPoints $luckyPoints,
    ) {}

    /** Claims finished daily tasks, then re-reads the lucky points they fed (null if that isn't readable yet). */
    public function claim(PopmartAccount $popmartAccount)
    {
        $results = $this->claimer->claimEligible($popmartAccount);

        try {
            $points = $this->luckyPoints->sync($popmartAccount);
        } catch (PopMartException) {
            // The claims went through; a failed balance refresh shouldn't hide that.
            $points = null;
        }

        return response()->json([
            'results' => $results,
            'points' => $points ? LuckyPointsController::present($points, $popmartAccount) : null,
        ]);
    }
}
