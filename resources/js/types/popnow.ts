import type { ProductCardData, SkuSummary } from './catalog';

/**
 * POP NOW draw sets and boxes, mirroring pop_now_sets / pop_now_boxes /
 * pop_now_box_hints / pop_now_box_reveals.
 * SYNC: see app/Models/README.md. Fields marked DERIVED are computed by the controller.
 *
 * There is no known-in-advance "recipe" for a set (no `composition` field):
 * Pop Mart never tells us how many of each figure a set contains before it's
 * fully revealed, and every set scraped so far has empirically been exactly
 * one of each non-secret SKU (proven deterministic — see app/Models/README.md).
 * The full figure list for a set is `product.skus` (secrets excluded client-side
 * where relevant), not a separate per-set count.
 */

export interface PopNowSet {
    id: number;
    product: ProductCardData;
    set_no: string;
    /** Grid shape (pop_now_sets.set_width / set_height). */
    width: number;
    height: number;
    total_boxes: number;
    first_seen_at: string;
    last_seen_at: string;
}

export type HintSource = 'verified_api' | 'user_reported';

/**
 * A figure PROVEN EXCLUDED from a box (pop_now_box_hints) — Pop Mart's
 * tip-card mechanic only ever reveals figures a box is NOT, never confirms
 * one it is. Proven deterministic and server-side-fixed across 36 real
 * datapoints on 4 independent accounts: everyone tipping the same box sees
 * the same exclusions. `source: 'user_reported'` is someone sharing an
 * exclusion they observed themselves (e.g. from their own tip), not a guess
 * at what the box holds.
 */
export interface BoxHint {
    sku: SkuSummary;
    source: HintSource;
    confirmations: number;
    last_seen_at: string;
}

/** The figure a box turned out to hold (pop_now_box_reveals). */
export interface BoxReveal {
    sku: SkuSummary;
    source: 'order_reveal' | 'open_box_api' | 'user_reported';
    revealed_at: string;
}

/**
 * Box state as the grid shows it. DERIVED from status, is_locked and
 * locked_by_other, plus whether the lock belongs to the current user.
 */
export type BoxState = 'available' | 'locked_other' | 'locked_mine' | 'sold';

export interface PopNowBox {
    id: number;
    box_no: string;
    /** 0-based index into the width × height grid (pop_now_boxes.position). */
    position: number;
    state: BoxState;
    /** When the current lock ends (lock_started_at + lock_duration_seconds). */
    lock_expires_at: string | null;
    hints: BoxHint[];
    reveal: BoxReveal | null;
    last_seen_at: string;
}
