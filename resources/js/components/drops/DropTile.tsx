import { ArrowUpRight, Bell, BellRing, MousePointerClick } from 'lucide-react';
import { LockToggle } from '@/components/lock/LockToggle';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import { StatusBadge } from '@/components/product/StatusBadge';
import { alertKind, watchLabel } from '@/components/product/WatchToggle';
import { formatMoney, formatSaleTime } from '@/lib/format';
import { productStatus } from '@/lib/productStatus';
import { useActivation } from '@/lib/useActivation';
import type { ProductCardData } from '@/types/catalog';

interface DropTileProps {
    product: ProductCardData;
    href?: string;
    watching: boolean;
    onToggleWatch?: (product: ProductCardData) => void;
    /** compact: thumbnail beside text (calendar day boxes); large: picture on top (timeline columns). */
    variant?: 'compact' | 'large';
}

/** Solid red pill naming the alert that's on, e.g. "Sale reminder". */
export function AlertBadge({ product, ring = 0 }: { product: ProductCardData; ring?: number }) {
    return (
        <span
            title={alertKind(product)}
            className="inline-flex w-fit max-w-full items-center gap-1 bg-brand px-1.5 py-0.5 text-[10px] leading-none font-bold whitespace-nowrap text-white"
        >
            <BellRing
                key={ring}
                aria-hidden="true"
                fill="currentColor"
                className={`size-3 shrink-0 origin-top ${ring ? 'motion-safe:animate-bell-ring' : ''}`}
            />
            <span className="truncate">{alertKind(product)}</span>
        </span>
    );
}

/**
 * One drop on the calendar or timeline. Double-click toggles its alert; the
 * bell button does the same for keyboard and single-click users. Opening the
 * product is a separate arrow link, so a double-click never navigates away
 * mid-gesture. A watched drop gets a red outline, tint and alert badge, and
 * pops when the alert switches on.
 */
export function DropTile({ product, href, watching, onToggleWatch, variant = 'compact' }: DropTileProps) {
    const label = watchLabel(product, watching);
    const activations = useActivation(watching);
    const large = variant === 'large';

    const actions = (
        <span className={`flex shrink-0 ${large ? 'absolute top-1 right-1 gap-0.5' : 'w-full justify-end gap-1'}`}>
            {href && (
                <a
                    href={href}
                    aria-label={`Open ${product.name}`}
                    title="Open product"
                    className="grid size-5 place-items-center bg-white/90 text-black opacity-0 group-hover/drop:opacity-100 hover:bg-black hover:text-white focus-visible:opacity-100"
                >
                    <ArrowUpRight aria-hidden="true" className="size-3.5" />
                </a>
            )}
            {onToggleWatch && (
                <button
                    type="button"
                    onClick={() => onToggleWatch(product)}
                    onDoubleClick={(e) => e.stopPropagation()}
                    aria-pressed={watching}
                    aria-label={`${watching ? 'Turn off' : label}: ${product.name}`}
                    title={watching ? `Turn off ${alertKind(product).toLowerCase()}` : label}
                    className={`grid size-5 place-items-center ${
                        watching
                            ? 'bg-brand text-white hover:bg-brand/85'
                            : 'bg-white/90 text-black opacity-0 group-hover/drop:opacity-100 hover:bg-black hover:text-white focus-visible:opacity-100'
                    }`}
                >
                    {watching ? (
                        <BellRing aria-hidden="true" fill="currentColor" className="size-3.5" />
                    ) : (
                        <Bell aria-hidden="true" className="size-3.5" />
                    )}
                </button>
            )}
            {/* Last, so an active lock sits in the corner rather than beside the hover-only buttons' empty space. */}
            <LockToggle product={product} size="sm" />
        </span>
    );

    return (
        <div
            key={activations}
            title={onToggleWatch ? `${product.name}\nDouble-click: ${watching ? 'turn alert off' : label.toLowerCase()}` : product.name}
            onDoubleClick={onToggleWatch ? () => onToggleWatch(product) : undefined}
            // Stop the browser from selecting the name when double-clicking.
            onMouseDown={(e) => {
                if (e.detail > 1) e.preventDefault();
            }}
            className={`group/drop relative flex min-w-0 gap-1.5 text-black transition-colors select-none ${
                large ? 'flex-col p-1' : 'flex-wrap items-center gap-y-1.5 px-1.5 py-2'
            } ${onToggleWatch ? 'cursor-pointer' : ''} ${
                watching ? 'bg-brand/5 ring-2 ring-brand ring-inset' : 'bg-tile hover:bg-black/10'
            } ${activations ? 'motion-safe:animate-pop' : ''}`}
        >
            <span className={`shrink-0 bg-white ${large ? 'aspect-square w-full' : 'size-10'}`}>
                {product.image_url && (
                    <img
                        src={product.image_url}
                        alt=""
                        loading="lazy"
                        draggable={false}
                        className={`size-full object-contain ${large ? 'p-[12%]' : 'p-0.5'} ${product.is_sold_out ? 'opacity-50 grayscale' : ''}`}
                    />
                )}
            </span>

            <span className={`flex min-w-0 grow flex-col ${large ? 'gap-1 px-1 pb-1' : 'basis-0 gap-0.5'}`}>
                <span className="flex items-center gap-1">
                    {product.sale_start_at && (
                        <span className="text-[10px] font-bold text-brand tabular-nums">{formatSaleTime(product.sale_start_at)}</span>
                    )}
                    <SaleTypeBadge product={product} iconOnly={!large} className="px-1 text-[9px]" />
                </span>
                <span className={`leading-tight font-medium ${large ? 'line-clamp-2 text-xs' : 'line-clamp-3 text-[11px]'}`}>{product.name}</span>
                {large && (
                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="text-xs font-medium text-brand">{formatMoney(product.price, product.currency)}</span>
                        <StatusBadge status={productStatus(product)} size="xs" />
                    </span>
                )}
                {watching && (
                    <span className="mt-1">
                        <AlertBadge product={product} ring={activations} />
                    </span>
                )}
            </span>

            {/* Compact tiles are too narrow to float the actions over the text, so they get their own full-width row. */}
            {actions}
        </div>
    );
}

/** One-line explanation of the double-click gesture, with a sample of the "alert on" look. */
export function DropGestureHint() {
    return (
        <p className="flex flex-wrap items-center gap-1.5 text-xs text-black/60 select-none">
            <MousePointerClick aria-hidden="true" className="size-4" />
            Double-click a drop to turn its alert on or off. Drops with an alert get a red outline and a badge like
            <span className="inline-flex items-center gap-1 bg-brand px-1.5 py-0.5 text-[10px] font-bold text-white">
                <BellRing aria-hidden="true" fill="currentColor" className="size-3" />
                Sale reminder
            </span>
        </p>
    );
}
