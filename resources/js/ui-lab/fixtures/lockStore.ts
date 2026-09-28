import { useSyncExternalStore } from 'react';
import type { ProductCardData } from '@/types/catalog';
import type { AutoLockRule, AutoLockRuleDraft, LockAttempt } from '@/types/lock';
import { sampleLockAttempts, sampleLockRules } from './lock';

/**
 * Lab-only in-memory store shared by every page, standing in for the
 * auto-lock endpoints, so a rule saved on the full setup page shows up on the
 * Auto-lock page. Resets on reload.
 */
interface LockState {
    rules: AutoLockRule[];
    attempts: LockAttempt[];
    /** Product + draft handed from the quick dialog to the full setup page. */
    handoff: { product: ProductCardData; draft: AutoLockRuleDraft; ruleId: string | null } | null;
    /** The user has ticked the risk acknowledgement at least once. */
    acknowledged: boolean;
}

let state: LockState = { rules: sampleLockRules, attempts: sampleLockAttempts, handoff: null, acknowledged: true };
const listeners = new Set<() => void>();

function set(patch: Partial<LockState>) {
    state = { ...state, ...patch };
    listeners.forEach((l) => l());
}

export const lockStore = {
    get: () => state,
    subscribe: (listener: () => void) => {
        listeners.add(listener);
        return () => listeners.delete(listener);
    },
    saveRule(product: ProductCardData, draft: AutoLockRuleDraft, ruleId: string | null) {
        set({
            acknowledged: true,
            rules: ruleId
                ? state.rules.map((r) => (r.id === ruleId ? { ...r, ...draft } : r))
                : [...state.rules, { ...draft, id: `rule-${Date.now()}`, product, locks_made: 0, created_at: new Date().toISOString() }],
        });
    },
    deleteRule: (id: string) => set({ rules: state.rules.filter((r) => r.id !== id) }),
    toggleRule: (id: string) => set({ rules: state.rules.map((r) => (r.id === id ? { ...r, enabled: !r.enabled } : r)) }),
    releaseLock: (id: string) => set({ attempts: state.attempts.map((a) => (a.id === id ? { ...a, status: 'released' } : a)) }),
    addAttempt: (attempt: LockAttempt) => set({ attempts: [attempt, ...state.attempts] }),
    setHandoff: (handoff: LockState['handoff']) => set({ handoff }),
    resetAcknowledgement: () => set({ acknowledged: false }),
};

export function useLockStore(): LockState {
    return useSyncExternalStore(lockStore.subscribe, lockStore.get);
}
