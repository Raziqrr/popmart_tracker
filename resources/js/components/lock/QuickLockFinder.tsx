import { ChevronRight, Lock, Search, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { StatusBadge } from '@/components/product/StatusBadge';
import { formatCountdown } from '@/lib/format';
import { productStatus } from '@/lib/productStatus';
import type { ProductCardData } from '@/types/catalog';

interface QuickLockFinderProps {
    open: boolean;
    /** POP NOW products the user can lock. */
    products: ProductCardData[];
    lockedIds: ReadonlySet<string>;
    onPick: (product: ProductCardData) => void;
    onClose: () => void;
}

/**
 * Search-first auto-lock launcher, reachable from every page (header button
 * or the L shortcut). Coming-soon draws first, soonest first, then the rest.
 */
export function QuickLockFinder({ open, products, lockedIds, onPick, onClose }: QuickLockFinderProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    const [query, setQuery] = useState('');

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        const rank = (p: ProductCardData) => (p.is_coming_soon ? 0 : p.is_sold_out ? 2 : 1);

        return products
            .filter((p) => !q || p.name.toLowerCase().includes(q) || p.theme_name?.toLowerCase().includes(q))
            .sort((a, b) => rank(a) - rank(b) || (a.sale_start_at ?? '').localeCompare(b.sale_start_at ?? ''));
    }, [products, query]);

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby={titleId}
            onClose={onClose}
            onClick={(e) => e.target === dialogRef.current && onClose()}
            className="mx-auto mt-[12vh] w-[min(32rem,calc(100vw-2rem))] bg-white p-0 text-black backdrop:bg-black/50"
        >
            <div className="flex items-center gap-2 border-b border-black/10 px-4 py-3">
                <Lock aria-hidden="true" className="size-4" />
                <h2 id={titleId} className="grow text-sm font-bold">
                    Auto-lock a POP NOW draw
                </h2>
                <button type="button" onClick={onClose} aria-label="Close" className="grid size-7 place-items-center hover:bg-tile">
                    <X aria-hidden="true" className="size-4" />
                </button>
            </div>

            <label className="flex items-center gap-2 border-b border-black/10 px-4 py-2.5">
                <Search aria-hidden="true" className="size-4 text-black/40" />
                <span className="sr-only">Search POP NOW products</span>
                <input
                    autoFocus
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && results[0] && onPick(results[0])}
                    placeholder="Search name or IP…"
                    className="w-full bg-transparent text-sm outline-none placeholder:text-black/40"
                />
            </label>

            <ul className="max-h-[50vh] overflow-y-auto py-1" aria-label="POP NOW products">
                {results.length === 0 && <li className="px-4 py-8 text-center text-sm text-black/50">No POP NOW products match.</li>}
                {results.map((product) => (
                    <li key={product.id}>
                        <button type="button" onClick={() => onPick(product)} className="flex w-full items-center gap-3 px-4 py-2 text-left hover:bg-tile focus-visible:bg-tile">
                            <span className="size-10 shrink-0 bg-tile">
                                {product.image_url && <img src={product.image_url} alt="" className="size-full object-contain p-1" />}
                            </span>
                            <span className="flex min-w-0 grow flex-col gap-0.5">
                                <span className="line-clamp-1 text-xs font-bold">{product.name}</span>
                                <span className="flex flex-wrap items-center gap-2 text-[11px] text-black/60">
                                    <StatusBadge status={productStatus(product)} size="xs" />
                                    {product.is_coming_soon && product.sale_start_at && <span>opens in {formatCountdown(product.sale_start_at)}</span>}
                                    {lockedIds.has(product.id) && (
                                        <span className="inline-flex items-center gap-1 bg-status-warning-tint px-1 font-bold text-status-warning-ink">
                                            <Lock aria-hidden="true" className="size-3" />
                                            Rule on
                                        </span>
                                    )}
                                </span>
                            </span>
                            <ChevronRight aria-hidden="true" className="size-4 shrink-0 text-black/40" />
                        </button>
                    </li>
                ))}
            </ul>

            <p className="border-t border-black/10 px-4 py-2 text-[11px] text-black/50">
                Tip: press <kbd className="border border-black/20 px-1 font-mono">L</kbd> anywhere to open this.
            </p>
        </dialog>
    );
}
