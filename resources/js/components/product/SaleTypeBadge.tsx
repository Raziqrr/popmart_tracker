import { Dices } from 'lucide-react';
import type { ProductCardData } from '@/types/catalog';

/**
 * Marks POP NOW (business_type = draw: the online blind-box draw where you
 * pick a box from a set) so it's never confused with a normal shop listing.
 * Renders nothing for normal shop products.
 */
export function SaleTypeBadge({ product, className = '' }: { product: Pick<ProductCardData, 'business_type'>; className?: string }) {
    if (product.business_type !== 'draw') return null;

    return (
        <span
            title="POP NOW: online blind-box draw"
            className={`inline-flex shrink-0 items-center gap-1 bg-brand px-1.5 py-0.5 text-[10px] leading-none font-bold tracking-wider whitespace-nowrap text-white uppercase ${className}`}
        >
            <Dices aria-hidden="true" className="size-3" />
            Pop Now
        </span>
    );
}
