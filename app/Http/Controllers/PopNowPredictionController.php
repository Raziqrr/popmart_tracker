<?php

namespace App\Http\Controllers;

use App\Contracts\BoxPredictorContract;
use App\Models\PopNowSet;
use App\Models\Product;
use App\Models\Sku;
use App\Repositories\PopNowBoxRepository;
use Illuminate\Http\Request;

class PopNowPredictionController extends Controller
{
    public function __construct(
        private BoxPredictorContract $predictor,
        private PopNowBoxRepository $boxes,
    ) {}

    public function forSet(PopNowSet $popNowSet)
    {
        $results = $this->predictor->predictSet($popNowSet);

        $predictions = $results->map(function ($prediction) {
            return [
                'box_id' => $prediction->box->id,
                'box_no' => $prediction->box->box_no,
                'confirmed' => $prediction->confirmed,
                'confirmed_via' => $prediction->confirmedVia,
                'confirmed_sku_id' => $prediction->confirmedSku?->id,
                'candidate_count' => $prediction->candidateCount(),
                'predictability' => round($prediction->predictability(), 4),
                'candidates' => $prediction->candidates->map(fn ($sku) => [
                    'sku_id' => $sku->id,
                    'name' => $sku->name,
                    'probability' => $prediction->probabilityFor($sku),
                ]),
                'excluded_sku_ids' => $prediction->excludedSkuIds,
            ];
        })->values();

        return response()->json([
            'set_predictability' => round($results->avg(fn ($p) => $p->predictability()), 4),
            'predictions' => $predictions,
        ]);
    }

    // Ranks every box across every set recorded for a product. With no
    // `sku_id`, ranks by predictability (which boxes are closest to solved).
    // With `sku_id`, ranks by that specific SKU's probability per box
    // (which box is the best bet if you specifically want that character).
    public function forProduct(Product $product, Request $request)
    {
        $sku = $request->filled('sku_id') ? Sku::find($request->query('sku_id')) : null;

        $sets = $this->boxes->setsForProduct($product->id);

        $rows = collect();

        foreach ($sets as $set) {
            foreach ($this->predictor->predictSet($set) as $prediction) {
                $topCandidate = $prediction->confirmed ? $prediction->confirmedSku : $prediction->candidates->first();

                $rows->push([
                    'set_no' => $set->set_no,
                    'box_id' => $prediction->box->id,
                    'box_no' => $prediction->box->box_no,
                    'confirmed' => $prediction->confirmed,
                    'confirmed_via' => $prediction->confirmedVia,
                    'predictability' => round($prediction->predictability(), 4),
                    'top_candidate' => $topCandidate?->name,
                    'top_probability' => $topCandidate ? round($prediction->probabilityFor($topCandidate), 4) : 0.0,
                    'requested_sku_probability' => $sku ? round($prediction->probabilityFor($sku), 4) : null,
                ]);
            }
        }

        $ranking = $sku
            ? $rows->sortByDesc('requested_sku_probability')->values()
            : $rows->sortByDesc('predictability')->values();

        return response()->json([
            'product_id' => $product->id,
            'mode' => $sku ? 'sku_specific' : 'general',
            'requested_sku' => $sku?->name,
            'sets_scanned' => $sets->count(),
            'boxes_ranked' => $ranking->count(),
            'ranking' => $ranking,
        ]);
    }
}
