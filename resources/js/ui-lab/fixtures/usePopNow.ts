import { useState } from 'react';
import type { SkuSummary } from '@/types/catalog';
import type { BoxHint, PopNowBox, PopNowSet } from '@/types/popnow';
import { lockStore } from './lockStore';
import { sampleSets } from './popnow';

const HOLD_SECONDS = 300;

/**
 * In-memory stand-in for the POP NOW endpoints: sets and boxes, plus lock,
 * release, report-hint and confirm-hint that update the grid immediately.
 */
export function usePopNow() {
    const [sets, setSets] = useState(sampleSets);

    const updateBox = (boxId: number, update: (box: PopNowBox) => PopNowBox) =>
        setSets((all) => all.map((entry) => ({ ...entry, boxes: entry.boxes.map((b) => (b.id === boxId ? update(b) : b)) })));

    const setOf = (boxId: number) => sets.find((entry) => entry.boxes.some((b) => b.id === boxId))!;

    const lockBox = (box: PopNowBox) => {
        const { set } = setOf(box.id);
        const now = new Date().toISOString();
        const expires = new Date(Date.now() + HOLD_SECONDS * 1000).toISOString();
        updateBox(box.id, (b) => ({ ...b, state: 'locked_mine', lock_expires_at: expires }));
        // Shows up under "Held for you" on the Auto-lock page too.
        lockStore.addAttempt({
            id: `att-manual-${box.id}-${Date.now()}`,
            rule_id: 'manual',
            product: set.product,
            set_no: set.set_no,
            box_no: box.box_no,
            figure: null,
            status: 'locked',
            attempted_at: now,
            locked_at: now,
            lock_expires_at: expires,
            renewals: 0,
            hold_ends_at: null,
            error: null,
        });
    };

    /** Lock every currently-free box in one set to you. */
    const lockAllInSet = (_set: PopNowSet, boxes: PopNowBox[]) => {
        boxes.filter((b) => b.state === 'available').forEach(lockBox);
    };

    /** Lock every currently-free box across every given set to you. */
    const lockAllSets = (entries: { set: PopNowSet; boxes: PopNowBox[] }[]) => {
        entries.forEach(({ set, boxes }) => lockAllInSet(set, boxes));
    };

    return {
        sets,
        lockBox,
        lockAllInSet,
        lockAllSets,
        releaseBox: (box: PopNowBox) => updateBox(box.id, (b) => ({ ...b, state: 'available', lock_expires_at: null })),
        reportHint: (box: PopNowBox, sku: SkuSummary) =>
            updateBox(box.id, (b) => {
                const existing = b.hints.find((h) => h.sku.id === sku.id && h.source === 'user_reported');
                const now = new Date().toISOString();
                return existing
                    ? { ...b, hints: b.hints.map((h) => (h === existing ? { ...h, confirmations: h.confirmations + 1, last_seen_at: now } : h)) }
                    : { ...b, hints: [...b.hints, { sku, source: 'user_reported', confirmations: 1, last_seen_at: now }] };
            }),
        confirmHint: (box: PopNowBox, hint: BoxHint) =>
            updateBox(box.id, (b) => ({
                ...b,
                hints: b.hints.map((h) => (h.sku.id === hint.sku.id && h.source === hint.source ? { ...h, confirmations: h.confirmations + 1 } : h)),
            })),
    };
}
