import type { ProductCardData } from '@/types/catalog';

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** Local-date key (YYYY-MM-DD) so products group by the user's calendar day, not UTC. */
export function dayKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** Monday-first weekday label for a date. */
export function weekdayLabel(date: Date): string {
    return WEEKDAYS[(date.getDay() + 6) % 7];
}

/** Products grouped by local sale day, each day sorted by sale time. */
export function groupByDay(products: ProductCardData[]): Map<string, ProductCardData[]> {
    const map = new Map<string, ProductCardData[]>();

    for (const product of products) {
        if (!product.sale_start_at) continue;
        const key = dayKey(new Date(product.sale_start_at));
        map.set(key, [...(map.get(key) ?? []), product]);
    }
    for (const list of map.values()) list.sort((a, b) => a.sale_start_at!.localeCompare(b.sale_start_at!));

    return map;
}
