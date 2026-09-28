<?php

namespace App\Contracts;

use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Services\Analytics\BoxPrediction;
use Illuminate\Support\Collection;

interface BoxPredictorContract
{
    public function predict(PopNowBox $box): BoxPrediction;

    // Predicts every box in a set together, so a box resolved to one
    // candidate (by hints or a real reveal) can strike that SKU from its
    // sibling boxes' pools — a set is a closed permutation, so this
    // cross-box elimination narrows further than any box could alone.
    // Returns a Collection<int, BoxPrediction> keyed by box id.
    public function predictSet(PopNowSet $set): Collection;
}
