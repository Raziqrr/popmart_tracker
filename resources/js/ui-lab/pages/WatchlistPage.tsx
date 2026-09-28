import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { ProductBrowser } from '@/components/product/ProductGrid';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

/** Everything the user pinned or set alerts on, in the dense list view. */
export function WatchlistPage() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);

    return (
        <MockSiteFrame currentHref="/watchlist" pinnedCount={actions.pinnedIds.size}>
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Watchlist' }]} />

            <header className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold sm:text-3xl">Watchlist</h1>
                <p className="text-sm text-black/60">
                    {actions.pinnedIds.size} pinned to your dashboard · {actions.watchedIds.size} with alerts on
                </p>
            </header>

            <ProductBrowser products={sampleProducts} defaultView="list" defaultScope="pinned" {...actions} />
        </MockSiteFrame>
    );
}
