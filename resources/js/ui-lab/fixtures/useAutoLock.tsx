import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { AutoLockContext, useAutoLockApi, type AutoLockApi } from '@/components/lock/AutoLockContext';
import { AutoLockDialog } from '@/components/lock/AutoLockDialog';
import { QuickLockFinder } from '@/components/lock/QuickLockFinder';
import type { ProductCardData } from '@/types/catalog';
import type { AutoLockRule } from '@/types/lock';
import { sampleFigures, sampleLockAccount, sampleLockLimits } from './lock';
import { lockStore, useLockStore } from './lockStore';
import { sampleProducts } from './products';

/** Lab route of the full setup page. */
export const LOCK_SETUP_ROUTE = '#/page-auto-lock-setup';

const popNowProducts = sampleProducts.filter((p) => p.business_type === 'draw');

/** True while the user is typing, so single-key shortcuts don't fire. */
function isTyping(target: EventTarget | null): boolean {
    return target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

/**
 * Lab stand-in for the app-level auto-lock provider: owns the quick-setup
 * dialog, the product finder and the L shortcut, backed by the in-memory
 * lock store. In the real app the layout renders this once around every page.
 */
export function LabAutoLockProvider({ children }: { children: ReactNode }) {
    const { rules, acknowledged } = useLockStore();
    const [editing, setEditing] = useState<{ product: ProductCardData; rule: AutoLockRule | null } | null>(null);
    const [finderOpen, setFinderOpen] = useState(false);

    const lockedIds = useMemo(() => new Set(rules.filter((r) => r.enabled).map((r) => r.product.id)), [rules]);

    const open = useCallback(
        (product: ProductCardData) => setEditing({ product, rule: lockStore.get().rules.find((r) => r.product.id === product.id) ?? null }),
        [],
    );

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key.toLowerCase() !== 'l' || e.metaKey || e.ctrlKey || e.altKey || isTyping(e.target)) return;
            if (document.querySelector('dialog[open]')) return;
            e.preventDefault();
            setFinderOpen(true);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const api = useMemo<AutoLockApi>(
        () => ({
            lockedIds,
            open,
            openRule: (rule) => setEditing({ product: rule.product, rule }),
            openFinder: () => setFinderOpen(true),
        }),
        [lockedIds, open],
    );

    return (
        <AutoLockContext.Provider value={api}>
            {children}

            {finderOpen && (
                <QuickLockFinder
                    open
                    products={popNowProducts}
                    lockedIds={lockedIds}
                    onClose={() => setFinderOpen(false)}
                    onPick={(product) => {
                        setFinderOpen(false);
                        open(product);
                    }}
                />
            )}

            {editing && (
                <AutoLockDialog
                    // Remount per product/rule so the form starts from that rule's values.
                    key={`${editing.product.id}-${editing.rule?.id ?? 'new'}`}
                    open
                    product={editing.product}
                    figures={sampleFigures[editing.product.id] ?? []}
                    limits={sampleLockLimits}
                    account={sampleLockAccount}
                    rule={editing.rule}
                    acknowledged={acknowledged}
                    onClose={() => setEditing(null)}
                    onDelete={(rule) => {
                        lockStore.deleteRule(rule.id);
                        setEditing(null);
                    }}
                    onSave={(draft) => {
                        lockStore.saveRule(editing.product, draft, editing.rule?.id ?? null);
                        setEditing(null);
                    }}
                    onMoreOptions={(draft) => {
                        lockStore.setHandoff({ product: editing.product, draft, ruleId: editing.rule?.id ?? null });
                        setEditing(null);
                        window.location.hash = LOCK_SETUP_ROUTE;
                    }}
                />
            )}
        </AutoLockContext.Provider>
    );
}

/** Rules and attempts from the lab store, plus the page-level actions for the Auto-lock page. */
export function useAutoLock() {
    const { rules, attempts } = useLockStore();
    const api = useAutoLockApi();

    return {
        rules,
        attempts,
        editRule: (rule: AutoLockRule) => api?.openRule(rule),
        toggleRule: (rule: AutoLockRule) => lockStore.toggleRule(rule.id),
        releaseLock: (lock: { id: string }) => lockStore.releaseLock(lock.id),
    };
}
