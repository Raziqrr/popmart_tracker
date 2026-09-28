import type { ProductCardData } from './catalog';

/**
 * POP NOW auto-lock. NOT IMPLEMENTED on the backend: these shapes are the
 * proposal in docs/tickets/auto-lock.md (tables auto_lock_rules and
 * auto_lock_attempts). Settle names there before building controllers.
 */

/** A figure (SKU) in a POP NOW set that a rule can target. */
export interface LockFigure {
    sku_id: string;
    name: string;
    image_url: string | null;
    /** skus.box_type === 'secret'. */
    is_secret: boolean;
}

/** What fires a lock attempt. */
export type LockTrigger =
    /** The draw opens (products.sale_start_at reached). */
    | 'sale_opens'
    /** Boxes become available again after the set sold out. */
    | 'restock'
    /** A box gets a hint (pop_now_box_hints) for the target figure. */
    | 'hint_match';

/** One chosen figure and how many boxes of it to lock. */
export interface LockPick {
    figure: LockFigure;
    count: number;
}

/** One figure in a priority queue: 1 = try first. */
export interface LockRankedPick {
    figure: LockFigure;
    priority: number;
}

export type LockTarget =
    /** The system locks any free boxes, up to count. */
    | { kind: 'random'; count: number }
    | {
          /** Lock boxes hinted to hold these figures, count of each. */
          kind: 'specific';
          picks: LockPick[];
          /** Only lock boxes whose hint has at least this many confirmations. */
          min_confirmations: number;
          /** Ignore user_reported hints, only trust verified_api ones. */
          verified_only: boolean;
      }
    | {
          /**
           * Lock up to `count` boxes total, working down a priority list:
           * fill from figure #1's hinted boxes first, then #2, and so on,
           * until `count` is reached — unlike `specific`, which fixes an
           * exact count per figure regardless of what's actually available.
           */
          kind: 'ranked';
          count: number;
          picks: LockRankedPick[];
          min_confirmations: number;
          verified_only: boolean;
      };

/**
 * Keep holding a box past Pop Mart's single-hold limit by locking it again
 * shortly before each hold runs out, until a total hold time is reached.
 */
export interface LockRenewal {
    /** Re-lock when this many seconds are left on the current hold. */
    renew_when_seconds_left: number;
    /** Stop renewing once the box has been held this long in total. */
    max_total_hold_seconds: number;
}

export interface AutoLockRule {
    id: string;
    product: ProductCardData;
    target: LockTarget;
    trigger: LockTrigger;
    /** How long to hold a locked box; never more than PopNowLockLimits.max_lock_seconds. */
    lock_duration_seconds: number;
    /** Auto-renew the hold; null = hold once, let it lapse. */
    renew: LockRenewal | null;
    /** Successful locks so far; the rule is done at totalBoxes(target). */
    locks_made: number;
    enabled: boolean;
    /** Rule switches itself off after this time; null = until the draw ends. */
    expires_at: string | null;
    created_at: string;
}

/** A rule without server-side fields, as edited in the setup dialog. */
export type AutoLockRuleDraft = Pick<AutoLockRule, 'target' | 'trigger' | 'lock_duration_seconds' | 'renew' | 'enabled' | 'expires_at'>;

export type LockAttemptStatus =
    /** Trigger fired, request in flight. */
    | 'pending'
    /** Box is held for the user right now. */
    | 'locked'
    /** User paid on Pop Mart while it was held. */
    | 'purchased'
    /** Hold ran out before paying. */
    | 'expired'
    /** User (or the rule) released it early. */
    | 'released'
    /** Pop Mart refused: box taken, session expired, rate limited... */
    | 'failed';

export interface LockAttempt {
    id: string;
    rule_id: string;
    product: ProductCardData;
    set_no: string;
    box_no: string;
    /** The figure the box was hinted to hold, when the rule targeted one. */
    figure: LockFigure | null;
    status: LockAttemptStatus;
    attempted_at: string;
    locked_at: string | null;
    lock_expires_at: string | null;
    /** Re-locks done so far for this box (0 = original hold). */
    renewals: number;
    /** When the total hold ends if every renewal succeeds; null when not renewing. */
    hold_ends_at: string | null;
    error: string | null;
}

/** Pop Mart's own limits for a POP NOW set (from pop_now_boxes.lock_duration_seconds). */
export interface PopNowLockLimits {
    max_lock_seconds: number;
}

/** The linked Pop Mart account the lock would act on (popmart_accounts). */
export interface LockAccount {
    label: string;
    area: string;
    session_valid: boolean;
}
