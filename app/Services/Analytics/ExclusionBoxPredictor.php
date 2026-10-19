<?php

namespace App\Services\Analytics;

use App\Contracts\BoxPredictorContract;
use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Models\Sku;
use App\Repositories\PopNowBoxRepository;
use Illuminate\Support\Collection;

class ExclusionBoxPredictor implements BoxPredictorContract
{
    /**
     * Limits before predictSet() gives up on exact counting and falls back to
     * per-box odds: distinct DP states, and DP cells actually stored. A 16-box
     * set with every box hinted differently is 65,536 states; a 48-box set
     * stays exact with up to about 12 distinct hint patterns. Same values as
     * MAX_DP_STATES / MAX_DP_CELLS in resources/js/lib/popnow.ts.
     */
    public const MAX_DP_STATES = 1 << 18;

    public const MAX_DP_CELLS = 1 << 20;

    public function __construct(private PopNowBoxRepository $boxes) {}

    public function predict(PopNowBox $box): BoxPrediction
    {
        $excludedSkuIds = $this->boxes->excludedSkuIds($box);
        $pool = $this->boxes->allPossibleSkus($box);

        $confirmedSku = $this->boxes->confirmedSku($box);
        if ($confirmedSku !== null) {
            return BoxPrediction::confirmed($box, $confirmedSku, $excludedSkuIds, $pool->count());
        }

        $candidates = $pool->reject(fn ($sku) => $excludedSkuIds->contains($sku->id))->values();

        return BoxPrediction::narrowed($box, $candidates, $excludedSkuIds, $pool->count());
    }

    public function predictSet(PopNowSet $set): Collection
    {
        return $this->predictBoxes($set, $this->boxes->boxesForSet($set));
    }

    /**
     * predictSet() over boxes the caller already loaded, e.g. with some hints
     * filtered out (auto-lock rules that only trust verified or well-confirmed
     * exclusions). Each box needs its hints and reveals (with sku) loaded.
     */
    public function predictBoxes(PopNowSet $set, Collection $boxes): Collection
    {
        // Eloquent Collection's except()/diff() filter by model key rather than
        // array key and silently re-index — collect() forces plain array semantics
        // so keyBy('id') survives the except() calls below.
        $pool = collect($this->boxes->nonSecretSkusForProduct($set->product_id)->keyBy('id')->all());
        $poolSize = $pool->count(); // "zero info" baseline for predictability, fixed per product

        $results = collect();

        // Boxes with a real reveal are fixed facts. They're removed from the
        // pool entirely — every other box's SKU is drawn from what's left.
        $unresolved = collect();
        $usedSkuIds = collect();

        foreach ($boxes as $box) {
            $revealedSku = $this->boxes->confirmedSku($box);

            if ($revealedSku !== null) {
                $usedSkuIds->push($revealedSku->id);
                $results[$box->id] = BoxPrediction::confirmed(
                    $box, $revealedSku, $box->hints->pluck('sku_id'), $poolSize, 'reveal'
                );

                continue;
            }

            $unresolved->push($box);
        }

        $availableSkus = $pool->except($usedSkuIds->all());

        // Each unresolved box's own allowed candidates (its hints already excluded).
        $allowed = $unresolved->map(
            fn ($box) => $availableSkus->except($box->hints->pluck('sku_id')->all())->keys()->values()
        );

        $completions = $this->countValidCompletions($allowed);

        if ($completions === null) {
            // Too many distinct hint patterns to count exactly: each box falls
            // back to its own hints, 1/N across what's left (same as predict()).
            foreach ($unresolved as $i => $box) {
                $candidates = $allowed[$i]->map(fn ($id) => $availableSkus->get($id))->values();
                $results[$box->id] = BoxPrediction::narrowed($box, $candidates, $box->hints->pluck('sku_id'), $poolSize);
            }

            return $results;
        }

        [$counts, $total] = $completions;

        foreach ($unresolved as $i => $box) {
            $excludedSkuIds = $box->hints->pluck('sku_id');

            if ($total == 0) {
                // No valid completion satisfies every box's exclusions together —
                // shouldn't happen with correct hint data, but don't fabricate one.
                $results[$box->id] = BoxPrediction::narrowed($box, collect(), $excludedSkuIds, $poolSize);

                continue;
            }

            $skuCounts = collect($counts[$i] ?? []);

            if ($skuCounts->count() === 1) {
                $sku = $availableSkus->get($skuCounts->keys()->first());
                $results[$box->id] = BoxPrediction::confirmed($box, $sku, $excludedSkuIds, $poolSize, 'elimination');

                continue;
            }

            $probabilities = $skuCounts->map(fn ($count) => $count / $total);
            $results[$box->id] = BoxPrediction::weighted($box, $probabilities, $availableSkus, $excludedSkuIds, $poolSize);
        }

        return $results;
    }

