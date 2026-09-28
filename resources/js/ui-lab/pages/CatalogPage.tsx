import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { ProductBrowser } from '@/components/product/ProductGrid';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

export function CatalogPage() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);

    return (
        <MockSiteFrame currentHref="/catalog">
            <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Catalog' }]} />

            <header className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold sm:text-3xl">Catalog</h1>
                <p className="text-sm text-black/60">{sampleProducts.length} products tracked in Malaysia</p>
            </header>

            <ProductBrowser products={sampleProducts} {...actions} />
        </MockSiteFrame>
    );
}
