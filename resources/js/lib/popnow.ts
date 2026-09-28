import type { SkuSummary } from '@/types/catalog';
import type { BoxHint, PopNowBox, PopNowSet } from '@/types/popnow';

/** Hints are "strong" when verified by the API or confirmed by at least this many users. */
export const STRONG_HINT_CONFIRMATIONS = 3;

export function hintStrength(hint: BoxHint): 'verified' | 'strong' | 'weak' {
    if (hint.source === 'verified_api') return 'verified';
    return hint.confirmations >= STRONG_HINT_CONFIRMATIONS ? 'strong' : 'weak';
}

/** The most trustworthy hint on a box: verified first, then most confirmations. */
export function bestHint(box: PopNowBox): BoxHint | null {
    return (
        [...box.hints].sort(
            (a, b) => Number(b.source === 'verified_api') - Number(a.source === 'verified_api') || b.confirmations - a.confirmations,
        )[0] ?? null
    );
}

export interface FigureOdds {
    sku: SkuSummary;
    inSet: number;
    revealed: number;
    remaining: number;
    /** Chance one unopened box holds this figure, ignoring hints. */
    chance: number;
    /** Unopened boxes hinted to hold it. */
    hintedBoxes: number;
}

/**
 * What's still in the set: the set's make-up minus revealed boxes, and the
 * plain chance per unopened box. Hints are counted separately, not blended in.
 */
export function setOdds(set: PopNowSet, boxes: PopNowBox[]): { unopened: number; figures: FigureOdds[] } {
    const unopenedBoxes = boxes.filter((b) => b.state !== 'sold');
    const unopened = unopenedBoxes.length;

    const figures = set.composition.map(({ sku, count }) => {
        const revealed = boxes.filter((b) => b.reveal?.sku.id === sku.id).length;
        const remaining = Math.max(0, count - revealed);
        const hintedBoxes = unopenedBoxes.filter((b) => bestHint(b)?.sku.id === sku.id).length;
        return { sku, inSet: count, revealed, remaining, chance: unopened ? remaining / unopened : 0, hintedBoxes };
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
