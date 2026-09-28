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

/** 12 boxes: every regular figure twice, Night Owl three times, one secret. */
const composition = [
    { sku: sku('Midnight Noodles'), count: 2 },
    { sku: sku('Sleepy Toast'), count: 2 },
    { sku: sku('Fridge Raid'), count: 2 },
    { sku: sku('Pillow Fort'), count: 2 },
    { sku: sku('Night Owl'), count: 3 },
    { sku: sku('Golden Moon Snack'), count: 1 },
];

const makeSet = (id: number, set_no: string, firstSeen: string): PopNowSet => ({
    id,
    product: midnightSnack,
    set_no,
    width: 4,
    height: 3,
    total_boxes: 12,
    composition,
    first_seen_at: firstSeen,
    last_seen_at: minutesAgo(1),
});

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
            box(1, 'available', { hints: [hint('Midnight Noodles', 'verified_api', 1)] }),
            box(2, 'sold', { reveal: reveal('Night Owl', hoursAgo(3), 'open_box_api') }),
            box(3, 'locked_other', { lock_expires_at: inFuture(130) }),
            box(4, 'available', { hints: [hint('Pillow Fort', 'user_reported', 1)] }),
            box(5, 'available'),
            box(6, 'locked_mine', { lock_expires_at: heldUntil, hints: [hint('Golden Moon Snack', 'verified_api', 1, minutesAgo(12))] }),
            box(7, 'available', { hints: [hint('Night Owl', 'user_reported', 4), hint('Fridge Raid', 'user_reported', 1)] }),
            box(8, 'sold', { reveal: reveal('Midnight Noodles', hoursAgo(2)) }),
            box(9, 'available', { hints: [hint('Golden Moon Snack', 'user_reported', 2, minutesAgo(8))] }),
            box(10, 'locked_other', { lock_expires_at: inFuture(40) }),
            box(11, 'available'),
        ],
    },
    {
        set: makeSet(1043, 'S-1043', minutesAgo(40)),
        boxes: Array.from({ length: 12 }, (_, i) =>
            box(i, 'available', i === 5 ? { hints: [hint('Sleepy Toast', 'user_reported', 3)] } : i === 9 ? { hints: [hint('Night Owl', 'verified_api', 1)] } : {}),
        ),
    },
    {
        set: makeSet(1038, 'S-1038', daysAgo(1)),
        boxes: Array.from({ length: 12 }, (_, i) => {
            const names = ['Night Owl', 'Sleepy Toast', 'Golden Moon Snack', 'Fridge Raid', 'Night Owl', 'Pillow Fort', 'Midnight Noodles', 'Night Owl', 'Fridge Raid', 'Sleepy Toast'];
            return i < names.length ? box(i, 'sold', { reveal: reveal(names[i], hoursAgo(20 - i)) }) : box(i, 'available');
        }),
    },
];
