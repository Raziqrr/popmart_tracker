import { usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import type { ProductActions } from '@/components/product/actions';
import { ApiError, apiRequest } from '@/lib/api';
import { useCan } from '@/lib/capabilities';
import type { CollectionSummary, ProductCardData } from '@/types/catalog';
import type { SharedProps, UserLists } from '@/types/page';

type Kind = 'product' | 'collection';

/**
 * Pins and watches for the logged-in user, ready to spread into any product
 * list as ProductActions. Starts from the shared `lists` prop, flips the
 * button at once, then POSTs to /api/pins or /api/watches and takes the
 * server's lists as the truth (undoing the flip if the call fails).
 * Guests are sent to log in.
 */
export function useProductActions() {
    const { props } = usePage<SharedProps>();
    const [lists, setLists] = useState<UserLists>(props.lists);
    const { allowed } = useCan('watchlist');

    const toggle = useCallback(
        async (endpoint: '/api/pins' | '/api/watches', field: keyof UserLists, kind: Kind, id: string) => {
            if (!allowed) {
                window.location.href = '/login';
                return;
            }

            const before = lists;
            const current = new Set(lists[field]);
            if (!current.delete(id)) current.add(id);
            setLists({ ...lists, [field]: [...current] });

            try {
                setLists(await apiRequest<UserLists>(endpoint, { method: 'POST', body: { type: kind, id } }));
            } catch (error) {
                setLists(before);
                if (error instanceof ApiError && error.code === 'unauthenticated') window.location.href = '/login';
            }
        },
        [allowed, lists],
    );

    const actions: ProductActions & {
        pinnedIds: ReadonlySet<string>;
        watchedIds: ReadonlySet<string>;
        pinnedCollectionIds: ReadonlySet<string>;
        onTogglePinCollection: (collection: CollectionSummary) => void;
    } = {
        pinnedIds: new Set(lists.pinned_product_ids),
        watchedIds: new Set(lists.watched_product_ids),
        pinnedCollectionIds: new Set(lists.pinned_collection_ids),
        onTogglePin: (product: ProductCardData) => toggle('/api/pins', 'pinned_product_ids', 'product', product.id),
        onToggleWatch: (product: ProductCardData) => toggle('/api/watches', 'watched_product_ids', 'product', product.id),
        onTogglePinCollection: (collection: CollectionSummary) => toggle('/api/pins', 'pinned_collection_ids', 'collection', collection.id),
        productHref: (product: ProductCardData) => `/catalog#${product.slug}`,
    };

    return actions;
}
