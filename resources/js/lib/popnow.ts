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
     * Exact chance per unopened box (from boxChances), keyed by box id. The
     * same numbers "Best bet per figure" uses, so the two panels always agree.
     */
    chanceByBox: Map<number, number>;
    /** Highest chance in a box you can still get (isObtainable); 0 when none. */
    bestChance: number;
    /** Unopened boxes whose own hints have ruled this figure out. */
    excludedFromBoxes: number;
    /** Unopened boxes where this figure is still a possible candidate. */
    possibleBoxes: number;
}

/**
 * What's still in the set: per figure, whether it's revealed, the exact chance
 * in every unopened box, and how many boxes its tip-card hints rule out
 * (hints only ever prove what a box ISN'T). Every set scraped so far is
 * exactly one of each non-secret figure, so "revealed" makes a figure gone.
 * Pass the set's boxChances when you already have them.
 */
export function setOdds(set: PopNowSet, boxes: PopNowBox[], chances: Map<number, BoxCandidate[]> = boxChances(set, boxes)): { unopened: number; figures: FigureOdds[] } {
    const unopenedBoxes = boxes.filter((b) => b.state !== 'sold');
    const unopened = unopenedBoxes.length;
    const skus = set.product.skus ?? [];

    const figures = skus.map((sku): FigureOdds => {
        const revealed = boxes.some((b) => b.reveal?.sku.id === sku.id);

        if (sku.is_secret) {
            return { sku, revealed, trackable: false, chanceByBox: new Map(), bestChance: 0, excludedFromBoxes: 0, possibleBoxes: 0 };
        }

        const chanceByBox = new Map(unopenedBoxes.map((b) => [b.id, chances.get(b.id)?.find((c) => c.sku.id === sku.id)?.probability ?? 0]));
        const possibleBoxes = revealed ? [] : unopenedBoxes.filter((b) => !isExcludedFromBox(b, sku.id));

        return {
            sku,
            revealed,
            trackable: true,
            chanceByBox,
            bestChance: Math.max(0, ...unopenedBoxes.filter(isObtainable).map((b) => chanceByBox.get(b.id) ?? 0)),
            excludedFromBoxes: unopened - (revealed ? 0 : possibleBoxes.length),
            possibleBoxes: possibleBoxes.length,
        };
    });

    // Secrets first (what people hunt), then most likely.
    figures.sort((a, b) => Number(b.sku.is_secret) - Number(a.sku.is_secret) || b.bestChance - a.bestChance);
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

    const completions = countCompletions(allowed.map((skus) => skus.map((s) => s.id)));

    if (completions === null) {
        // Too many distinct hint patterns to count exactly: fall back to each
        // box's own hints (1/N across its remaining candidates), like the backend.
        unresolved.forEach((box, i) => {
            const p = allowed[i].length ? 1 / allowed[i].length : 0;
            result.set(box.id, allowed[i].map((sku) => ({ sku, probability: p })));
        });
        return result;
    }

    const { counts, total } = completions;
    const skuById = new Map(available.map((s) => [s.id, s]));

    unresolved.forEach((box, i) => {
        if (total === 0) {
            result.set(box.id, []);
            return;
        }
        const entries = Object.entries(counts[i]).map(([skuId, count]) => ({
            sku: skuById.get(skuId)!,
            probability: count / total,
        }));
        entries.sort((a, b) => b.probability - a.probability);
        result.set(box.id, entries);
    });

    return result;
}

/**
 * Limits for countCompletions before it gives up and boxChances falls back to
 * per-box odds: distinct states, and table cells actually stored. A 16-box
 * set with every box hinted differently is 65,536 states; a 48-box set stays
 * exact with up to about 12 distinct hint patterns. Mirrors
 * ExclusionBoxPredictor::MAX_DP_STATES / MAX_DP_CELLS.
 */
export const MAX_DP_STATES = 1 << 18;
export const MAX_DP_CELLS = 1 << 20;

/**
 * Counts every way to give each box a different figure from its own allowed
 * list, and how many of those ways put figure s in box i. Returns null when
 * the set is too irregular to count within MAX_DP_CELLS. Mirrors
 * ExclusionBoxPredictor::countValidCompletions().
 *
 * Boxes with the same allowed list are interchangeable, so they're grouped:
 * a state is "how many boxes of each group are filled so far", not which
 * boxes. A fresh 48-box set is one group (49 states); n boxes that all
 * have different hints are the worst case (2^n states).
 *
 * Figures are processed one at a time. `after[j][s]` = ways figures j..m-1
 * can fill everything still open in state s; a forward pass carries
 * `before[s]` = ways figures 0..j-1 reached state s. Figure j going into
 * one of the (size − filled) open boxes of group g then accounts for
 * before[s] × open × after[j+1][s + g] completions, shared evenly by the
 * group's boxes. Each step only visits states with a reachable number of
 * filled boxes (see window), so with as many figures as boxes every state
 * is visited about once per pass.
 */
