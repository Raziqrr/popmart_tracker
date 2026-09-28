import { Package, Plane, RefreshCw, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatCountdown, formatSaleTime, formatTimeAgo } from '@/lib/format';
import { productStatus } from '@/lib/productStatus';
import type { ProductCardData, SkuSummary } from '@/types/catalog';
import { LockToggle } from '@/components/lock/LockToggle';
import { PriceTag } from './PriceTag';
import { SaleTypeBadge } from './SaleTypeBadge';
import { SkuMatches } from './SkuMatches';
import { StatusBadge } from './StatusBadge';
import { StockTrend } from './StockTrend';
import { PinToggle, WatchToggle } from './WatchToggle';

interface ProductCardProps {
    product: ProductCardData;
    href?: string;
    watching?: boolean;
    pinned?: boolean;
    onToggleWatch?: (product: ProductCardData) => void;
    onTogglePin?: (product: ProductCardData) => void;
    locked?: boolean;
    onLockClick?: (product: ProductCardData) => void;
    /** Figures that matched the SKU filter. */
    matchedSkus?: SkuSummary[];
    /** Show the red sale-start time above the image, as on release calendars. */
    showSaleTime?: boolean;
}

/**
 * Monitoring-first product tile: status, stock and freshness are read before
 * the name; pin and alert are compact icon toggles rather than a shop CTA.
 */
export function ProductCard({
    product,
    href,
    watching = false,
    pinned = false,
    onToggleWatch,
    onTogglePin,
    locked,
    onLockClick,
    matchedSkus,
    showSaleTime = false,
}: ProductCardProps) {
    const status = productStatus(product);
    const Title = href ? 'a' : 'span';

    return (
        <article className="group relative flex w-full flex-col">
            {showSaleTime && product.sale_start_at && (
                <p className="mb-2 font-medium text-brand">
                    <time dateTime={product.sale_start_at}>{formatSaleTime(product.sale_start_at)}</time>
                </p>
            )}

            <div className="relative aspect-square overflow-hidden bg-tile">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt=""
                        loading="lazy"
                        className={`size-full object-contain p-[14%] transition-transform duration-300 group-hover:scale-105 ${
                            status === 'sold_out' ? 'opacity-50 grayscale' : ''
                        }`}
                    />
                ) : (
                    <div className="grid size-full place-items-center text-xs text-black/40">No image</div>
                )}

                <div className="absolute top-2 left-2 flex flex-col items-start gap-1">
                    {product.is_new && (
                        <Badge tone="dark">
                            <Sparkles aria-hidden="true" className="size-3" fill="currentColor" strokeWidth={1.5} />
                            New
                        </Badge>
                    )}
                    <SaleTypeBadge product={product} />
                </div>

                <div className="absolute top-2 right-2 flex flex-col gap-1">
                    <PinToggle product={product} active={pinned} onToggle={onTogglePin} />
                    <WatchToggle product={product} active={watching} onToggle={onToggleWatch} />
                    <LockToggle product={product} active={locked} onClick={onLockClick} />
                </div>

                {product.warehouse === 'cross_border' && (
                    <span className="absolute inset-x-0 bottom-1.5 flex items-center justify-center gap-1 text-[10px] font-medium tracking-wide text-sky-700 uppercase">
                        <Plane aria-hidden="true" className="size-3" />
                        Global express
                    </span>
                )}
            </div>

            <div className="flex grow flex-col gap-1.5 pt-2.5">
                <div className="flex items-center justify-between gap-2">
                    <StatusBadge status={status} />
                    <StockHint product={product} />
                </div>

                <div>
                    {product.theme_name && (
                        <p className="text-[10px] font-medium tracking-wide text-black/50 uppercase">{product.theme_name}</p>
                    )}
                    <Title
                        {...(href ? { href } : {})}
                        className={`line-clamp-2 text-xs leading-snug font-medium text-black ${
                            // With an href, the title's ::after stretches over the card to make it clickable.
                            href ? 'after:absolute after:inset-0 hover:underline' : ''
                        }`}
                    >
                        {product.name}
                    </Title>
                </div>

                <PriceTag product={product} />
                <SkuMatches skus={matchedSkus} />

                <div className="mt-auto flex items-center justify-between gap-2 border-t border-black/5 pt-2 text-[10px] text-black/50">
                    {product.stock_history && product.stock_history.length > 1 ? (
                        <StockTrend history={product.stock_history} width={56} height={20} />
                    ) : product.spec_type === 'blind_box' ? (
                        <span className="inline-flex items-center gap-1">
                            <Package aria-hidden="true" className="size-3" />
                            Blind box
                        </span>
                    ) : (
                        <span />
                    )}
                    <time dateTime={product.last_seen_at} title="Last checked" className="inline-flex items-center gap-1">
                        <RefreshCw aria-hidden="true" className="size-3" />
                        {formatTimeAgo(product.last_seen_at)}
                    </time>
                </div>
            </div>
        </article>
    );
}

/** The most useful number for the status: a countdown before sale, stock left while selling. */
function StockHint({ product }: { product: ProductCardData }) {
    const status = productStatus(product);

    if (status === 'coming_soon' && product.sale_start_at) {
        return (
            <span className="text-[11px] font-medium text-black">
                in <time dateTime={product.sale_start_at}>{formatCountdown(product.sale_start_at)}</time>
            </span>
        );
    }

    if ((status === 'in_stock' || status === 'low_stock') && product.remain_stock !== null) {
        return (
            <span className={`text-[11px] font-medium ${status === 'low_stock' ? 'text-brand' : 'text-black/60'}`}>
                {product.remain_stock} left
            </span>
        );
    }

    return null;
}

function Badge({ tone, children }: { tone: 'dark' | 'brand'; children: ReactNode }) {
    const tones = { dark: 'bg-black text-white', brand: 'bg-brand text-white' };

    return (
        <span
            className={`inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] leading-none font-bold tracking-wider uppercase ${tones[tone]}`}
        >
            {children}
        </span>
    );
}
