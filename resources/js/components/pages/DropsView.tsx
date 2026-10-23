import { CalendarDays, GalleryHorizontal, Rows3 } from 'lucide-react';
import { useState } from 'react';
import { ReleaseCalendar } from '@/components/drops/ReleaseCalendar';
import { ReleaseTimeline } from '@/components/drops/ReleaseTimeline';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import type { ProductActions } from '@/components/product/actions';
import { ProductSection, ViewSwitch } from '@/components/product/ProductGrid';
import type { ProductCardData } from '@/types/catalog';

type DropsMode = 'calendar' | 'timeline' | 'list';

/** Groups products into release sections by sale date, latest first. */
function groupBySaleDate(products: ProductCardData[]) {
    const groups = new Map<string, ProductCardData[]>();

    for (const product of products) {
        const day = product.sale_start_at?.slice(0, 10) ?? 'unscheduled';
        groups.set(day, [...(groups.get(day) ?? []), product]);
    }

    return [...groups.entries()].sort(([a], [b]) => b.localeCompare(a));
}

/** Upcoming and recent releases as a calendar, timeline or list. */
export function DropsView({ products, actions }: { products: ProductCardData[]; actions: ProductActions }) {
    const [mode, setMode] = useState<DropsMode>('calendar');
    const dropProps = {
        products,
        productHref: actions.productHref,
        watchedIds: actions.watchedIds,
        onToggleWatch: actions.onToggleWatch,
    };

    return (
        <>
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Drops' }]} />

            <div className="flex items-center justify-between gap-4">
                <h1 className="text-2xl font-bold sm:text-3xl">Drops</h1>
                <ViewSwitch
                    value={mode}
                    onChange={setMode}
                    options={[
                        { key: 'calendar', label: 'Calendar view', icon: <CalendarDays aria-hidden="true" className="size-4" /> },
                        { key: 'timeline', label: 'Timeline view', icon: <GalleryHorizontal aria-hidden="true" className="size-4" /> },
                        { key: 'list', label: 'List view', icon: <Rows3 aria-hidden="true" className="size-4" /> },
                    ]}
                />
            </div>

            {mode === 'calendar' && <ReleaseCalendar {...dropProps} />}
            {mode === 'timeline' && <ReleaseTimeline {...dropProps} />}
            {mode === 'list' &&
                groupBySaleDate(products).map(([day, group]) => (
                    <ProductSection
                        key={day}
                        title={group.every((p) => p.is_coming_soon) ? 'Coming soon' : 'Released'}
                        date={group[0].sale_start_at}
                        products={group}
                        {...actions}
                    />
                ))}
        </>
    );
}
