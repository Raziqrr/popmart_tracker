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
        $boxes = $this->boxes->boxesForSet($set);
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

        [$counts, $total] = $this->countValidCompletions($allowed);

        foreach ($unresolved as $i => $box) {
            $excludedSkuIds = $box->hints->pluck('sku_id');

            if ($total === 0) {
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

    // Exact enumeration: every way to assign a distinct SKU to each unresolved
    // box, respecting that box's own allowed list, counted by backtracking.
    // Returns [counts, total] where counts[boxIndex][sku_id] = how many valid
    // completions placed that SKU in that box.
    private function countValidCompletions(Collection $allowed): array
    {
        $n = $allowed->count();
        $counts = array_fill(0, $n, []);
        $total = 0;
        $used = [];
        $assignment = [];

        $allowedArrays = $allowed->map(fn ($ids) => $ids->all())->all();

        $dfs = function (int $boxIndex) use (&$dfs, &$counts, &$total, &$used, &$assignment, $allowedArrays, $n) {
            if ($boxIndex === $n) {
                $total++;
                foreach ($assignment as $i => $skuId) {
                    $counts[$i][$skuId] = ($counts[$i][$skuId] ?? 0) + 1;
                }

                return;
            }

            foreach ($allowedArrays[$boxIndex] as $skuId) {
                if (isset($used[$skuId])) {
                    continue;
                }

                $used[$skuId] = true;
                $assignment[$boxIndex] = $skuId;

                $dfs($boxIndex + 1);

                unset($assignment[$boxIndex], $used[$skuId]);
            }
        };

        if ($n > 0) {
            $dfs(0);
        }

        return [$counts, $total];
    }
}
