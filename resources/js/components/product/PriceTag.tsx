import { TrendingDown, TrendingUp } from 'lucide-react';
import { formatMoney } from '@/lib/format';
import type { ProductCardData } from '@/types/catalog';

/** Current price, plus the previous price and direction when it changed. */
export function PriceTag({ product, className = '' }: { product: ProductCardData; className?: string }) {
    const previous = product.previous_price;
    const changed = previous !== undefined && previous !== null && previous !== product.price;
    const dropped = changed && product.price < previous;

    return (
        <span className={`inline-flex flex-wrap items-center gap-x-1.5 ${className}`}>
            <span className="text-sm font-medium text-brand">{formatMoney(product.price, product.currency)}</span>
            {changed && (
                <>
                    <s className="text-[11px] text-black/40">{formatMoney(previous, product.currency)}</s>
                    <span
                        className={`inline-flex items-center gap-0.5 text-[11px] font-bold ${dropped ? 'text-good-text' : 'text-black/60'}`}
                        title={dropped ? 'Price dropped' : 'Price went up'}
                    >
                        {dropped ? <TrendingDown aria-hidden="true" className="size-3.5" /> : <TrendingUp aria-hidden="true" className="size-3.5" />}
                        {dropped ? 'Drop' : 'Up'}
                    </span>
                </>
            )}
        </span>
    );
}
