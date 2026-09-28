<?php

namespace App\Http\Controllers;

use App\Models\PopmartAccount;
use App\Services\PopMartTaskClaimer;

class PopMartTaskController extends Controller
{
    public function __construct(private PopMartTaskClaimer $claimer) {}

    public function claim(PopmartAccount $popmartAccount)
    {
        return response()->json([
            'results' => $this->claimer->claimEligible($popmartAccount),
        ]);
    }
}
