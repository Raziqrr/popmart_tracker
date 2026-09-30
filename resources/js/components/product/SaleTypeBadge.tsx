import { Dices } from 'lucide-react';
import type { ProductCardData } from '@/types/catalog';

/**
 * Marks POP NOW (business_type = draw: the online blind-box draw where you
 * pick a box from a set) so it's never confused with a normal shop listing.
 * Renders nothing for normal shop products. Links straight to that draw on
 * the POP NOW page.
 */
export function SaleTypeBadge({
    product,
    className = '',
    iconOnly = false,
}: {
    product: Pick<ProductCardData, 'business_type' | 'id'>;
    className?: string;
    /** Just the dice icon, for tiles too narrow for the label (e.g. calendar days). */
    iconOnly?: boolean;
}) {
    if (product.business_type !== 'draw') return null;

    return (
        <a
            href={`#/page-pop-now?product=${product.id}`}
            title="POP NOW: online blind-box draw — go to this draw"
            aria-label={iconOnly ? 'POP NOW: go to this draw' : undefined}
            className={`inline-flex shrink-0 items-center gap-1 bg-brand px-1.5 py-0.5 text-[10px] leading-none font-bold tracking-wider whitespace-nowrap text-white uppercase hover:bg-brand/85 ${className}`}
        >
            <Dices aria-hidden="true" className="size-3" />
            {!iconOnly && 'Pop Now'}
        </a>
    );
}
