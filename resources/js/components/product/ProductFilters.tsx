import { Check, ChevronDown, Dices, Layers, ScanBarcode, Shapes, Store, X, type LucideIcon } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { activeFilterCount, emptyFilters, facet, ipOf, typeOf, type ProductFilters as Filters, type SaleTypeFilter } from '@/lib/productFilters';
import type { ProductCardData } from '@/types/catalog';

interface ProductFiltersProps {
    /** Products the facet counts are based on (already scoped by search/pinned). */
    products: ProductCardData[];
    value: Filters;
    onChange: (filters: Filters) => void;
}

/**
 * IP, type, POP NOW and SKU filters, plus removable chips for whatever is
 * active. IP and type are multi-select dropdowns with counts.
 */
export function ProductFilters({ products, value, onChange }: ProductFiltersProps) {
    const set = (patch: Partial<Filters>) => onChange({ ...value, ...patch });
    const toggle = (list: string[], item: string) => (list.includes(item) ? list.filter((v) => v !== item) : [...list, item]);
    const active = activeFilterCount(value);

    const saleTypes: { key: SaleTypeFilter; label: string; Icon?: LucideIcon }[] = [
        { key: 'all', label: 'Any' },
        { key: 'draw', label: 'POP NOW', Icon: Dices },
        { key: 'shop', label: 'Shop', Icon: Store },
    ];

    const chips = [
        ...value.ips.map((ip) => ({ key: `ip-${ip}`, label: `IP: ${ip}`, remove: () => set({ ips: value.ips.filter((v) => v !== ip) }) })),
        ...value.types.map((t) => ({ key: `type-${t}`, label: `Type: ${t}`, remove: () => set({ types: value.types.filter((v) => v !== t) }) })),
        ...(value.saleType !== 'all'
            ? [{ key: 'sale', label: value.saleType === 'draw' ? 'POP NOW only' : 'Shop only', remove: () => set({ saleType: 'all' }) }]
            : []),
        ...(value.sku.trim() ? [{ key: 'sku', label: `SKU: “${value.sku.trim()}”`, remove: () => set({ sku: '' }) }] : []),
    ];

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <FilterMenu
                    label="IP"
                    icon={Layers}
                    options={facet(products, ipOf)}
                    selected={value.ips}
                    onToggle={(ip) => set({ ips: toggle(value.ips, ip) })}
                    onClear={() => set({ ips: [] })}
                />
                <FilterMenu
                    label="Type"
                    icon={Shapes}
                    options={facet(products, typeOf)}
                    selected={value.types}
                    onToggle={(t) => set({ types: toggle(value.types, t) })}
                    onClear={() => set({ types: [] })}
                />

                <div role="radiogroup" aria-label="POP NOW" className="flex border border-black/20">
                    {saleTypes.map(({ key, label, Icon }) => (
                        <button
                            key={key}
                            type="button"
                            role="radio"
                            aria-checked={value.saleType === key}
                            onClick={() => set({ saleType: key })}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-black/70 hover:text-black aria-checked:bg-black aria-checked:text-white"
                        >
                            {Icon && <Icon aria-hidden="true" className="size-3.5" />}
                            {label}
                        </button>
                    ))}
                </div>

                <label className="flex min-w-52 items-center gap-2 border border-black/20 px-2.5 py-1.5 focus-within:border-black">
                    <ScanBarcode aria-hidden="true" className="size-4 text-black/40" />
                    <span className="sr-only">Figure, SKU code or barcode</span>
                    <input
                        type="search"
                        value={value.sku}
                        onChange={(e) => set({ sku: e.target.value })}
                        placeholder="Figure, SKU or barcode"
                        className="w-full bg-transparent text-xs outline-none placeholder:text-black/40"
                    />
                </label>
            </div>

            {active > 0 && (
                <ul aria-label="Active filters" className="flex flex-wrap items-center gap-1.5">
                    {chips.map((chip) => (
                        <li key={chip.key}>
                            <button
                                type="button"
                                onClick={chip.remove}
                                aria-label={`Remove filter ${chip.label}`}
                                className="inline-flex items-center gap-1 bg-black px-2 py-1 text-[11px] font-medium text-white hover:bg-brand"
                            >
                                {chip.label}
                                <X aria-hidden="true" className="size-3" />
                            </button>
                        </li>
                    ))}
                    <li>
                        <button type="button" onClick={() => onChange(emptyFilters)} className="px-1 text-[11px] font-bold underline underline-offset-2">
                            Clear all
                        </button>
                    </li>
                </ul>
            )}
        </div>
    );
}

interface FilterMenuProps {
    label: string;
    icon: LucideIcon;
    options: { value: string; count: number }[];
    selected: string[];
    onToggle: (value: string) => void;
    onClear: () => void;
}

/** Dropdown with a checkbox per option and its count. Closes on outside click or Escape. */
function FilterMenu({ label, icon: Icon, options, selected, onToggle, onClear }: FilterMenuProps) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const panelId = useId();

    useEffect(() => {
        if (!open) return;
        const onPointer = (e: PointerEvent) => root.current && !root.current.contains(e.target as Node) && setOpen(false);
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                aria-expanded={open}
                aria-controls={panelId}
                onClick={() => setOpen((o) => !o)}
                className={`inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-medium ${
                    selected.length ? 'border-black bg-black text-white' : 'border-black/20 hover:border-black'
                }`}
            >
                <Icon aria-hidden="true" className="size-3.5" />
                {label}
                {selected.length > 0 && <span className="bg-white px-1 text-[10px] font-bold text-black tabular-nums">{selected.length}</span>}
                <ChevronDown aria-hidden="true" className={`size-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div id={panelId} className="absolute top-full left-0 z-30 mt-1 w-60 border border-black bg-white shadow-lg">
                    <ul className="max-h-64 overflow-y-auto py-1" aria-label={`${label} options`}>
                        {options.map((option) => {
                            const checked = selected.includes(option.value);
                            return (
                                <li key={option.value}>
                                    <label className="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-xs hover:bg-tile">
                                        <input type="checkbox" checked={checked} onChange={() => onToggle(option.value)} className="sr-only" />
                                        <span
                                            aria-hidden="true"
                                            className={`grid size-4 shrink-0 place-items-center border ${checked ? 'border-black bg-black text-white' : 'border-black/30'}`}
                                        >
                                            {checked && <Check className="size-3" strokeWidth={3} />}
                                        </span>
                                        <span className="grow truncate font-medium">{option.value}</span>
                                        <span className="text-black/50 tabular-nums">{option.count}</span>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                    {selected.length > 0 && (
                        <button type="button" onClick={onClear} className="w-full border-t border-black/10 px-3 py-2 text-left text-xs font-bold hover:bg-tile">
                            Clear {label}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
