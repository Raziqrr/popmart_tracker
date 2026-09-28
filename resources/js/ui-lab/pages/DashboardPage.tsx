import { CalendarClock, Flame, History, PackageCheck, PackageX, Pin, Sparkles } from 'lucide-react';
import { ActivityFeed } from '@/components/dashboard/ActivityFeed';
import { CollectionStatus } from '@/components/dashboard/CollectionStatus';
import { FastSellers } from '@/components/dashboard/FastSellers';
import { LuckyPointsTile } from '@/components/dashboard/LuckyPointsTile';
import { NewListings } from '@/components/dashboard/NewListings';
import { Panel } from '@/components/dashboard/Panel';
import { StatRow, type Stat } from '@/components/dashboard/StatRow';
import { ProductTable } from '@/components/product/ProductTable';
import { formatCountdown } from '@/lib/format';
import { countStatuses } from '@/lib/productStatus';
import type { ProductCardData, ProductEvent } from '@/types/catalog';
import { fakeRedeem, sampleLuckyPoints } from '../fixtures/account';
import { sampleCollections, samplePinnedCollectionIds } from '../fixtures/collections';
import { sampleEvents } from '../fixtures/events';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useIdSet, useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

const DAY = 24 * 3_600_000;

/** "2 pinned · 5 overall" style figures: pinned first, catalog-wide as context. */
function eventStat(events: ProductEvent[], type: ProductEvent['type'], pinnedIds: ReadonlySet<string>) {
    const recent = events.filter((e) => e.type === type && Date.now() - new Date(e.occurred_at).getTime() < DAY);
    return { pinned: recent.filter((e) => pinnedIds.has(e.product.id)).length, overall: recent.length };
}

function buildStats(products: ProductCardData[], events: ProductEvent[], pinnedIds: ReadonlySet<string>, pinnedCollections: number): Stat[] {
    const pinned = products.filter((p) => pinnedIds.has(p.id));
    const counts = countStatuses(pinned);
    const restocks = eventStat(events, 'restocked', pinnedIds);
    const soldOut = eventStat(events, 'sold_out', pinnedIds);

    const upcoming = products.filter((p) => p.is_coming_soon && p.sale_start_at).sort((a, b) => a.sale_start_at!.localeCompare(b.sale_start_at!));
    const nextDrop = upcoming.find((p) => pinnedIds.has(p.id)) ?? upcoming[0];
    const fastest = [...products].sort((a, b) => (b.sell_rate_per_hour ?? 0) - (a.sell_rate_per_hour ?? 0))[0];

    return [
        {
            id: 'pinned',
            label: 'Pinned',
            icon: Pin,
            value: pinned.length + pinnedCollections,
            detail: `${counts.low_stock} low · ${counts.sold_out} sold out · ${counts.coming_soon} upcoming`,
            href: '#pinned',
            highlight: counts.low_stock + counts.sold_out > 0,
        },
        {
            id: 'restocks',
            label: 'Restocks 24h',
            icon: PackageCheck,
            value: restocks.pinned,
            detail: `pinned · ${restocks.overall} overall`,
            href: '#activity',
        },
        {
            id: 'next-drop',
            label: 'Next drop',
            icon: CalendarClock,
            value: nextDrop ? formatCountdown(nextDrop.sale_start_at!) : '—',
            detail: nextDrop?.name,
            href: '/drops',
        },
        {
            id: 'sold-out',
            label: 'Sold out 24h',
            icon: PackageX,
            value: soldOut.pinned,
            detail: `pinned · ${soldOut.overall} overall`,
            href: '#activity',
            highlight: soldOut.pinned > 0,
        },
        {
            id: 'fastest',
            label: 'Fastest seller',
            icon: Flame,
            value: fastest?.sell_rate_per_hour ? `${fastest.sell_rate_per_hour}/hr` : '—',
            detail: fastest?.name,
            href: '#selling-fast',
            estimate: true,
        },
    ];
}

export function DashboardPage() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);
    const [pinnedCollectionIds, togglePinnedCollection] = useIdSet(samplePinnedCollectionIds);

    const pinnedProducts = sampleProducts.filter((p) => actions.pinnedIds.has(p.id));
    const pinnedCollections = sampleCollections.filter((c) => pinnedCollectionIds.has(c.id));
    const stats = buildStats(sampleProducts, sampleEvents, actions.pinnedIds, pinnedCollections.length);

    return (
        <MockSiteFrame currentHref="/" pinnedCount={pinnedProducts.length + pinnedCollections.length}>
            <header className="flex flex-wrap items-baseline justify-between gap-2">
                <h1 className="text-2xl font-bold">Dashboard</h1>
                <p className="text-xs text-black/50">Malaysia · prices checked every 5 minutes</p>
            </header>

            <StatRow
                lead={<LuckyPointsTile points={sampleLuckyPoints} onRedeem={fakeRedeem} />}
                stats={stats}
            />

            <Panel title="Pinned" icon={Pin} action={{ label: 'Manage', href: '/watchlist' }}>
                <div id="pinned" className="flex flex-col gap-4">
                    {pinnedCollections.length > 0 && (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {pinnedCollections.map((collection) => (
                                <CollectionStatus
                                    key={collection.id}
                                    collection={collection}
                                    href={`#${collection.slug}`}
                                    onUnpin={togglePinnedCollection}
                                />
                            ))}
                        </div>
                    )}
                    {pinnedProducts.length > 0 ? (
                        <ProductTable products={pinnedProducts} caption="Pinned products" compact {...actions} />
                    ) : (
                        <p className="py-6 text-center text-sm text-black/50">Nothing pinned. Use the pin icon on any product.</p>
                    )}
                </div>
            </Panel>

            <div className="grid gap-10 lg:grid-cols-2">
                <Panel title="New products & collections" icon={Sparkles} action={{ label: 'All new', href: '/catalog?sort=newest' }}>
                    <NewListings
                        products={sampleProducts}
                        collections={sampleCollections}
                        collectionHref={(c) => `#${c.slug}`}
                        {...actions}
                    />
                </Panel>

                <Panel title="Selling fast" icon={Flame}>
                    <div id="selling-fast">
                        <FastSellers products={sampleProducts} {...actions} />
                    </div>
                </Panel>
            </div>

            <Panel title="Recent changes" icon={History}>
                <div id="activity">
                    <ActivityFeed events={sampleEvents} pinnedIds={actions.pinnedIds} productHref={actions.productHref} limit={6} />
                </div>
            </Panel>
        </MockSiteFrame>
    );
}
