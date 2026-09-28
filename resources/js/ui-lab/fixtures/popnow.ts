import type { SkuSummary } from '@/types/catalog';
import type { BoxHint, BoxReveal, BoxState, PopNowBox, PopNowSet } from '@/types/popnow';
import { sampleFigures } from './lock';
import { productBySlug } from './products';
import { daysAgo, hoursAgo, minutesAgo } from './time';

// SYNC: follows types/popnow.ts (pop_now_sets / boxes / hints / reveals).

const midnightSnack = productBySlug('pebble-pals-midnight-snack');

/** The product's SKUs with pictures (same art as the lock fixtures). */
const figureImages = new Map(sampleFigures[midnightSnack.id].map((f) => [f.name, f.image_url]));
const skus: SkuSummary[] = (midnightSnack.skus ?? []).map((s) => ({ ...s, image_url: figureImages.get(s.name) ?? null }));
const sku = (name: string) => {
    const found = skus.find((s) => s.name === name);
    if (!found) throw new Error(`No SKU named ${name}`);
    return found;
};

// 5 boxes: one of each non-secret figure (Golden Moon Snack is the secret,
// which — per how Pop Mart's API actually behaves — never appears in a set
// slot count and is never excludable, so it's simply not one of the 5).
const midnightSnackProduct = { ...midnightSnack, skus };

const makeSet = (id: number, set_no: string, firstSeen: string): PopNowSet => ({
    id,
    product: midnightSnackProduct,
    set_no,
    width: 5,
    height: 1,
    total_boxes: 5,
    first_seen_at: firstSeen,
    last_seen_at: minutesAgo(1),
});

/** A figure PROVEN EXCLUDED from the box (never a claim about what it holds). */
const hint = (name: string, source: BoxHint['source'], confirmations: number, seen = minutesAgo(20)): BoxHint => ({
    sku: sku(name),
    source,
    confirmations,
    last_seen_at: seen,
});
const reveal = (name: string, when: string, source: BoxReveal['source'] = 'order_reveal'): BoxReveal => ({ sku: sku(name), source, revealed_at: when });
const inFuture = (seconds: number) => new Date(Date.now() + seconds * 1000).toISOString();

let nextBoxId = 1;
function box(position: number, state: BoxState, extra: Partial<PopNowBox> = {}): PopNowBox {
    return {
        id: nextBoxId++,
        box_no: String(position + 1).padStart(2, '0'),
        position,
        state,
        lock_expires_at: null,
        hints: [],
        reveal: null,
        last_seen_at: minutesAgo(1),
        ...extra,
    };
}

// The held box matches the "Held for you" lock attempt in fixtures/lock.ts (box 07 of S-1042, held 1 min ago).
const heldUntil = new Date(new Date(minutesAgo(1)).getTime() + 300_000).toISOString();

export const sampleSets: { set: PopNowSet; boxes: PopNowBox[] }[] = [
    {
        set: makeSet(1042, 'S-1042', hoursAgo(5)),
        boxes: [
            box(0, 'sold', { reveal: reveal('Sleepy Toast', hoursAgo(4)) }),
            // Own hints have ruled out Sleepy Toast and Fridge Raid for this box —
            // three candidates left (Midnight Noodles, Pillow Fort, Night Owl).
            box(1, 'available', {
                hints: [hint('Sleepy Toast', 'verified_api', 1), hint('Fridge Raid', 'verified_api', 1)],
            }),
            box(2, 'locked_other', { lock_expires_at: inFuture(130) }),
            // A second, independent exclusion pair on the same box, one user-reported.
            box(3, 'locked_mine', {
                lock_expires_at: heldUntil,
                hints: [hint('Midnight Noodles', 'verified_api', 1, minutesAgo(12)), hint('Night Owl', 'user_reported', 4)],
            }),
            box(4, 'available', { hints: [hint('Pillow Fort', 'user_reported', 1)] }),
        ],
    },
    {
        set: makeSet(1043, 'S-1043', minutesAgo(40)),
        boxes: Array.from({ length: 5 }, (_, i) =>
            box(
                i,
                'available',
                i === 2
                    ? { hints: [hint('Night Owl', 'user_reported', 3)] }
                    : i === 4
                      ? { hints: [hint('Midnight Noodles', 'verified_api', 1)] }
                      : {},
            ),
        ),
    },
    {
        set: makeSet(1038, 'S-1038', daysAgo(1)),
        boxes: (() => {
            const names = ['Night Owl', 'Sleepy Toast', 'Fridge Raid', 'Pillow Fort'];
            return Array.from({ length: 5 }, (_, i) =>
                i < names.length ? box(i, 'sold', { reveal: reveal(names[i], hoursAgo(20 - i)) }) : box(i, 'available'),
            );
        })(),
    },
];
