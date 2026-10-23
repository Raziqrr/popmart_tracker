import {
    CalendarCheck,
    CalendarClock,
    PackageCheck,
    PackageMinus,
    PackageX,
    ShoppingBag,
    Sparkles,
    Tag,
    TicketCheck,
    TrendingDown,
    TrendingUp,
    type LucideIcon,
} from 'lucide-react';
import { formatMoney, formatShortDateTime } from '@/lib/format';
import type { ProductCardData, ProductChange, ProductEventType } from '@/types/catalog';

export type ChangeGroup = 'stock' | 'price' | 'new' | 'preorder';

/** How each kind of detected change is named, drawn and grouped. Shared by the feed and the "Last change" column. */
export const changeMeta: Record<ProductEventType, { label: string; icon: LucideIcon; ink: string; group: ChangeGroup }> = {
    restocked: { label: 'Restocked', icon: PackageCheck, ink: 'text-status-good-ink', group: 'stock' },
    low_stock: { label: 'Stock falling', icon: PackageMinus, ink: 'text-status-warning-ink', group: 'stock' },
    sold_out: { label: 'Sold out', icon: PackageX, ink: 'text-status-critical-ink', group: 'stock' },
    price_drop: { label: 'Price drop', icon: TrendingDown, ink: 'text-good-text', group: 'price' },
    price_rise: { label: 'Price up', icon: TrendingUp, ink: 'text-black/70', group: 'price' },
    listed: { label: 'New listing', icon: Sparkles, ink: 'text-black', group: 'new' },
    sale_started: { label: 'Sale opened', icon: CalendarCheck, ink: 'text-brand', group: 'new' },
    sale_time_changed: { label: 'Sale time set', icon: CalendarClock, ink: 'text-black', group: 'new' },
    preorder_opened: { label: 'Pre-order opened', icon: TicketCheck, ink: 'text-brand', group: 'preorder' },
    preorder_closed: { label: 'Pre-order closed', icon: ShoppingBag, ink: 'text-black/70', group: 'preorder' },
    tag_added: { label: 'Tag added', icon: Tag, ink: 'text-black/70', group: 'new' },
    tag_removed: { label: 'Tag removed', icon: Tag, ink: 'text-black/50', group: 'new' },
};

/** The before → after of a change in plain words, e.g. "12 → 8 left" or "RM49.80 → RM44.80". */
export function changeDetail(change: ProductChange, product: Pick<ProductCardData, 'price' | 'currency'>): string {
    const { from, to } = change;
    const money = (v: number | string) => formatMoney(Number(v), product.currency);

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
        case 'sale_time_changed':
            return typeof to === 'string' ? `Opens ${formatShortDateTime(to)}` : 'Sale time changed';
        case 'preorder_opened':
            return 'Buy now, ships later (early pass)';
        case 'preorder_closed':
            return 'Pre-order window ended';
        case 'tag_added':
            return to != null ? `“${to}”` : '';
        case 'tag_removed':
            return from != null ? `“${from}”` : '';
    }
}
