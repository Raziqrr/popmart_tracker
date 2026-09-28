import { Bell, BellRing, Pin, PinOff } from 'lucide-react';
import { useState } from 'react';
import { productStatus } from '@/lib/productStatus';
import { useActivation } from '@/lib/useActivation';
import type { ProductCardData } from '@/types/catalog';

/** Short name of the alert a product gets, by status: what the bell will notify you about. */
export function alertKind(product: ProductCardData): string {
    const status = productStatus(product);

    if (status === 'coming_soon') return 'Sale reminder';
    if (status === 'sold_out') return 'Restock alert';
    return 'Price & stock alert';
}

/** Button label: the action when off, the current state when on. */
export function watchLabel(product: ProductCardData, watching: boolean): string {
    const status = productStatus(product);

    if (watching) return `${alertKind(product)} on`;
    if (status === 'coming_soon') return 'Remind me when on sale';
    if (status === 'sold_out') return 'Alert me on restock';
    return 'Alert me on price & stock changes';
}

const toggleBase =
    'relative z-10 inline-flex size-8 shrink-0 items-center justify-center border transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';

/** Expanding ring behind a toggle the moment it switches on. */
function ActivationPing({ count, className }: { count: number; className: string }) {
    if (count === 0) return null;
    return <span key={count} aria-hidden="true" className={`pointer-events-none absolute inset-0 motion-safe:animate-ping-once ${className}`} />;
}

interface ToggleProps {
    product: ProductCardData;
    active: boolean;
    onToggle?: (product: ProductCardData) => void;
    className?: string;
}

/** Alerts for this product (wishlist_items). Brand red when on; the bell rings as it switches on. */
export function WatchToggle({ product, active, onToggle, className = '' }: ToggleProps) {
    const label = watchLabel(product, active);
    const activations = useActivation(active);
    const Icon = active ? BellRing : Bell;

    return (
        <button
            type="button"
            onClick={() => onToggle?.(product)}
            aria-pressed={active}
            aria-label={`${label}: ${product.name}`}
            title={label}
            className={`${toggleBase} ${
                active ? 'border-brand bg-brand text-white hover:bg-brand/85' : 'border-black/20 bg-white text-black hover:border-black'
            } ${className}`}
        >
            <ActivationPing count={activations} className="bg-brand" />
            <Icon
                key={activations}
                aria-hidden="true"
                fill={active ? 'currentColor' : 'none'}
                className={`size-4 origin-top ${activations ? 'motion-safe:animate-bell-ring' : ''}`}
            />
        </button>
    );
}

/** Keep this product on the dashboard and watchlist (pinned_items). The pin drops in as it switches on. */
export function PinToggle({ product, active, onToggle, className = '' }: ToggleProps) {
    const [hover, setHover] = useState(false);
    const activations = useActivation(active);
    const label = active ? 'Unpin from watchlist' : 'Pin to watchlist';
    const Icon = active && hover ? PinOff : Pin;

    return (
        <button
            type="button"
            onClick={() => onToggle?.(product)}
            onPointerEnter={() => setHover(true)}
            onPointerLeave={() => setHover(false)}
            aria-pressed={active}
            aria-label={`${label}: ${product.name}`}
            title={label}
            className={`${toggleBase} ${
                active ? 'border-black bg-black text-white hover:bg-black/80' : 'border-black/20 bg-white text-black hover:border-black'
            } ${className}`}
        >
            <ActivationPing count={activations} className="bg-black" />
            <Icon
                key={activations}
                aria-hidden="true"
                className={`size-4 ${active ? 'fill-current' : ''} ${activations ? 'motion-safe:animate-pin-drop' : ''}`}
            />
        </button>
    );
}
