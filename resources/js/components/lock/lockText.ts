import { productStatus } from '@/lib/productStatus';
import type { ProductCardData } from '@/types/catalog';
import type { AutoLockRuleDraft, LockAttemptStatus, LockRenewal, LockTarget, LockTrigger, PopNowLockLimits } from '@/types/lock';

export const triggerLabels: Record<LockTrigger, { title: string; detail: string }> = {
    sale_opens: { title: 'As soon as the draw opens', detail: 'Fires at the sale start time.' },
    restock: { title: 'When boxes come back', detail: 'Fires when a sold-out set gets new boxes.' },
    hint_match: { title: 'When a box is hinted to hold a chosen figure', detail: 'Fires when a hint for one of your figures appears.' },
};

export const attemptStatusMeta: Record<LockAttemptStatus, { label: string; tone: string }> = {
    pending: { label: 'Locking…', tone: 'bg-status-upcoming-tint text-status-upcoming-ink' },
    locked: { label: 'Held for you', tone: 'bg-status-warning-tint text-status-warning-ink' },
    purchased: { label: 'Paid', tone: 'bg-status-good-tint text-status-good-ink' },
    expired: { label: 'Expired', tone: 'bg-status-upcoming-tint text-black/60' },
    released: { label: 'Released', tone: 'bg-status-upcoming-tint text-black/60' },
    failed: { label: 'Failed', tone: 'bg-status-critical-tint text-status-critical-ink' },
};

/** Total boxes a target asks for: the random/ranked count, or the sum of specific picks. */
export function totalBoxes(target: LockTarget): number {
    if (target.kind === 'random' || target.kind === 'ranked') return target.count;
    return target.picks.reduce((sum, pick) => sum + pick.count, 0);
}

/** Trigger to preselect: the draw opening before sale, restock once sold out, hints otherwise. */
export function defaultTrigger(product: ProductCardData, target: LockTarget): LockTrigger {
    const status = productStatus(product);
    if (status === 'coming_soon') return 'sale_opens';
    if (status === 'sold_out') return 'restock';
    return target.kind === 'specific' || target.kind === 'ranked' ? 'hint_match' : 'restock';
}

/** A fresh draft for a product: random, 1 box, default trigger, Pop Mart's max hold. */
export function newDraft(product: ProductCardData, limits: PopNowLockLimits): AutoLockRuleDraft {
    const target: LockTarget = { kind: 'random', count: 1 };
    return { target, trigger: defaultTrigger(product, target), lock_duration_seconds: limits.max_lock_seconds, renew: null, enabled: true, expires_at: null };
}

export function formatHold(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return rest ? `${minutes}m ${rest}s` : `${minutes} min`;
}

const boxes = (n: number) => (n === 1 ? '1 box' : `${n} boxes`);

/** "2 random boxes" / "2× Sleepy Toast and 1× Golden Moon Snack (secret)" / "3 boxes, preferring Sleepy Toast, then Fridge Raid". */
export function describeTarget(target: LockTarget): string {
    if (target.kind === 'random') return `${target.count === 1 ? '1 random box' : `${target.count} random boxes`}`;

    if (target.kind === 'ranked') {
        const order = [...target.picks].sort((a, b) => a.priority - b.priority).map((p) => p.figure.name);
        const boxesLabel = target.count === 1 ? '1 box' : `${target.count} boxes`;
        return order.length === 0 ? `${boxesLabel}, no figures ranked yet` : `${boxesLabel}, preferring ${order.join(', then ')}`;
    }

    const parts = target.picks.map((p) => `${p.count}× ${p.figure.name}${p.figure.is_secret ? ' (secret)' : ''}`);
    return parts.length <= 1 ? (parts[0] ?? 'no figures') : `${parts.slice(0, -1).join(', ')} and ${parts.at(-1)}`;
}

/**
 * How a renewing hold plays out: each renewal buys (hold - renewAt) more
 * seconds, so n locks cover hold + (n - 1) × (hold - renewAt).
 */
export function renewalPlan(holdSeconds: number, renew: LockRenewal) {
    const step = Math.max(1, holdSeconds - renew.renew_when_seconds_left);
    const locks = Math.max(1, Math.ceil((renew.max_total_hold_seconds - holdSeconds) / step) + 1);
    return { locks, renewals: locks - 1, step };
}

/** The rule in one plain sentence. */
export function describeRule(rule: AutoLockRuleDraft): string {
    const when =
        rule.trigger === 'sale_opens'
            ? 'When the draw opens'
            : rule.trigger === 'restock'
              ? 'When boxes come back'
              : 'When boxes are hinted to hold your figures';

    // A total only adds information when several figures are picked.
    const total = rule.target.kind === 'specific' && rule.target.picks.length > 1 ? ` (${boxes(totalBoxes(rule.target))} total)` : '';

    const hold = rule.renew
        ? `for up to ${formatHold(rule.renew.max_total_hold_seconds)} each (re-locking with ${formatHold(
              rule.renew.renew_when_seconds_left,
          )} left on each ${formatHold(rule.lock_duration_seconds)} hold)`
        : `for ${formatHold(rule.lock_duration_seconds)} each`;

    return `${when}, lock ${describeTarget(rule.target)}${total} ${hold} and alert you to pay.`;
}