    /**
     * Counts every way to assign a distinct SKU to each unresolved box,
     * respecting that box's own allowed list. Returns [counts, total] where
     * counts[boxIndex][sku_id] = how many valid completions placed that SKU
     * in that box, or null when the set is too irregular to count within
     * MAX_DP_STATES / MAX_DP_CELLS. Mirrors countCompletions() in
     * resources/js/lib/popnow.ts — see there for the full explanation.
     *
     * In short: boxes with the same allowed list are grouped, and a state is
     * how many boxes of each group are filled. SKUs are placed one at a time;
     * $after[$j][$state] = ways SKUs j..m-1 can fill what's still open, and
     * the forward pass carries $before[$state]. Each step only visits states
     * whose number of filled boxes is reachable at that step.
     *
     * @param  Collection<int, Collection<int, string>>  $allowed
     * @return array{0: array<int, array<string, int|float>>, 1: int|float}|null
     */
    private function countValidCompletions(Collection $allowed): ?array
    {
        $allowedArrays = $allowed->map(fn ($ids) => $ids->all())->values()->all();
        $n = count($allowedArrays);
        $counts = array_fill(0, $n, []);
        $skuIds = array_values(array_unique(array_merge([], ...$allowedArrays)));
        $m = count($skuIds);

        // More boxes than SKUs left means no valid way to fill them all.
        if ($n === 0 || $n > $m) {
            return [$counts, 0];
        }

        // Group boxes by identical allowed list.
        $groupOf = [];
        $groupBoxes = [];
        $groupSkus = [];
        foreach ($allowedArrays as $box => $ids) {
            $sorted = $ids;
            sort($sorted);
            $key = implode('|', $sorted);
            if (! isset($groupOf[$key])) {
                $groupOf[$key] = count($groupBoxes);
                $groupBoxes[] = [];
                $groupSkus[] = array_flip($ids);
            }
            $groupBoxes[$groupOf[$key]][] = $box;
        }

        $groupCount = count($groupBoxes);
        $sizes = array_map('count', $groupBoxes);

        // Mixed-radix state: digit g = boxes of group g filled, 0..size.
        $strides = [];
        $states = 1;
        foreach ($sizes as $size) {
            $strides[] = $states;
            $states *= $size + 1;
            if ($states > self::MAX_DP_STATES) {
                return null;
            }
        }

        // Open boxes per state, bucketed by how many boxes are filled.
        $openTotal = [];
        $byFilled = array_fill(0, $n + 1, []);
        $digits = array_fill(0, $groupCount, 0);
        for ($s = 0; $s < $states; $s++) {
            $open = $n - array_sum($digits);
            $openTotal[$s] = $open;
            $byFilled[$n - $open][] = $s;
            for ($g = 0; $g < $groupCount && ++$digits[$g] > $sizes[$g]; $g++) {
                $digits[$g] = 0;
            }
        }

        // After j SKUs at most j boxes are filled, and the open boxes must fit in the m - j SKUs left.
        $window = fn (int $j) => [max(0, $n - ($m - $j)), min($j, $n)];

        $storedCells = 0;
        for ($j = 0; $j <= $m; $j++) {
            [$lo, $hi] = $window($j);
            for ($f = $lo; $f <= $hi; $f++) {
                $storedCells += count($byFilled[$f]);
            }
        }
        if ($storedCells > self::MAX_DP_CELLS) {
            return null;
        }

        // $groupsFor[$j] = the groups SKU j may go in.
        $groupsFor = [];
        foreach ($skuIds as $j => $skuId) {
            $groupsFor[$j] = array_keys(array_filter($groupSkus, fn ($skus) => isset($skus[$skuId])));
        }

        // Sparse layers: only window states, only non-zero ways. A missing state has 0 ways.
        $after = array_fill(0, $m + 1, []);
        $after[$m][$states - 1] = 1;

        for ($j = $m - 1; $j >= 0; $j--) {
            $next = $after[$j + 1];
            $current = [];
            [$lo, $hi] = $window($j);
            for ($f = $lo; $f <= $hi; $f++) {
                foreach ($byFilled[$f] as $s) {
                    $ways = $next[$s] ?? 0; // SKU j left unused
                    foreach ($groupsFor[$j] as $g) {
                        $left = $sizes[$g] - intdiv($s, $strides[$g]) % ($sizes[$g] + 1);
                        if ($left > 0) {
                            $ways += $left * ($next[$s + $strides[$g]] ?? 0);
                        }
                    }
                    if ($ways != 0) {
                        $current[$s] = $ways;
                    }
                }
            }
            $after[$j] = $current;
        }

        $total = $after[0][0] ?? 0;
        if ($total == 0) {
            return [$counts, 0];
        }

        // Updated in place: walking buckets from most filled down means a
        // state is read before this SKU's placements are added into it.
        $before = [0 => 1];

        for ($j = 0; $j < $m; $j++) {
            $next = $after[$j + 1];
            $perGroup = [];
            [$lo, $hi] = $window($j);
            for ($f = $hi; $f >= $lo; $f--) {
                foreach ($byFilled[$f] as $s) {
                    $ways = $before[$s] ?? 0;
                    if ($ways == 0) {
                        continue;
                    }
                    foreach ($groupsFor[$j] as $g) {
                        $left = $sizes[$g] - intdiv($s, $strides[$g]) % ($sizes[$g] + 1);
                        if ($left === 0) {
                            continue;
                        }
                        $placed = $s + $strides[$g];
                        $perGroup[$g] = ($perGroup[$g] ?? 0) + $ways * $left * ($next[$placed] ?? 0);
                        $before[$placed] = ($before[$placed] ?? 0) + $ways * $left;
                    }
                }
            }

            foreach ($perGroup as $g => $completions) {
                if ($completions == 0) {
                    continue;
                }
                // Every box in a group is equally likely to be the one that got it.
                $share = $completions / $sizes[$g];
                foreach ($groupBoxes[$g] as $box) {
                    $counts[$box][$skuIds[$j]] = $share;
                }
            }
        }

        return [$counts, $total];
    }
}
