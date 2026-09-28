import { useCallback, useState } from 'react';

/** In-memory toggleable id set: stand-in for wishlist_items / pinned_items so buttons work in the lab. */
export function useIdSet(initial: string[] = []) {
    const [ids, setIds] = useState<Set<string>>(() => new Set(initial));

    const toggle = useCallback(
        ({ id }: { id: string }) =>
            setIds((current) => {
                const next = new Set(current);
                if (!next.delete(id)) next.add(id);
                return next;
            }),
        [],
    );

    return [ids, toggle] as const;
}

/** Pinned + alert state and handlers, ready to spread into any product list as ProductActions. */
export function useProductActions(pinned: string[] = [], watched: string[] = []) {
    const [pinnedIds, onTogglePin] = useIdSet(pinned);
    const [watchedIds, onToggleWatch] = useIdSet(watched);

    return { pinnedIds, onTogglePin, watchedIds, onToggleWatch, productHref: (p: { slug: string }) => `#${p.slug}` };
}
