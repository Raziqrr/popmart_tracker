import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import type { ProductCardData } from '@/types/catalog';
import { dayKey, groupByDay, WEEKDAYS, weekdayLabel } from './dates';
import { DropGestureHint, DropTile } from './DropTile';

/** Tiles show this many products, then "+N more". */
const TILE_LIMIT = 3;

/** 6x7 grid of dates covering the month, weeks starting Monday. */
function monthGrid(year: number, month: number): Date[] {
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7; // Monday = 0
    return Array.from({ length: 42 }, (_, i) => new Date(year, month, 1 - offset + i));
}

export interface DropViewProps {
    products: ProductCardData[];
    productHref?: (product: ProductCardData) => string;
    /** Products with a reminder/alert on (wishlist_items). */
    watchedIds?: ReadonlySet<string>;
    /** Double-clicking a drop (or its bell) calls this to toggle the reminder. */
    onToggleWatch?: (product: ProductCardData) => void;
}

interface ReleaseCalendarProps extends DropViewProps {
    /** Month to open on; defaults to the current month. */
    initialMonth?: Date;
}

/**
 * Month calendar of release dates (sale_start_at). Each day tile shows the
 * product picture, name and time. On phones it collapses to an agenda list,
 * since seven columns of pictures don't fit.
 */
export function ReleaseCalendar({ products, productHref, watchedIds, onToggleWatch, initialMonth }: ReleaseCalendarProps) {
    const [cursor, setCursor] = useState(() => {
        const start = initialMonth ?? new Date();
        return new Date(start.getFullYear(), start.getMonth(), 1);
    });
    const [expandedDay, setExpandedDay] = useState<string | null>(null);

    const byDay = useMemo(() => groupByDay(products), [products]);

    const days = monthGrid(cursor.getFullYear(), cursor.getMonth());
    const today = dayKey(new Date());
    const monthLabel = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(cursor);
    const goTo = (month: Date) => {
        setExpandedDay(null);
        setCursor(new Date(month.getFullYear(), month.getMonth(), 1));
    };

    const agenda = days.filter((d) => d.getMonth() === cursor.getMonth() && byDay.has(dayKey(d)));

    const tile = (product: ProductCardData) => (
        <DropTile
            key={product.id}
            product={product}
            href={productHref?.(product)}
            watching={watchedIds?.has(product.id) ?? false}
            onToggleWatch={onToggleWatch}
        />
    );

    return (
        <section aria-label={`Release calendar, ${monthLabel}`} className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-xl font-bold sm:text-2xl">{monthLabel}</h2>
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => goTo(new Date())}
                        className="border border-black/20 px-3 py-1.5 text-xs font-medium hover:border-black"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        onClick={() => goTo(new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1))}
                        aria-label="Previous month"
                        className="grid size-8 place-items-center border border-black/20 hover:border-black"
                    >
                        <ChevronLeft aria-hidden="true" className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => goTo(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1))}
                        aria-label="Next month"
                        className="grid size-8 place-items-center border border-black/20 hover:border-black"
                    >
                        <ChevronRight aria-hidden="true" className="size-4" />
                    </button>
                </div>
            </div>

            {onToggleWatch && <DropGestureHint />}

            {/* Month grid, md and up */}
            <div className="hidden md:block">
                <div className="grid grid-cols-7 border-b border-black text-[10px] font-bold tracking-wider text-black/60 uppercase">
                    {WEEKDAYS.map((day) => (
                        <div key={day} className="px-2 py-2">
                            {day}
                        </div>
                    ))}
                </div>
                <ol className="grid grid-cols-7 border-l border-black/10">
                    {days.map((date) => {
                        const key = dayKey(date);
                        const drops = byDay.get(key) ?? [];
                        const inMonth = date.getMonth() === cursor.getMonth();
                        const isToday = key === today;
                        const expanded = expandedDay === key;
                        const shown = expanded ? drops : drops.slice(0, TILE_LIMIT);

                        return (
                            <li
                                key={key}
                                aria-current={isToday ? 'date' : undefined}
                                className={`flex min-h-36 min-w-0 flex-col gap-1.5 border-r border-b border-black/10 p-1.5 ${
                                    isToday ? 'bg-brand/5' : inMonth ? 'bg-white' : 'bg-tile/60'
                                }`}
                            >
                                <DayHeader date={date} isToday={isToday} muted={!inMonth} />
                                {shown.map(tile)}
                                {drops.length > TILE_LIMIT && (
                                    <button
                                        type="button"
                                        onClick={() => setExpandedDay(expanded ? null : key)}
                                        className="mt-auto text-left text-[10px] font-bold text-black/60 hover:text-black"
                                    >
                                        {expanded ? 'Show less' : `+${drops.length - TILE_LIMIT} more`}
                                    </button>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </div>

            {/* Agenda list, phones */}
            <ol className="flex flex-col gap-4 md:hidden">
                {agenda.length === 0 && <li className="py-10 text-center text-sm text-black/50">No drops this month.</li>}
                {agenda.map((date) => {
                    const key = dayKey(date);

                    return (
                        <li key={key} className="flex gap-3">
                            <time
                                dateTime={key}
                                className={`flex w-12 shrink-0 flex-col items-center py-1 text-white ${key === today ? 'bg-brand' : 'bg-black'}`}
                            >
                                <span className="text-[10px] uppercase opacity-70">{weekdayLabel(date)}</span>
                                <span className="text-lg leading-tight font-bold">{date.getDate()}</span>
                            </time>
                            <div className="flex min-w-0 grow flex-col gap-2">{byDay.get(key)!.map(tile)}</div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/** Day number; today becomes a full-width red bar across the top of the box. */
export function DayHeader({ date, isToday, muted = false, children }: { date: Date; isToday: boolean; muted?: boolean; children?: ReactNode }) {
    const key = dayKey(date);

    if (isToday) {
        return (
            <time
                dateTime={key}
                className="-mx-1.5 -mt-1.5 flex items-center justify-between gap-2 bg-brand px-2 py-1 text-xs font-bold text-white tabular-nums"
            >
                <span>{children ?? date.getDate()}</span>
                <span className="text-[10px] tracking-wider uppercase">Today</span>
            </time>
        );
    }

    return (
        <time dateTime={key} className={`px-1 text-xs font-bold tabular-nums ${muted ? 'text-black/30' : 'text-black'}`}>
            {children ?? date.getDate()}
        </time>
    );
}
