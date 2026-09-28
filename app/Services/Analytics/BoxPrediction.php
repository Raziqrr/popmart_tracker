<?php

namespace App\Services\Analytics;

use App\Models\PopNowBox;
use App\Models\Sku;
use Illuminate\Support\Collection;

class BoxPrediction
{
    private function __construct(
        public readonly PopNowBox $box,
        public readonly bool $confirmed,
        public readonly ?Sku $confirmedSku,
        public readonly Collection $candidates, // Sku, sorted most-likely-first when unconfirmed
        public readonly Collection $excludedSkuIds,
        private readonly Collection $probabilities, // sku_id => float
        private readonly int $poolSize, // total non-secret SKUs for the product — the "zero info" baseline
        public readonly ?string $confirmedVia = null, // 'reveal' | 'elimination'
    ) {}

    public static function confirmed(PopNowBox $box, Sku $sku, Collection $excludedSkuIds, int $poolSize, string $via = 'reveal'): self
    {
        return new self($box, true, $sku, collect([$sku]), $excludedSkuIds, collect([$sku->id => 1.0]), $poolSize, $via);
    }

    // Equal-weight 1/N across the given candidates — used by the standalone
    // single-box predict(), which has no visibility into sibling boxes.
    public static function narrowed(PopNowBox $box, Collection $candidates, Collection $excludedSkuIds, int $poolSize): self
    {
        $p = $candidates->isEmpty() ? 0.0 : 1 / $candidates->count();
        $probabilities = $candidates->mapWithKeys(fn ($sku) => [$sku->id => $p]);

        return new self($box, false, null, $candidates, $excludedSkuIds, $probabilities, $poolSize);
    }

    // Non-uniform candidates derived from counting valid set-wide completions
    // (see ExclusionBoxPredictor::predictSet) — sorted most-likely first.
    public static function weighted(PopNowBox $box, Collection $probabilities, Collection $skusById, Collection $excludedSkuIds, int $poolSize): self
    {
        $sortedIds = $probabilities->sortDesc()->keys();
        $candidates = $sortedIds->map(fn ($id) => $skusById->get($id))->filter()->values();

        return new self($box, false, null, $candidates, $excludedSkuIds, $probabilities, $poolSize);
    }

    // How many possible SKUs remain — 1 means fully deduced even without a real reveal.
    public function candidateCount(): int
    {
        return $this->candidates->count();
    }

    public function probabilityFor(Sku $sku): float
    {
        if ($this->confirmed) {
            return $this->confirmedSku?->id === $sku->id ? 1.0 : 0.0;
        }

        return $this->probabilities->get($sku->id) ?? 0.0;
    }

    // Only meaningful when candidates are equally weighted (the uniform case).
    public function probabilityPerCandidate(): float
    {
        return $this->confirmed ? 1.0 : 1 / max($this->candidateCount(), 1);
    }

    // Shannon entropy of this box's distribution, in bits. 0 = fully certain;
    // higher = closer to a flat guess. Confirmed boxes are always 0.
    public function entropyBits(): float
    {
        if ($this->confirmed) {
            return 0.0;
        }

        return $this->probabilities->sum(fn ($p) => $p > 0 ? -$p * log($p, 2) : 0);
    }

    // How much this box's data has actually narrowed things down, relative to
    // total ignorance across the product's full non-secret SKU pool (not just
    // this box's already-narrowed candidate list — that would be circular).
    // 0% = no better than a blind guess across every SKU; 100% = fully resolved.
    public function predictability(): float
    {
        if ($this->confirmed) {
            return 1.0;
        }

        if ($this->poolSize <= 1) {
            return 1.0;
        }

        $maxEntropy = log($this->poolSize, 2);

        return $maxEntropy > 0 ? 1 - ($this->entropyBits() / $maxEntropy) : 1.0;
    }
}
