import { DropsView } from '@/components/pages/DropsView';
import { samplePinnedIds, sampleProducts, sampleWatchedIds } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { MockSiteFrame } from './MockSiteFrame';

/** Drops on sample data. The real page (resources/js/Pages/Drops.tsx) renders the same DropsView. */
export function DropsPage() {
    const actions = useProductActions(samplePinnedIds, sampleWatchedIds);

    return (
        <MockSiteFrame currentHref="/drops">
            <DropsView products={sampleProducts} actions={actions} />
        </MockSiteFrame>
    );
}
