import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { AutoLockContext, type AutoLockApi } from '@/components/lock/AutoLockContext';
import { AutoLockDialog } from '@/components/lock/AutoLockDialog';
import { QuickLockFinder } from '@/components/lock/QuickLockFinder';
import { createAutoLockRule, deleteAutoLockRule } from '@/lib/api';
import { useCan } from '@/lib/capabilities';
import type { ProductCardData } from '@/types/catalog';
import type { AutoLockRule, LockFigure, PopNowLockLimits } from '@/types/lock';
import type { SharedProps } from '@/types/page';

function isTyping(target: EventTarget | null): boolean {
    return target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

const DEFAULT_LIMITS: PopNowLockLimits = {
    max_lock_seconds: 300,
};

export function AutoLockProvider({ children }: { children: ReactNode }) {
    const { props } = usePage<SharedProps>();
    const canAutoLock = useCan('auto_lock');
    const [editing, setEditing] = useState<{ product: ProductCardData; rule: AutoLockRule | null } | null>(null);
    const [finderOpen, setFinderOpen] = useState(false);

    // If on AutoLock page, read rules from props; otherwise empty set by default
    const pageRules = (props.rules as AutoLockRule[] | undefined) ?? [];
    const [activeRules, setActiveRules] = useState<AutoLockRule[]>(pageRules);

    useEffect(() => {
        if (Array.isArray(props.rules)) {
            setActiveRules(props.rules as AutoLockRule[]);
        }
    }, [props.rules]);

    const lockedIds = useMemo(() => new Set(activeRules.filter((r) => r.enabled).map((r) => r.product.id)), [activeRules]);

    const open = useCallback(
        (product: ProductCardData) => {
            if (!canAutoLock) {
                router.visit('/login');
                return;
            }
            setEditing({
                product,
                rule: activeRules.find((r) => r.product.id === product.id) ?? null,
            });
        },
        [canAutoLock, activeRules],
    );

    const openRule = useCallback(
        (rule: AutoLockRule) => {
            setEditing({ product: rule.product, rule });
        },
        [],
    );

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key.toLowerCase() !== 'l' || e.metaKey || e.ctrlKey || e.altKey || isTyping(e.target)) return;
            if (document.querySelector('dialog[open]')) return;
            e.preventDefault();
            if (!canAutoLock) {
                router.visit('/login');
                return;
            }
            setFinderOpen(true);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [canAutoLock]);

    const api = useMemo<AutoLockApi>(
        () => ({
            lockedIds,
            open,
            openRule,
            openFinder: () => {
                if (!canAutoLock) {
                    router.visit('/login');
                    return;
                }
                setFinderOpen(true);
            },
        }),
        [lockedIds, open, openRule, canAutoLock],
    );

    const drawProducts = useMemo(() => {
        const prods = (props.products as ProductCardData[] | undefined) ?? [];
        return prods.filter((p) => p.business_type === 'draw');
    }, [props.products]);

    const figuresForProduct = useCallback((product: ProductCardData): LockFigure[] => {
        return (product.skus ?? []).map((sku) => ({
            sku_id: sku.id,
            name: sku.name,
            image_url: sku.image_url ?? null,
            is_secret: sku.is_secret,
        }));
    }, []);

    return (
        <AutoLockContext.Provider value={api}>
            {children}

            {finderOpen && (
                <QuickLockFinder
                    open
                    products={drawProducts}
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
                    key={`${editing.product.id}-${editing.rule?.id ?? 'new'}`}
                    open
                    product={editing.product}
                    figures={figuresForProduct(editing.product)}
                    limits={(props.limits as PopNowLockLimits | undefined) ?? DEFAULT_LIMITS}
                    account={null}
                    rule={editing.rule}
                    acknowledged={true}
                    onClose={() => setEditing(null)}
                    onDelete={async (rule) => {
                        try {
                            await deleteAutoLockRule(rule.id);
                            setActiveRules((prev) => prev.filter((r) => r.id !== rule.id));
                        } catch {
                            // ignore or handle
                        }
                        setEditing(null);
                    }}
                    onSave={async (draft) => {
                        try {
                            const saved = await createAutoLockRule(editing.product.id, draft, true);
                            setActiveRules((prev) => [...prev.filter((r) => r.id !== saved.id), saved]);
                        } catch {
                            // ignore or handle
                        }
                        setEditing(null);
                    }}
                    onMoreOptions={(draft) => {
                        setEditing(null);
                        router.visit(`/auto-lock?mode=setup&product=${editing.product.id}`);
                    }}
                />
            )}
        </AutoLockContext.Provider>
    );
}
