import { usePage } from '@inertiajs/react';
import { AppLayout } from '@/components/layout/AppLayout';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { ProductBrowser } from '@/components/product/ProductGrid';
import { useProductActions } from '@/lib/useProductActions';
import type { ProductListProps } from '@/types/page';

/** GET /watchlist (PageController::watchlist, logged in): pinned and watched products. */
export default function Watchlist() {
    const { props } = usePage<ProductListProps>();
    const actions = useProductActions();

    return (
        <AppLayout title="Watchlist">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Watchlist' }]} />
            <header className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold sm:text-3xl">Watchlist</h1>
                <p className="text-sm text-black/60">
                    {actions.pinnedIds.size} pinned to your dashboard · {actions.watchedIds.size} with alerts on
                </p>
            </header>
            {props.products.length === 0 ? (
                <p className="py-10 text-center text-sm text-black/50">
                    Nothing here yet. Pin a product to keep it on your dashboard, or watch it to get alerts.
                </p>
            ) : (
                <ProductBrowser products={props.products} defaultView="list" defaultScope="pinned" {...actions} />
            )}
        </AppLayout>
    );
}
