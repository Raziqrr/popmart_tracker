import { formatCountdown, formatTimeAgo } from '@/lib/format';
import { productStatus } from '@/lib/productStatus';
import type { ProductCardData, SkuSummary } from '@/types/catalog';
import type { ProductActions } from './actions';
import { LockToggle } from '@/components/lock/LockToggle';
import { ChangeSummary } from './ChangeSummary';
import { PriceTag } from './PriceTag';
import { SaleTypeBadge } from './SaleTypeBadge';
import { SkuMatches } from './SkuMatches';
import { StatusBadge } from './StatusBadge';
import { StockTrend } from './StockTrend';
import { PinToggle, WatchToggle } from './WatchToggle';

interface ProductTableProps extends ProductActions {
    products: ProductCardData[];
    caption?: string;
    /** Hide columns that don't matter in tight spots (e.g. the dashboard). */
    compact?: boolean;
}

/**
 * Dense list view for scanning many products: a table on md+, compact rows on
 * phones. Same data as the card, one line per product.
 */
export function ProductTable({ products, caption = 'Products', compact = false, ...actions }: ProductTableProps) {
    const { watchedIds, pinnedIds, onToggleWatch, onTogglePin, productHref, lockedIds, onLockClick, skuMatches } = actions;

    const toggles = (product: ProductCardData) => (
        <div className="flex justify-end gap-1">
            <PinToggle product={product} active={pinnedIds?.has(product.id) ?? false} onToggle={onTogglePin} />
            <WatchToggle product={product} active={watchedIds?.has(product.id) ?? false} onToggle={onToggleWatch} />
            <LockToggle product={product} active={lockedIds?.has(product.id)} onClick={onLockClick} />
        </div>
    );

    return (
        <>
            <table className="hidden w-full border-collapse text-left text-xs md:table">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr className="border-b border-black text-[10px] tracking-wider text-black/60 uppercase">
                        <th scope="col" className="py-2 pr-4 font-bold">Product</th>
                        <th scope="col" className="px-3 py-2 font-bold">Status</th>
                        <th scope="col" className="px-3 py-2 font-bold">Stock</th>
                        <th scope="col" className="px-3 py-2 font-bold">Price</th>
                        <th scope="col" className="px-3 py-2 font-bold">Last change</th>
                        {!compact && <th scope="col" className="px-3 py-2 font-bold">Checked</th>}
                        <th scope="col" className="py-2 pl-3 text-right font-bold">
                            <span className="sr-only">Pin, alerts and auto-lock</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {products.map((product) => (
                        <tr key={product.id} className="border-b border-black/10 hover:bg-tile/60">
                            <td className="py-2 pr-4">
                                <ProductIdentity product={product} href={productHref?.(product)} matched={skuMatches?.get(product.id)} />
                            </td>
                            <td className="px-3 py-2">
                                <StatusBadge status={productStatus(product)} size="xs" />
                            </td>
                            <td className="px-3 py-2">
                                <StockCell product={product} />
                            </td>
                            <td className="px-3 py-2">
                                <PriceTag product={product} />
                            </td>
                            <td className="px-3 py-2">
                                <ChangeSummary product={product} />
                            </td>
                            {!compact && (
                                <td className="px-3 py-2 whitespace-nowrap text-black/60">{formatTimeAgo(product.last_seen_at)}</td>
                            )}
                            <td className="py-2 pl-3">{toggles(product)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <ul className="divide-y divide-black/10 border-y border-black/10 md:hidden" aria-label={caption}>
                {products.map((product) => (
                    <li key={product.id} className="flex items-center gap-3 py-3">
                        <div className="min-w-0 grow">
                            <ProductIdentity product={product} href={productHref?.(product)} matched={skuMatches?.get(product.id)} />
                            <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 pl-13">
                                <StatusBadge status={productStatus(product)} size="xs" />
                                <StockCell product={product} compact />
                                <PriceTag product={product} />
                            </div>
                            <div className="mt-1 pl-13">
                                <ChangeSummary product={product} />
                            </div>
                        </div>
                        {toggles(product)}
                    </li>
                ))}
            </ul>
        </>
    );
}

function ProductIdentity({ product, href, matched }: { product: ProductCardData; href?: string; matched?: SkuSummary[] }) {
    const Title = href ? 'a' : 'span';

    return (
        <div className="flex min-w-0 items-center gap-3">
            <div className="size-10 shrink-0 bg-tile">
                {product.image_url && (
                    <img
                        src={product.image_url}
                        alt=""
                        loading="lazy"
                        className={`size-full object-contain p-1 ${product.is_sold_out ? 'opacity-50 grayscale' : ''}`}
                    />
                )}
            </div>
            <div className="min-w-0">
                <p className="flex items-center gap-2 text-[10px] font-medium tracking-wide text-black/50 uppercase">
                    <SaleTypeBadge product={product} />
                    {product.theme_name && <span className="truncate">{product.theme_name}</span>}
                </p>
                <Title {...(href ? { href } : {})} className="line-clamp-1 font-medium text-black hover:underline">
                    {product.name}
                </Title>
                <SkuMatches skus={matched} />
            </div>
        </div>
    );
}

function StockCell({ product, compact = false }: { product: ProductCardData; compact?: boolean }) {
    const status = productStatus(product);

    if (status === 'coming_soon' && product.sale_start_at) {
        return <span className="whitespace-nowrap text-black/70">Opens in {formatCountdown(product.sale_start_at)}</span>;
    }

    return (
        <span className="inline-flex items-center gap-2 whitespace-nowrap">
            <span className={status === 'low_stock' ? 'font-bold text-brand' : 'text-black/70'}>
                {product.remain_stock === null ? (status === 'sold_out' ? '0' : 'Hidden') : product.remain_stock}
                {compact && product.remain_stock !== null && ' left'}
            </span>
            {!compact && product.stock_history && product.stock_history.length > 1 && (
                <StockTrend history={product.stock_history} />
            )}
        </span>
    );
}
