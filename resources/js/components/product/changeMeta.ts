import { CalendarCheck, PackageCheck, PackageMinus, PackageX, Sparkles, TrendingDown, TrendingUp, type LucideIcon } from 'lucide-react';
import { formatMoney } from '@/lib/format';
import type { ProductCardData, ProductChange, ProductEventType } from '@/types/catalog';

export type ChangeGroup = 'stock' | 'price' | 'new';

/** How each kind of detected change is named, drawn and grouped. Shared by the feed and the "Last change" column. */
export const changeMeta: Record<ProductEventType, { label: string; icon: LucideIcon; ink: string; group: ChangeGroup }> = {
    restocked: { label: 'Restocked', icon: PackageCheck, ink: 'text-status-good-ink', group: 'stock' },
    low_stock: { label: 'Stock falling', icon: PackageMinus, ink: 'text-status-warning-ink', group: 'stock' },
    sold_out: { label: 'Sold out', icon: PackageX, ink: 'text-status-critical-ink', group: 'stock' },
    price_drop: { label: 'Price drop', icon: TrendingDown, ink: 'text-good-text', group: 'price' },
    price_rise: { label: 'Price up', icon: TrendingUp, ink: 'text-black/70', group: 'price' },
    listed: { label: 'New listing', icon: Sparkles, ink: 'text-black', group: 'new' },
    sale_started: { label: 'Sale opened', icon: CalendarCheck, ink: 'text-brand', group: 'new' },
};

/** The before → after of a change in plain words, e.g. "12 → 8 left" or "RM49.80 → RM44.80". */
export function changeDetail(change: ProductChange, product: Pick<ProductCardData, 'price' | 'currency'>): string {
    const { from, to } = change;
    const money = (v: number) => formatMoney(v, product.currency);

    switch (change.type) {
        case 'restocked':
            return to != null ? `${from ?? 0} → ${to} in stock` : 'Back in stock';
        case 'low_stock':
            return to != null ? (from != null ? `${from} → ${to} left` : `${to} left`) : 'Running low';
        case 'sold_out':
            return from != null ? `Last ${from} sold` : 'No stock left';
        case 'price_drop':
        case 'price_rise':
            return from != null && to != null ? `${money(from)} → ${money(to)}` : '';
        case 'listed':
            return `Listed at ${money(product.price)}`;
        case 'sale_started':
            return `Opened at ${money(product.price)}`;
    }
}