export function countCompletions(allowed: string[][]): { counts: Record<string, number>[]; total: number } | null {
    const n = allowed.length;
    const counts: Record<string, number>[] = Array.from({ length: n }, () => ({}));
    const skuIds = [...new Set(allowed.flat())];
    const m = skuIds.length;

    // More boxes than figures left means no valid way to fill them all.
    if (n === 0 || n > m) return { counts, total: 0 };

    // Group boxes by identical allowed list.
    const groupIndex = new Map<string, number>();
    const groups: { boxes: number[]; skus: Set<string> }[] = [];
    allowed.forEach((list, box) => {
        const key = [...list].sort().join('|');
        let g = groupIndex.get(key);
        if (g === undefined) {
            g = groups.length;
            groupIndex.set(key, g);
            groups.push({ boxes: [], skus: new Set(list) });
        }
        groups[g].boxes.push(box);
    });

    // Mixed-radix state: digit g = boxes of group g filled, 0..size.
    const sizes = groups.map((g) => g.boxes.length);
    const strides: number[] = [];
    let states = 1;
    for (const size of sizes) {
        strides.push(states);
        states *= size + 1;
        if (states > MAX_DP_STATES) return null;
    }
    const full = states - 1;
    const groupCount = groups.length;

    // Per state, how many boxes are still open in each group and in total.
    // Precomputed once with an odometer so the hot loops below never divide.
    const open = new Uint8Array(states * groupCount);
    const openTotal = new Uint16Array(states);
    {
        const digits = new Array(groupCount).fill(0);
        for (let s = 0; s < states; s++) {
            let total = 0;
            for (let g = 0; g < groupCount; g++) {
                const left = sizes[g] - digits[g];
                open[s * groupCount + g] = left;
                total += left;
            }
            openTotal[s] = total;
            for (let g = 0; g < groupCount && ++digits[g] > sizes[g]; g++) digits[g] = 0;
        }
    }

    // States bucketed by boxes filled. After j figures at most j boxes are
    // filled, and the n − filled open boxes must fit in the m − j figures
    // left, so each step only ever touches the buckets in that window.
    const byFilled: number[][] = Array.from({ length: n + 1 }, () => []);
    for (let s = 0; s < states; s++) byFilled[n - openTotal[s]].push(s);
    const window = (j: number) => [Math.max(0, n - (m - j)), Math.min(j, n)];

    // groupsFor[j] = the groups figure j may go in.
    const groupsFor = skuIds.map((id) => Int32Array.from(groups.flatMap((group, g) => (group.skus.has(id) ? [g] : []))));

    // after[j] is only ever needed for the states in window(j), so each step
    // stores just those buckets (indexed by a state's position in its bucket).
    // Anything outside the window has too many open boxes left: 0 ways.
    const position = new Int32Array(states);
    for (const bucket of byFilled) bucket.forEach((s, i) => (position[s] = i));

    let storedCells = 0;
    for (let j = 0; j <= m; j++) {
        const [lo, hi] = window(j);
        for (let f = lo; f <= hi; f++) storedCells += byFilled[f].length;
    }
    if (storedCells > MAX_DP_CELLS) return null;

    const after: { lo: number; buckets: Float64Array[] }[] = new Array(m + 1);
    const read = (j: number, s: number) => {
        const layer = after[j];
        const bucket = layer.buckets[n - openTotal[s] - layer.lo];
        return bucket ? bucket[position[s]] : 0;
    };

    {
        const [lo, hi] = window(m);
        after[m] = { lo, buckets: byFilled.slice(lo, hi + 1).map((bucket) => new Float64Array(bucket.length)) };
        after[m].buckets[n - lo][position[full]] = 1;
    }
    for (let j = m - 1; j >= 0; j--) {
        const fits = groupsFor[j];
        const [lo, hi] = window(j);
        const buckets: Float64Array[] = [];
        for (let f = lo; f <= hi; f++) {
            const bucket = new Float64Array(byFilled[f].length);
            byFilled[f].forEach((s, i) => {
                let ways = read(j + 1, s); // figure j left unused
                const row = s * groupCount;
                for (let k = 0; k < fits.length; k++) {
                    const g = fits[k];
                    const left = open[row + g];
                    if (left > 0) ways += left * read(j + 1, s + strides[g]);
                }
                bucket[i] = ways;
            });
            buckets.push(bucket);
        }
        after[j] = { lo, buckets };
    }

    const total = read(0, 0);
    if (total === 0) return { counts, total: 0 };

    // One array updated in place: walking buckets from most filled down means
    // a state is read before anything adds this figure's placements into it.
    const before = new Float64Array(states);
    before[0] = 1;
    for (let j = 0; j < m; j++) {
        const perGroup = new Float64Array(groupCount);
        const fits = groupsFor[j];
        const [lo, hi] = window(j);
        for (let f = hi; f >= lo; f--) {
            for (const s of byFilled[f]) {
                const ways = before[s];
                if (ways === 0) continue;
                const row = s * groupCount;
                for (let k = 0; k < fits.length; k++) {
                    const g = fits[k];
                    const left = open[row + g];
                    if (left === 0) continue;
                    const placed = s + strides[g];
                    perGroup[g] += ways * left * read(j + 1, placed);
                    before[placed] += ways * left;
                }
            }
        }
        for (const g of fits) {
            if (perGroup[g] === 0) continue;
            // Every box in a group is equally likely to be the one that got it.
            const share = perGroup[g] / sizes[g];
            for (const box of groups[g].boxes) counts[box][skuIds[j]] = share;
        }
    }

    return { counts, total };
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
