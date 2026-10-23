import { usePage } from '@inertiajs/react';
import { AppLayout } from '@/components/layout/AppLayout';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { ProductBrowser } from '@/components/product/ProductGrid';
import { useProductActions } from '@/lib/useProductActions';
import type { ProductListProps } from '@/types/page';

/** GET /catalog (PageController::catalog). */
export default function Catalog() {
    const { props } = usePage<ProductListProps>();
    const actions = useProductActions();

    return (
        <AppLayout title="Catalog">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Catalog' }]} />
            <header className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold sm:text-3xl">Catalog</h1>
                <p className="text-sm text-black/60">{props.products.length} products tracked</p>
            </header>
            <ProductBrowser products={props.products} {...actions} />
        </AppLayout>
    );
}
