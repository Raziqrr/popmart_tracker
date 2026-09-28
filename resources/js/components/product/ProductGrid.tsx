import { LayoutGrid, List, Search } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { formatSaleDate } from '@/lib/format';
import { emptyFilters, matchingSkus, passesFilters, type ProductFilters as Filters } from '@/lib/productFilters';
import { countStatuses, productStatus, STATUS_ORDER, statusMeta, type ProductStatus } from '@/lib/productStatus';
import type { ProductCardData, SkuSummary } from '@/types/catalog';
import type { ProductActions } from './actions';
import { ProductCard } from './ProductCard';
import { ProductFilters } from './ProductFilters';
import { ProductTable } from './ProductTable';
import { StatusIcon } from './StatusBadge';

interface ProductGridProps extends ProductActions {
    products: ProductCardData[];
    showSaleTime?: boolean;
    emptyMessage?: ReactNode;
}

/** Responsive grid of product cards, dense enough to scan a whole release at once. */
export function ProductGrid({
    products,
    watchedIds,
    pinnedIds,
    onToggleWatch,
    onTogglePin,
    productHref,
    lockedIds,
    onLockClick,
    skuMatches,
    showSaleTime,
    emptyMessage = 'No products match these filters.',
}: ProductGridProps) {
    if (products.length === 0) {
        return <p className="py-16 text-center text-sm text-black/50">{emptyMessage}</p>;
    }

    return (
        <ul className="grid grid-cols-2 gap-x-3 gap-y-8 sm:grid-cols-3 sm:gap-x-5 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {products.map((product) => (
                <li key={product.id} className="flex">
                    <ProductCard
                        product={product}
                        href={productHref?.(product)}
                        watching={watchedIds?.has(product.id)}
                        pinned={pinnedIds?.has(product.id)}
                        onToggleWatch={onToggleWatch}
                        onTogglePin={onTogglePin}
                        locked={lockedIds?.has(product.id)}
                        onLockClick={onLockClick}
                        matchedSkus={skuMatches?.get(product.id)}
                        showSaleTime={showSaleTime}
                    />
                </li>
            ))}
        </ul>
    );
}

interface ProductSectionProps extends Omit<ProductGridProps, 'showSaleTime'> {
    title: string;
    /** ISO date shown next to the title, e.g. "Coming soon  October 2". */
    date?: string | null;
}

/** A titled release-calendar block, e.g. "Coming soon · October 2", with sale times on each card. */
export function ProductSection({ title, date, ...grid }: ProductSectionProps) {
    return (
        <section className="flex flex-col gap-5">
            <h2 className="flex items-baseline gap-6 text-xl font-bold text-black sm:text-2xl">
                {title}
                {date && <time dateTime={date}>{formatSaleDate(date)}</time>}
            </h2>
            <ProductGrid showSaleTime {...grid} />
        </section>
    );
}

type StatusFilter = 'all' | ProductStatus;
type SortKey = 'recently_changed' | 'stock_asc' | 'opens_soonest' | 'price_asc' | 'price_desc';
export type ProductView = 'grid' | 'list';
type Scope = 'all' | 'pinned' | 'watched';

const sortLabels: Record<SortKey, string> = {
    recently_changed: 'Recently changed',
    stock_asc: 'Lowest stock',
    opens_soonest: 'Opens soonest',
    price_asc: 'Price: low to high',
    price_desc: 'Price: high to low',
};

const scopeLabels: Record<Scope, string> = { all: 'Everything', pinned: 'Pinned', watched: 'Alerts on' };

const statusFilters: StatusFilter[] = ['all', ...STATUS_ORDER];

const byTimeDesc = (a?: string | null, b?: string | null) => (b ?? '').localeCompare(a ?? '');

function sortProducts(products: ProductCardData[], sort: SortKey): ProductCardData[] {
    const sorted = [...products];

    switch (sort) {
        case 'recently_changed':
            return sorted.sort((a, b) => byTimeDesc(a.last_changed_at, b.last_changed_at));
        case 'stock_asc':
            // Unknown stock (masked SPUs) sorts last.
            return sorted.sort((a, b) => (a.remain_stock ?? Infinity) - (b.remain_stock ?? Infinity));
        case 'opens_soonest':
            return sorted.sort((a, b) => (a.sale_start_at ?? '9999').localeCompare(b.sale_start_at ?? '9999'));
        case 'price_asc':
            return sorted.sort((a, b) => a.price - b.price);
        case 'price_desc':
            return sorted.sort((a, b) => b.price - a.price);
    }
}

interface ProductBrowserProps extends Omit<ProductGridProps, 'showSaleTime'> {
    defaultView?: ProductView;
    defaultSort?: SortKey;
    /** Start scoped to pinned or alerted products (the watchlist page). */
    defaultScope?: Scope;
}

/**
 * Search, pinned/alerts scope, IP / type / POP NOW / SKU filters, status
 * filter, sort and grid/list switch over
 * one page of products. Client-side for now; move to query params once the
 * catalog is paginated server-side.
 */
