import { usePage } from '@inertiajs/react';
import { AppLayout } from '@/components/layout/AppLayout';
import { DropsView } from '@/components/pages/DropsView';
import { useProductActions } from '@/lib/useProductActions';
import type { ProductListProps } from '@/types/page';

/** GET /drops (PageController::drops): upcoming and recent releases. */
export default function Drops() {
    const { props } = usePage<ProductListProps>();
    const actions = useProductActions();

    return (
        <AppLayout title="Drops">
            <DropsView products={props.products} actions={actions} />
        </AppLayout>
    );
}
