import { History, ListChecks, Lock, ShieldAlert, SlidersHorizontal } from 'lucide-react';
import { Panel } from '@/components/dashboard/Panel';
import { ActiveLocks } from '@/components/lock/ActiveLocks';
import { LockHistory } from '@/components/lock/LockHistory';
import { LockRules } from '@/components/lock/LockRules';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { ProductTable } from '@/components/product/ProductTable';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { lockStore } from '../fixtures/lockStore';
import { LOCK_SETUP_ROUTE, useAutoLock } from '../fixtures/useAutoLock';
import { useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

/** Held boxes first (they're on a timer), then rules, then what happened. */
export function AutoLockPage() {
    const lock = useAutoLock();
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);
    const popNowProducts = sampleProducts.filter((p) => p.business_type === 'draw');

    return (
        <MockSiteFrame currentHref="/auto-lock">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Auto-lock' }]} />

            <header className="flex flex-col gap-3">
                <h1 className="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                    <Lock aria-hidden="true" className="size-7" />
                    Auto-lock
                </h1>
                <p className="flex max-w-3xl items-start gap-2 bg-status-warning-tint p-3 text-xs text-status-warning-ink">
                    <ShieldAlert aria-hidden="true" className="size-4 shrink-0" />
                    Auto-lock holds POP NOW boxes on your Pop Mart account when a rule fires, then alerts you to pay before the hold runs out. It
                    acts on your real account and may break Pop Mart's terms.
                </p>
            </header>

            <Panel title="Held for you" icon={Lock}>
                <ActiveLocks locks={lock.attempts} onRelease={lock.releaseLock} payHref={() => 'https://www.popmart.com/my'} />
            </Panel>

            <Panel title="Rules" icon={ListChecks}>
                <LockRules rules={lock.rules} attempts={lock.attempts} onToggle={lock.toggleRule} onEdit={lock.editRule} />
            </Panel>

            <Panel title="Add a rule" icon={Lock}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-black/60">Use a product's lock button for quick setup, or open the full setup.</p>
                    <a
                        href={LOCK_SETUP_ROUTE}
                        onClick={() => lockStore.setHandoff(null)}
                        className="inline-flex items-center gap-1.5 border border-black px-3 py-1.5 text-xs font-bold hover:bg-black hover:text-white"
                    >
                        <SlidersHorizontal aria-hidden="true" className="size-3.5" />
                        Full setup
                    </a>
                </div>
                <ProductTable products={popNowProducts} caption="POP NOW products" compact {...actions} />
            </Panel>

            <Panel title="History" icon={History}>
                <LockHistory attempts={lock.attempts} />
            </Panel>

        </MockSiteFrame>
    );
}
