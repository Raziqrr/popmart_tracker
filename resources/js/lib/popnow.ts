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

export interface BoxCandidate {
    sku: SkuSummary;
    probability: number;
}

/**
 * Exact per-box, per-figure probabilities — mirrors the backend's
 * App\Services\Analytics\ExclusionBoxPredictor::predictSet(): count every
 * valid way to assign a distinct non-secret figure to each unrevealed box,
 * respecting each box's own excluded hints, then a figure's probability in
 * a box is how often it landed there ÷ total valid completions. A revealed
 * box is a fixed fact and removes its figure from the pool for every other
 * box in the set — this is what makes it "cross-box", not just per-box.
 */
export function boxChances(set: PopNowSet, boxes: PopNowBox[]): Map<number, BoxCandidate[]> {
    const pool = (set.product.skus ?? []).filter((s) => !s.is_secret);
    const result = new Map<number, BoxCandidate[]>();

    const usedIds = new Set<string>();
    const unresolved: PopNowBox[] = [];

    for (const box of boxes) {
        if (box.reveal) {
            usedIds.add(box.reveal.sku.id);
            result.set(box.id, [{ sku: box.reveal.sku, probability: 1 }]);
        } else {
            unresolved.push(box);
        }
    }

    const available = pool.filter((s) => !usedIds.has(s.id));
    const allowed = unresolved.map((box) => available.filter((s) => !isExcludedFromBox(box, s.id)));

    const n = unresolved.length;
    const counts: Record<string, number>[] = Array.from({ length: n }, () => ({}));
    let total = 0;
    const inUse = new Set<string>();
    const assignment: string[] = [];

    const dfs = (i: number) => {
        if (i === n) {
            total++;
            assignment.forEach((skuId, idx) => {
                counts[idx][skuId] = (counts[idx][skuId] ?? 0) + 1;
            });
            return;
        }
        for (const sku of allowed[i]) {
            if (inUse.has(sku.id)) continue;
            inUse.add(sku.id);
            assignment[i] = sku.id;
            dfs(i + 1);
            inUse.delete(sku.id);
        }
    };
    if (n > 0) dfs(0);

    unresolved.forEach((box, i) => {
        if (total === 0) {
            result.set(box.id, []);
            return;
        }
        const entries = Object.entries(counts[i]).map(([skuId, count]) => ({
            sku: available.find((s) => s.id === skuId)!,
            probability: count / total,
        }));
        entries.sort((a, b) => b.probability - a.probability);
        result.set(box.id, entries);
    });

    return result;
}

/** A box you can still get: free, or already held by you. Sold boxes are opened and locked ones are someone else's. */
export function isObtainable(box: PopNowBox): boolean {
    return box.state === 'available' || box.state === 'locked_mine';
}

/**
 * boxChances limited to obtainable boxes, for anything that recommends a box
 * (best bets, highlights, "better elsewhere"). A sold box's revealed figure
 * is 100% certain but not a bet, so it must never be suggested.
 */
export function obtainableChances(chances: Map<number, BoxCandidate[]>, boxes: PopNowBox[]): Map<number, BoxCandidate[]> {
    const obtainable = new Set(boxes.filter(isObtainable).map((b) => b.id));
    return new Map([...chances].filter(([boxId]) => obtainable.has(boxId)));
}

/**
 * Which box(es) have the best shot at a given figure, and what that chance
 * is — used to highlight the grid when someone clicks a figure. Ties (equal
 * top probability) return every box tied for the lead, not just one.
 */
export function bestBoxesForSku(chances: Map<number, BoxCandidate[]>, skuId: string): { boxIds: number[]; probability: number } {
    let best = 0;
    const byBox = new Map<number, number>();

    for (const [boxId, candidates] of chances) {
        const found = candidates.find((c) => c.sku.id === skuId);
        if (found) {
            byBox.set(boxId, found.probability);
            best = Math.max(best, found.probability);
        }
    }

    if (best === 0) {
        return { boxIds: [], probability: 0 };
    }

    const boxIds = [...byBox.entries()].filter(([, p]) => p === best).map(([boxId]) => boxId);
    return { boxIds, probability: best };
}

/**
 * For each figure, the best obtainable box in any of the other sets, when it
 * beats the current set's best chance for that figure (pass the current set's
 * obtainableChances). Only obtainable boxes count, so every suggestion is one
 * you can lock right now (or already hold). Used by "Best bet per
 * figure" to point at a better set.
 */
export function betterInOtherSets(
    currentChances: Map<number, BoxCandidate[]>,
    others: { set: PopNowSet; boxes: PopNowBox[]; chances: Map<number, BoxCandidate[]> }[],
    skuIds: string[],
): Map<string, { set: PopNowSet; box: PopNowBox; probability: number }> {
    const result = new Map<string, { set: PopNowSet; box: PopNowBox; probability: number }>();

    for (const skuId of skuIds) {
        const here = bestBoxesForSku(currentChances, skuId).probability;
        let best: { set: PopNowSet; box: PopNowBox; probability: number } | null = null;

        for (const { set, boxes, chances } of others) {
            for (const box of boxes) {
                if (!isObtainable(box)) continue;
                const p = chances.get(box.id)?.find((c) => c.sku.id === skuId)?.probability ?? 0;
                if (p > here && p > (best?.probability ?? 0)) best = { set, box, probability: p };
            }
        }

        if (best) result.set(skuId, best);
    }

    return result;
}

/**
 * For one box's own candidate list, which figures some OTHER box is a
 * better bet for right now — e.g. this box shows Night Owl at 30%, but box
 * 03 shows it at 60%. Only includes a figure when another box strictly
 * beats this one; ties or this box already being the best are omitted.
 */
export function betterElsewhereFor(
    chances: Map<number, BoxCandidate[]>,
    boxes: PopNowBox[],
    boxId: number,
): Map<string, { box: PopNowBox; probability: number }> {
    const ownProbabilities = new Map((chances.get(boxId) ?? []).map((c) => [c.sku.id, c.probability]));
    const result = new Map<string, { box: PopNowBox; probability: number }>();

    for (const [otherBoxId, candidates] of chances) {
        if (otherBoxId === boxId) continue;
        const box = boxes.find((b) => b.id === otherBoxId);
        if (!box) continue;

        for (const c of candidates) {
            const mine = ownProbabilities.get(c.sku.id) ?? 0;
            if (c.probability <= mine) continue;
            const existing = result.get(c.sku.id);
            if (!existing || c.probability > existing.probability) {
                result.set(c.sku.id, { box, probability: c.probability });
            }
        }
    }

    return result;
}

export function boxCounts(boxes: PopNowBox[]): Record<PopNowBox['state'], number> {
    const counts = { available: 0, locked_other: 0, locked_mine: 0, sold: 0 };
    for (const box of boxes) counts[box.state]++;
    return counts;
}

export const formatPercent = (value: number) => `${Math.round(value * 100)}%`;
