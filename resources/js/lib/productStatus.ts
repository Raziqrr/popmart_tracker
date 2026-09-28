import type { ProductCardData, StatusCounts } from '@/types/catalog';

export type ProductStatus = keyof StatusCounts;

/** At or below this remain_stock, an in-stock product counts as low stock. */
export const LOW_STOCK_THRESHOLD = 20;

export function productStatus(product: ProductCardData): ProductStatus {
    if (product.is_coming_soon) return 'coming_soon';
    if (product.is_sold_out) return 'sold_out';
    if (product.remain_stock !== null && product.remain_stock <= LOW_STOCK_THRESHOLD) return 'low_stock';
    return 'in_stock';
}

export const STATUS_ORDER: ProductStatus[] = ['in_stock', 'low_stock', 'coming_soon', 'sold_out'];

/**
 * Status colours follow the fixed status palette; they're always paired with
 * an icon (see StatusIcon) and a text label, never colour alone.
 */
export const statusMeta: Record<ProductStatus, { label: string; text: string; badge: string; fill: string }> = {
    in_stock: {
        label: 'In stock',
        text: 'text-status-good-ink',
        badge: 'bg-status-good-tint text-status-good-ink',
        fill: 'bg-status-good',
    },
    low_stock: {
        label: 'Low stock',
        text: 'text-status-warning-ink',
        badge: 'bg-status-warning-tint text-status-warning-ink',
        fill: 'bg-status-warning',
    },
    coming_soon: {
        label: 'Coming soon',
        text: 'text-status-upcoming-ink',
        badge: 'bg-status-upcoming-tint text-status-upcoming-ink',
        fill: 'bg-black',
    },
    sold_out: {
        label: 'Sold out',
        text: 'text-status-critical-ink',
        badge: 'bg-status-critical-tint text-status-critical-ink',
        fill: 'bg-status-critical',
    },
};

export function countStatuses(products: ProductCardData[]): StatusCounts {
    const counts: StatusCounts = { in_stock: 0, low_stock: 0, coming_soon: 0, sold_out: 0 };
    for (const product of products) counts[productStatus(product)]++;
    return counts;
}
