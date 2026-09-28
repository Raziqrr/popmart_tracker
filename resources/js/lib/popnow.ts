import type { SkuSummary } from '@/types/catalog';
import type { BoxHint, PopNowBox, PopNowSet } from '@/types/popnow';

/** Hints are "strong" when verified by the API or confirmed by at least this many users. */
export const STRONG_HINT_CONFIRMATIONS = 3;

export function hintStrength(hint: BoxHint): 'verified' | 'strong' | 'weak' {
    if (hint.source === 'verified_api') return 'verified';
    return hint.confirmations >= STRONG_HINT_CONFIRMATIONS ? 'strong' : 'weak';
}

/** SKU ids a box's own hints have proven it is NOT. */
export function excludedSkuIds(box: PopNowBox): Set<string> {
    return new Set(box.hints.map((h) => h.sku.id));
}

export function isExcludedFromBox(box: PopNowBox, skuId: string): boolean {
    return box.hints.some((h) => h.sku.id === skuId);
}

export interface FigureOdds {
    sku: SkuSummary;
    revealed: boolean;
    /**
     * Whether we have any exclusion signal to compute odds for this figure.
     * False for secrets: nothing in Pop Mart's API ever indicates whether an
     * unrevealed box is a secret slot, so a chance number here would be
     * fabricated rather than derived — see app/Repositories/PopNowBoxRepository.php.
     */
    trackable: boolean;
    /**
     * Plain chance an unopened box holds this figure: 1 divided by however
     * many unopened boxes haven't had it excluded by their own hints yet.
     * This is a same-box-only estimate (matches the backend's single-box
     * predict()); it does not do the full cross-box elimination the backend's
     * predictSet() does, since that needs server-side computation to stay
     * accurate as more hints arrive — see App\Services\Analytics\ExclusionBoxPredictor.
     */
    chance: number;
    /** Unopened boxes whose own hints have ruled this figure out. */
    excludedFromBoxes: number;
    /** Unopened boxes where this figure is still a possible candidate. */
    possibleBoxes: number;
}

/**
 * What's still in the set, using each box's own excluded hints (never a
 * positive "this box is X" claim — Pop Mart's tip-card mechanic only ever
 * proves what a box ISN'T). Every set scraped so far is exactly one of each
 * non-secret figure, so "revealed" makes a figure fully gone; otherwise its
 * chance comes from how many unopened boxes still can't rule it out.
 */
export function setOdds(set: PopNowSet, boxes: PopNowBox[]): { unopened: number; figures: FigureOdds[] } {
    const unopenedBoxes = boxes.filter((b) => b.state !== 'sold');
    const unopened = unopenedBoxes.length;
    const skus = set.product.skus ?? [];

    const figures = skus.map((sku): FigureOdds => {
        const revealed = boxes.some((b) => b.reveal?.sku.id === sku.id);

        if (sku.is_secret) {
            return { sku, revealed, trackable: false, chance: 0, excludedFromBoxes: 0, possibleBoxes: 0 };
        }

        const possibleBoxes = revealed ? [] : unopenedBoxes.filter((b) => !isExcludedFromBox(b, sku.id));

        return {
            sku,
            revealed,
            trackable: true,
            chance: revealed || possibleBoxes.length === 0 ? 0 : 1 / possibleBoxes.length,
            excludedFromBoxes: unopened - (revealed ? 0 : possibleBoxes.length),
            possibleBoxes: possibleBoxes.length,
        };
    });

    // Secrets first (what people hunt), then most likely.
    figures.sort((a, b) => Number(b.sku.is_secret) - Number(a.sku.is_secret) || b.chance - a.chance);
    return { unopened, figures };
}

export function boxCounts(boxes: PopNowBox[]): Record<PopNowBox['state'], number> {
    const counts = { available: 0, locked_other: 0, locked_mine: 0, sold: 0 };
    for (const box of boxes) counts[box.state]++;
    return counts;
}

export const formatPercent = (value: number) => `${Math.round(value * 100)}%`;