export function ProductBrowser({
    products,
    defaultView = 'grid',
    defaultSort = 'recently_changed',
    defaultScope = 'all',
    ...grid
}: ProductBrowserProps) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState<StatusFilter>('all');
    const [scope, setScope] = useState<Scope>(defaultScope);
    const [sort, setSort] = useState<SortKey>(defaultSort);
    const [view, setView] = useState<ProductView>(defaultView);
    const [filters, setFilters] = useState<Filters>(emptyFilters);

    // Search + scope first: the filter menus count against this set.
    const searched = useMemo(() => {
        const q = query.trim().toLowerCase();
        const inScope = (p: ProductCardData) =>
            scope === 'all' || (scope === 'pinned' ? grid.pinnedIds?.has(p.id) : grid.watchedIds?.has(p.id));

        return products.filter(
            (p) => inScope(p) && (!q || p.name.toLowerCase().includes(q) || p.theme_name?.toLowerCase().includes(q)),
        );
    }, [products, query, scope, grid.pinnedIds, grid.watchedIds]);

    // Then the IP / type / POP NOW / SKU filters; status chips count against this.
    const scoped = useMemo(() => searched.filter((p) => passesFilters(p, filters)), [searched, filters]);

    const skuMatches = useMemo(() => {
        const map = new Map<string, SkuSummary[]>();
        if (filters.sku.trim()) for (const p of scoped) map.set(p.id, matchingSkus(p, filters.sku));
        return map;
    }, [scoped, filters.sku]);

    const counts: Record<StatusFilter, number> = useMemo(() => ({ all: scoped.length, ...countStatuses(scoped) }), [scoped]);

    const visible = useMemo(
        () => sortProducts(status === 'all' ? scoped : scoped.filter((p) => productStatus(p) === status), sort),
        [scoped, status, sort],
    );

    const scopes = (Object.keys(scopeLabels) as Scope[]).filter(
        (key) => key === 'all' || (key === 'pinned' ? grid.pinnedIds : grid.watchedIds),
    );

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-3 border-b border-black/10 pb-4">
                <div className="flex flex-wrap items-center gap-3">
                    <label className="flex min-w-48 grow items-center gap-2 border border-black/20 px-3 py-1.5 focus-within:border-black sm:max-w-xs">
                        <Search aria-hidden="true" className="size-4 text-black/40" />
                        <span className="sr-only">Search products</span>
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search name or theme"
                            className="w-full bg-transparent text-xs outline-none placeholder:text-black/40"
                        />
                    </label>

                    {scopes.length > 1 && (
                        <div role="group" aria-label="Show" className="flex border border-black/20">
                            {scopes.map((key) => (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={scope === key}
                                    onClick={() => setScope(key)}
                                    className="px-3 py-1.5 text-xs font-medium text-black/70 hover:text-black aria-pressed:bg-black aria-pressed:text-white"
                                >
                                    {scopeLabels[key]}
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="ml-auto flex items-center gap-3">
                        <label className="flex items-center gap-2 text-xs font-medium">
                            <span className="hidden sm:inline">Sort</span>
                            <select
                                value={sort}
                                onChange={(e) => setSort(e.target.value as SortKey)}
                                className="border border-black/20 bg-white px-2 py-1.5 text-xs focus-visible:outline-2 focus-visible:outline-brand"
                            >
                                {(Object.keys(sortLabels) as SortKey[]).map((key) => (
                                    <option key={key} value={key}>
                                        {sortLabels[key]}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <ViewSwitch
                            value={view}
                            onChange={setView}
                            options={[
                                { key: 'grid', label: 'Grid view', icon: <LayoutGrid aria-hidden="true" className="size-4" /> },
                                { key: 'list', label: 'List view', icon: <List aria-hidden="true" className="size-4" /> },
                            ]}
                        />
                    </div>
                </div>

                <ProductFilters products={searched} value={filters} onChange={setFilters} />

                <div role="group" aria-label="Filter by status" className="flex flex-wrap gap-2">
                    {statusFilters.map((key) => (
                        <button
                            key={key}
                            type="button"
                            aria-pressed={status === key}
                            onClick={() => setStatus(key)}
                            className="group/chip inline-flex items-center gap-1.5 border border-black/20 px-2.5 py-1 text-xs font-medium transition-colors hover:border-black aria-pressed:border-black aria-pressed:bg-black aria-pressed:text-white"
                        >
                            {key !== 'all' && <StatusIcon status={key} className="size-3.5 group-aria-pressed/chip:text-white" />}
                            {key === 'all' ? 'All' : statusMeta[key].label}
                            <span className="opacity-60">{counts[key]}</span>
                        </button>
                    ))}
                </div>
            </div>

            <p className="text-xs text-black/50" aria-live="polite">
                Showing {visible.length} of {products.length}
            </p>

            {visible.length === 0 ? (
                <p className="py-16 text-center text-sm text-black/50">
                    {scope !== 'all' && scoped.length === 0
                        ? scope === 'pinned'
                            ? 'Nothing pinned yet. Use the pin icon on any product.'
                            : 'No alerts set yet. Use the bell icon on any product.'
                        : 'No products match these filters.'}
                </p>
            ) : view === 'grid' ? (
                <ProductGrid products={visible} {...grid} skuMatches={skuMatches} />
            ) : (
                <ProductTable products={visible} {...grid} skuMatches={skuMatches} />
            )}
        </div>
    );
}

/** Icon segmented control for switching views (grid/list, list/calendar). */
export function ViewSwitch<T extends string>({
    value,
    onChange,
    options,
}: {
    value: T;
    onChange: (value: T) => void;
    options: { key: T; label: string; icon: ReactNode }[];
}) {
    return (
        <div role="group" aria-label="View" className="flex border border-black/20">
            {options.map((option) => (
                <button
                    key={option.key}
                    type="button"
                    aria-pressed={value === option.key}
                    aria-label={option.label}
                    title={option.label}
                    onClick={() => onChange(option.key)}
                    className="grid size-8 place-items-center text-black/60 hover:text-black aria-pressed:bg-black aria-pressed:text-white"
                >
                    {option.icon}
                </button>
            ))}
        </div>
    );
}
