import { Lock, LockOpen } from 'lucide-react';
import { useActivation } from '@/lib/useActivation';
import type { ProductCardData } from '@/types/catalog';
import { useAutoLockApi } from './AutoLockContext';

interface LockToggleProps {
    product: ProductCardData;
    /** An enabled auto-lock rule exists. Defaults to the site-wide auto-lock state. */
    active?: boolean;
    /** Opens auto-lock setup. Defaults to the site-wide quick setup. */
    onClick?: (product: ProductCardData) => void;
    /** md = 32px (cards, rows); sm = 20px (calendar/timeline tiles). */
    size?: 'md' | 'sm';
    className?: string;
}

/**
 * Auto-lock entry point, only for POP NOW (business_type = draw) products.
 * Works anywhere inside the AutoLockContext provider with no props beyond the
 * product. Amber when a rule is on: it acts on the user's Pop Mart account.
 */
export function LockToggle({ product, active, onClick, size = 'md', className = '' }: LockToggleProps) {
    const api = useAutoLockApi();
    const isActive = active ?? api?.lockedIds.has(product.id) ?? false;
    const handler = onClick ?? api?.open;
    const activations = useActivation(isActive);

    if (product.business_type !== 'draw' || !handler) return null;

    const label = isActive ? 'Auto-lock on: edit' : 'Set up auto-lock';
    const Icon = isActive ? Lock : LockOpen;

    return (
        <button
            type="button"
            onClick={(e) => {
                // Tiles use double-click and stretched links; keep the click on the button.
                e.stopPropagation();
                handler(product);
            }}
            onDoubleClick={(e) => e.stopPropagation()}
            aria-pressed={isActive}
            aria-haspopup="dialog"
            aria-label={`${label}: ${product.name}`}
            title={label}
            className={`relative z-10 inline-flex shrink-0 items-center justify-center border transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand ${
                size === 'sm' ? 'size-5' : 'size-8'
            } ${
                isActive
                    ? 'border-status-warning-ink bg-status-warning-tint text-status-warning-ink hover:bg-status-warning/30'
                    : 'border-black/20 bg-white text-black hover:border-black'
            } ${className}`}
        >
            {activations > 0 && (
                <span
                    key={`ping-${activations}`}
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 bg-status-warning motion-safe:animate-ping-once"
                />
            )}
            <Icon
                key={activations}
                aria-hidden="true"
                strokeWidth={2.25}
                className={`${size === 'sm' ? 'size-3.5' : 'size-4'} ${activations ? 'motion-safe:animate-lock-snap' : ''}`}
            />
        </button>
    );
}
