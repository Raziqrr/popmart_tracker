import { DashboardView } from '@/components/pages/DashboardView';
import { fakeClaimTasks, fakeRedeem, sampleLuckyPoints } from '../fixtures/account';
import { sampleCollections, samplePinnedCollectionIds } from '../fixtures/collections';
import { sampleEvents } from '../fixtures/events';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useIdSet, useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

/** The dashboard on sample data. The real page (resources/js/Pages/Dashboard.tsx) renders the same DashboardView. */
export function DashboardPage() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);
    const [pinnedCollectionIds, togglePinnedCollection] = useIdSet(samplePinnedCollectionIds);
    const pinnedCollections = sampleCollections.filter((c) => pinnedCollectionIds.has(c.id));

    return (
        <MockSiteFrame currentHref="/" pinnedCount={actions.pinnedIds.size + pinnedCollections.length}>
            <DashboardView
                products={sampleProducts}
                collections={sampleCollections}
                pinnedCollections={pinnedCollections}
                events={sampleEvents}
                luckyPoints={sampleLuckyPoints}
                actions={actions}
                onUnpinCollection={togglePinnedCollection}
                onRedeem={fakeRedeem}
                onClaimTasks={fakeClaimTasks}
                sellRateIsEstimate
            />
        </MockSiteFrame>
    );
}
