import { CalendarClock, PackageCheck, PackageMinus, PackageX, type LucideIcon } from 'lucide-react';
import { statusMeta, type ProductStatus } from '@/lib/productStatus';

// Stock-specific icons: a box that's full, running low, empty, or a scheduled release.
const statusIcons: Record<ProductStatus, LucideIcon> = {
    in_stock: PackageCheck,
    low_stock: PackageMinus,
    coming_soon: CalendarClock,
    sold_out: PackageX,
};

/** Status icon in its status ink colour (for places that show a count next to it, like filter chips). */
export function StatusIcon({ status, className = 'size-3.5' }: { status: ProductStatus; className?: string }) {
    const Icon = statusIcons[status];
    return <Icon aria-hidden="true" strokeWidth={2.25} className={`shrink-0 ${statusMeta[status].text} ${className}`} />;
}

/**
 * Tinted status pill: icon, label and background all carry the status, so it
 * reads at a glance and never relies on colour alone.
 */
export function StatusBadge({ status, size = 'sm' }: { status: ProductStatus; size?: 'sm' | 'xs' }) {
    const Icon = statusIcons[status];

    return (
        <span
            className={`inline-flex items-center gap-1 font-bold whitespace-nowrap uppercase ${statusMeta[status].badge} ${
                size === 'xs' ? 'px-1.5 py-0.5 text-[10px] tracking-wide' : 'px-2 py-1 text-[11px] tracking-wider'
            }`}
        >
            <Icon aria-hidden="true" strokeWidth={2.25} className={`shrink-0 ${size === 'xs' ? 'size-3' : 'size-3.5'}`} />
            {statusMeta[status].label}
        </span>
    );
}
