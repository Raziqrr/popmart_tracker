import { Flame, Info } from 'lucide-react';
import { LockToggle } from '@/components/lock/LockToggle';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import { PinToggle } from '@/components/product/WatchToggle';
import type { ProductActions } from '@/components/product/actions';
import type { ProductCardData } from '@/types/catalog';

interface FastSellersProps extends Pick<ProductActions, 'pinnedIds' | 'onTogglePin' | 'productHref'> {
    products: ProductCardData[];
    limit?: number;
}

/**
 * Products ranked by estimated sell rate (units/hour). The rate is a
 * placeholder until the velocity logic exists: docs/tickets/fast-selling-products.md.
 */
export function FastSellers({ products, limit = 5, ...actions }: FastSellersProps) {
    const ranked = products
        .filter((p) => (p.sell_rate_per_hour ?? 0) > 0)
        .sort((a, b) => b.sell_rate_per_hour! - a.sell_rate_per_hour!)
        .slice(0, limit);
    const top = ranked[0]?.sell_rate_per_hour ?? 1;

    return (
        <div className="flex flex-col gap-3">
            <p className="flex items-start gap-1.5 bg-tile px-2 py-1.5 text-[11px] text-black/60">
                <Info aria-hidden="true" className="mt-px size-3.5 shrink-0" />
                Estimated from stock drops between checks. Needs backend logic before it's accurate.
            </p>

            {ranked.length === 0 ? (
                <p className="py-6 text-center text-sm text-black/50">Nothing selling fast right now.</p>
            ) : (
                <ol className="flex flex-col gap-2">
                    {ranked.map((product, index) => {
                        const href = actions.productHref?.(product);
                        const rate = product.sell_rate_per_hour!;

                        return (
                            <li key={product.id} className="flex items-center gap-3">
                                <span className="w-4 shrink-0 text-center text-xs font-bold text-black/40 tabular-nums">{index + 1}</span>
                                <div className="size-10 shrink-0 bg-tile">
                                    {product.image_url && (
                                        <img src={product.image_url} alt="" loading="lazy" className="size-full object-contain p-1" />
                                    )}
                                </div>
                                <div className="min-w-0 grow">
                                    {href ? (
                                        <a href={href} className="line-clamp-1 text-xs font-medium hover:underline">
                                            {product.name}
                                        </a>
                                    ) : (
                                        <p className="line-clamp-1 text-xs font-medium">{product.name}</p>
                                    )}
                                    <div className="mt-1 flex items-center gap-2">
                                        {/* Single-series magnitude bar, ink colour, 4px rounded data-end. */}
                                        <span className="h-1.5 grow bg-tile">
                                            <span className="block h-full rounded-r-sm bg-black" style={{ width: `${(rate / top) * 100}%` }} />
                                        </span>
                                        <span className="inline-flex shrink-0 items-center gap-0.5 text-[11px] font-bold tabular-nums">
                                            <Flame aria-hidden="true" className="size-3.5 text-brand" />
                                            {rate}/hr
                                        </span>
                                    </div>
                                    <SaleTypeBadge product={product} className="mt-1" />
                                    {product.remain_stock !== null && (
                                        <p className="text-[10px] text-black/50">
                                            {product.remain_stock} left · ~{Math.max(1, Math.round(product.remain_stock / rate))}h to sell out
                                        </p>
                                    )}
                                </div>
                                <LockToggle product={product} />
                                <PinToggle product={product} active={actions.pinnedIds?.has(product.id) ?? false} onToggle={actions.onTogglePin} />
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}
