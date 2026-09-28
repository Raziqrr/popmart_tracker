import { ProductBrowser, ProductGrid } from '@/components/product/ProductGrid';
import { ProductTable } from '@/components/product/ProductTable';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { LabHeader, Specimen } from '../Specimen';

export function ProductListLab() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="Product lists"
                description="The browser (search, pinned/alerts scope, status filter, sort, grid/list switch), the dense table and the card grid. Resize below 768px to see the table collapse into rows."
                source="components/product/ProductGrid.tsx, ProductTable.tsx"
            />

            <Specimen label="ProductBrowser" note="Try the scope, status chips and view switch">
                <ProductBrowser products={sampleProducts} {...actions} />
            </Specimen>

            <Specimen label="ProductTable" note="Full columns">
                <ProductTable products={sampleProducts.slice(2, 8)} {...actions} />
            </Specimen>

            <Specimen label="ProductTable: compact" note="Dashboard variant, no Checked column">
                <ProductTable products={sampleProducts.slice(2, 5)} compact {...actions} />
            </Specimen>

            <Specimen label="ProductGrid: empty">
                <ProductGrid products={[]} />
            </Specimen>
        </div>
    );
}
