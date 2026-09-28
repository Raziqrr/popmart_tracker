import { CalendarDays, GalleryHorizontal, Rows3 } from 'lucide-react';
import { useState } from 'react';
import { ReleaseCalendar } from '@/components/drops/ReleaseCalendar';
import { ReleaseTimeline } from '@/components/drops/ReleaseTimeline';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { ProductSection, ViewSwitch } from '@/components/product/ProductGrid';
import type { ProductCardData } from '@/types/catalog';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

type DropsView = 'calendar' | 'timeline' | 'list';

/** Groups products into release sections by sale date, latest first. */
function groupBySaleDate(products: ProductCardData[]) {
    const groups = new Map<string, ProductCardData[]>();

    for (const product of products) {
        const day = product.sale_start_at?.slice(0, 10) ?? 'unscheduled';
        groups.set(day, [...(groups.get(day) ?? []), product]);
    }

    return [...groups.entries()].sort(([a], [b]) => b.localeCompare(a));
}

export function DropsPage() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);
    const [view, setView] = useState<DropsView>('calendar');
    const dropProps = {
        products: sampleProducts,
        productHref: actions.productHref,
        watchedIds: actions.watchedIds,
        onToggleWatch: actions.onToggleWatch,
    };

    return (
        <MockSiteFrame currentHref="/drops">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Drops' }]} />

            <div className="flex items-center justify-between gap-4">
                <h1 className="text-2xl font-bold sm:text-3xl">Drops</h1>
                <ViewSwitch
                    value={view}
                    onChange={setView}
                    options={[
                        { key: 'calendar', label: 'Calendar view', icon: <CalendarDays aria-hidden="true" className="size-4" /> },
                        { key: 'timeline', label: 'Timeline view', icon: <GalleryHorizontal aria-hidden="true" className="size-4" /> },
                        { key: 'list', label: 'List view', icon: <Rows3 aria-hidden="true" className="size-4" /> },
                    ]}
                />
            </div>

            {view === 'calendar' && <ReleaseCalendar {...dropProps} />}
            {view === 'timeline' && <ReleaseTimeline {...dropProps} />}
            {view === 'list' &&
                groupBySaleDate(sampleProducts).map(([day, products]) => (
                    <ProductSection
                        key={day}
                        title={products.every((p) => p.is_coming_soon) ? 'Coming soon' : 'Released'}
                        date={products[0].sale_start_at}
                        products={products}
                        {...actions}
                    />
                ))}
        </MockSiteFrame>
    );
}
