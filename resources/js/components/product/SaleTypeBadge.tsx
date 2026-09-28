import { Dices } from 'lucide-react';
import type { ProductCardData } from '@/types/catalog';

/**
 * Marks POP NOW (business_type = draw: the online blind-box draw where you
 * pick a box from a set) so it's never confused with a normal shop listing.
 * Renders nothing for normal shop products. Links straight to that draw on
 * the POP NOW page.
 */
export function SaleTypeBadge({ product, className = '' }: { product: Pick<ProductCardData, 'business_type' | 'id'>; className?: string }) {
    if (product.business_type !== 'draw') return null;

    return (
        <a
            href={`#/page-pop-now?product=${product.id}`}
            title="POP NOW: online blind-box draw — go to this draw"
            className={`inline-flex shrink-0 items-center gap-1 bg-brand px-1.5 py-0.5 text-[10px] leading-none font-bold tracking-wider whitespace-nowrap text-white uppercase hover:bg-brand/85 ${className}`}
        >
            <Dices aria-hidden="true" className="size-3" />
            Pop Now
        </a>
    );
}
