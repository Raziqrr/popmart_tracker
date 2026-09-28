import type { ProductCardData, SkuSummary } from '@/types/catalog';

export type SaleTypeFilter = 'all' | 'draw' | 'shop';

export interface ProductFilters {
    /** Theme names (Pop Mart calls them IPs); empty = all. */
    ips: string[];
    /** Category names; empty = all. */
    types: string[];
    /** POP NOW (business_type = draw), normal shop listings, or both. */
    saleType: SaleTypeFilter;
    /** Figure name, SKU code or barcode. */
    sku: string;
}

export const emptyFilters: ProductFilters = { ips: [], types: [], saleType: 'all', sku: '' };

export const NO_IP = 'Other';

export function ipOf(product: ProductCardData): string {
    return product.theme_name ?? NO_IP;
}

export function typeOf(product: ProductCardData): string {
    return product.category_name ?? 'Uncategorised';
}

/** Figures of a product matching a figure name, SKU code or barcode (case-insensitive, partial). */
export function matchingSkus(product: ProductCardData, query: string): SkuSummary[] {
    const q = query.trim().toLowerCase();
    if (!q) return [];

    return (product.skus ?? []).filter(
        (sku) => sku.name.toLowerCase().includes(q) || sku.sku_code?.toLowerCase().includes(q) || sku.bar_code?.includes(q),
    );
}

/** Whether a product passes every active filter. */
export function passesFilters(product: ProductCardData, filters: ProductFilters): boolean {
    if (filters.ips.length && !filters.ips.includes(ipOf(product))) return false;
    if (filters.types.length && !filters.types.includes(typeOf(product))) return false;
    if (filters.saleType !== 'all' && product.business_type !== filters.saleType) return false;
    if (filters.sku.trim() && matchingSkus(product, filters.sku).length === 0) return false;
    return true;
}

/** How many filters are active, for the "Filters (3)" count and "Clear all". */
export function activeFilterCount(filters: ProductFilters): number {
    return filters.ips.length + filters.types.length + (filters.saleType !== 'all' ? 1 : 0) + (filters.sku.trim() ? 1 : 0);
}

/** Distinct values with counts, sorted by count then name. */
export function facet(products: ProductCardData[], valueOf: (p: ProductCardData) => string): { value: string; count: number }[] {
    const counts = new Map<string, number>();
    for (const product of products) counts.set(valueOf(product), (counts.get(valueOf(product)) ?? 0) + 1);

    return [...counts.entries()].map(([value, count]) => ({ value, count })).sort((a, b) => b.count - a.count || a.value.localeCompare(b.value));
}
