import { LockToggle } from '@/components/lock/LockToggle';
import { WatchToggle } from '@/components/product/WatchToggle';
import { formatCountdown, formatMoney, formatShortDateTime } from '@/lib/format';
import { useNow } from '@/lib/useNow';
import type { ProductCardData } from '@/types/catalog';

interface UpcomingDropsProps {
    products: ProductCardData[];
    watchedIds?: ReadonlySet<string>;
    onToggleWatch?: (product: ProductCardData) => void;
    productHref?: (product: ProductCardData) => string;
}

/** Soonest-first list of coming-soon products with a live countdown and reminder toggle. */
export function UpcomingDrops({ products, watchedIds, onToggleWatch, productHref }: UpcomingDropsProps) {
    const now = useNow(30_000);
    const upcoming = products
        .filter((p) => p.is_coming_soon && p.sale_start_at)
        .sort((a, b) => a.sale_start_at!.localeCompare(b.sale_start_at!));

    if (upcoming.length === 0) {
        return <p className="py-6 text-center text-sm text-black/50">No announced drops.</p>;
    }

    return (
        <ol className="divide-y divide-black/10 border-y border-black/10">
            {upcoming.map((product) => {
                const href = productHref?.(product);

                return (
                    <li key={product.id} className="flex items-center gap-3 py-2.5">
                        <div className="flex w-14 shrink-0 flex-col items-center bg-black py-1.5 text-white">
                            <span className="text-[9px] font-medium tracking-wider uppercase opacity-70">in</span>
                            <span className="text-xs leading-tight font-bold tabular-nums">
                                {formatCountdown(product.sale_start_at!, now)}
                            </span>
                        </div>
                        <div className="min-w-0 grow">
                            {href ? (
                                <a href={href} className="line-clamp-1 text-xs font-medium hover:underline">
                                    {product.name}
                                </a>
                            ) : (
                                <p className="line-clamp-1 text-xs font-medium">{product.name}</p>
                            )}
                            <p className="text-[11px] text-black/60">
                                <time dateTime={product.sale_start_at!}>{formatShortDateTime(product.sale_start_at!)}</time>
                                {' · '}
                                <span className="text-brand">{formatMoney(product.price, product.currency)}</span>
                            </p>
                        </div>
                        <LockToggle product={product} />
                        <WatchToggle product={product} active={watchedIds?.has(product.id) ?? false} onToggle={onToggleWatch} />
                    </li>
                );
            })}
        </ol>
    );
}

