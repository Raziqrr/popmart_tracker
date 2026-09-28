import type { ProductCardData, SkuSummary } from '@/types/catalog';

/** Per-user state and handlers shared by every product list (cards, table, feeds). */
export interface ProductActions {
    /** Products with alerts on (wishlist_items). */
    watchedIds?: ReadonlySet<string>;
    /** Products pinned to the dashboard (pinned_items). */
    pinnedIds?: ReadonlySet<string>;
    onToggleWatch?: (product: ProductCardData) => void;
    onTogglePin?: (product: ProductCardData) => void;
    productHref?: (product: ProductCardData) => string;
    /** POP NOW products with an enabled auto-lock rule. */
    lockedIds?: ReadonlySet<string>;
    /** Opens auto-lock setup; the lock button only shows on POP NOW products when this is set. */
    onLockClick?: (product: ProductCardData) => void;
    /** Figures that matched the SKU filter, by product id; shown as "Has: …". */
    skuMatches?: ReadonlyMap<string, SkuSummary[]>;
}
