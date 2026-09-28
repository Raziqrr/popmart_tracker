import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { dayKey, groupByDay, weekdayLabel } from './dates';
import { DropGestureHint, DropTile } from './DropTile';
import { DayHeader, type DropViewProps } from './ReleaseCalendar';

const DAY = 24 * 3_600_000;
/** Width of one large tile in a day column (Tailwind w-40 = 10rem). */
const TILE_REM = 10;
/** Most tiles side by side in one day; more drops wrap onto new rows. */
const MAX_PER_ROW = 2;
/** Floating edge arrows appear once less than this share of the header arrows is visible. */
const CONTROLS_VISIBLE_RATIO = 0.2;

interface ReleaseTimelineProps extends DropViewProps {
    /** Days shown before today (older drops); the range also stretches to cover every product. */
    daysBefore?: number;
    /** Days shown after today. */
    daysAfter?: number;
}

/**
 * Horizontal scrolling timeline of drops, one column per day. A day's drops
 * sit side by side, at most two per row, then wrap downwards;
 * empty days collapse to a thin strip. Opens scrolled to today. When the
 * arrow controls above scroll out of view, carousel arrows appear on the
 * timeline's edges instead.
 */
export function ReleaseTimeline({ products, productHref, watchedIds, onToggleWatch, daysBefore = 14, daysAfter = 30 }: ReleaseTimelineProps) {
    const scroller = useRef<HTMLOListElement>(null);
    const todayRef = useRef<HTMLLIElement>(null);
    const controlsRef = useRef<HTMLDivElement>(null);
    const [controlsVisible, setControlsVisible] = useState(true);
    const [edges, setEdges] = useState({ atStart: false, atEnd: false });

    const byDay = useMemo(() => groupByDay(products), [products]);

    const days = useMemo(() => {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const saleDays = products.filter((p) => p.sale_start_at).map((p) => new Date(p.sale_start_at!).getTime());
        const start = Math.min(today.getTime() - daysBefore * DAY, ...saleDays);
        const end = Math.max(today.getTime() + daysAfter * DAY, ...saleDays);
        const first = new Date(start);
        const count = Math.round((end - start) / DAY) + 1;

        return Array.from({ length: count }, (_, i) => new Date(first.getFullYear(), first.getMonth(), first.getDate() + i));
    }, [products, daysBefore, daysAfter]);

    const today = dayKey(new Date());

    const updateEdges = useCallback(() => {
        const el = scroller.current;
        if (!el) return;
        setEdges({ atStart: el.scrollLeft <= 4, atEnd: el.scrollLeft + el.clientWidth >= el.scrollWidth - 4 });
    }, []);

    const scrollToToday = (behavior: ScrollBehavior) => {
        const el = scroller.current;
        const target = todayRef.current;
        if (el && target) el.scrollTo({ left: target.offsetLeft - el.offsetLeft - 8, behavior });
    };

    const scrollByPage = (direction: 1 | -1) => {
        const el = scroller.current;
        if (el) el.scrollBy({ left: direction * el.clientWidth * 0.8, behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToToday('auto');
        updateEdges();
    }, [updateEdges]);

    // Show the floating arrows once the header controls are mostly out of view (less than
    // CONTROLS_VISIBLE_RATIO showing), so there's a margin before they're completely gone.
    // The sticky site header is excluded from the visible area, since it covers the controls.
    useEffect(() => {
        const el = controlsRef.current;
        if (!el) return;
        const stickyHeader = document.querySelector('header.sticky');
        const topInset = stickyHeader ? Math.round(stickyHeader.getBoundingClientRect().height) : 0;
        const observer = new IntersectionObserver(
            ([entry]) => setControlsVisible(entry.intersectionRatio >= CONTROLS_VISIBLE_RATIO),
            { rootMargin: `-${topInset}px 0px 0px 0px`, threshold: [0, CONTROLS_VISIBLE_RATIO, 1] },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    const arrowButton = 'grid size-8 place-items-center border border-black/20 hover:border-black disabled:cursor-not-allowed disabled:opacity-30';

    return (
        <section aria-label="Release timeline" className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-xl font-bold sm:text-2xl">Timeline</h2>
                <div ref={controlsRef} className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => scrollToToday('smooth')}
                        className="border border-black/20 px-3 py-1.5 text-xs font-medium hover:border-black"
                    >
                        Today
                    </button>
                    <button type="button" onClick={() => scrollByPage(-1)} disabled={edges.atStart} aria-label="Scroll to earlier days" className={arrowButton}>
                        <ChevronLeft aria-hidden="true" className="size-4" />
                    </button>
                    <button type="button" onClick={() => scrollByPage(1)} disabled={edges.atEnd} aria-label="Scroll to later days" className={arrowButton}>
                        <ChevronRight aria-hidden="true" className="size-4" />
                    </button>
                </div>
            </div>

            {onToggleWatch && <DropGestureHint />}

            <div className="relative">
                <ol
                    ref={scroller}
                    onScroll={updateEdges}
                    tabIndex={0}
                    aria-label="Days, scroll horizontally"
                    className="flex snap-x snap-proximity overflow-x-auto border-y border-l border-black/10 pb-2 focus-visible:outline-2 focus-visible:outline-brand"
                >
                    {days.map((date, index) => {
                        const key = dayKey(date);
                        const drops = byDay.get(key) ?? [];
                        const isToday = key === today;
                        const newMonth = index === 0 || date.getDate() === 1;
                        const monthLabel = new Intl.DateTimeFormat('en-US', { month: 'short' }).format(date);

                        return (
                            <li
                                key={key}
                                ref={isToday ? todayRef : undefined}
                                aria-current={isToday ? 'date' : undefined}
                                className={`flex shrink-0 snap-start flex-col gap-2 border-r border-black/10 p-1.5 ${isToday ? 'bg-brand/5' : ''}`}
                                // Widen for up to MAX_PER_ROW tiles side by side; more drops wrap below.
                                style={{ width: drops.length ? columnWidth(Math.min(drops.length, MAX_PER_ROW)) : '3rem' }}
                            >
                                <DayHeader date={date} isToday={isToday}>
                                    <span className="flex flex-col leading-tight">
                                        <span
                                            className={`text-[10px] uppercase ${
                                                isToday ? 'font-medium text-white/80' : newMonth ? 'font-bold text-brand' : 'font-medium text-black/50'
                                            }`}
                                        >
                                            {newMonth ? monthLabel : weekdayLabel(date)}
                                        </span>
                                        {date.getDate()}
                                    </span>
                                </DayHeader>

                                {drops.length > 0 && (
                                    <div className={`grid gap-1.5 ${drops.length > 1 ? 'grid-cols-2' : 'grid-cols-1'}`}>
                                        {drops.map((product) => (
                                            <div key={product.id} className="min-w-0">
                                                <DropTile
                                                    variant="large"
                                                    product={product}
                                                    href={productHref?.(product)}
                                                    watching={watchedIds?.has(product.id) ?? false}
                                                    onToggleWatch={onToggleWatch}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ol>

                {!controlsVisible && (
                    <>
                        <EdgeArrow side="left" hidden={edges.atStart} onClick={() => scrollByPage(-1)} />
                        <EdgeArrow side="right" hidden={edges.atEnd} onClick={() => scrollByPage(1)} />
                    </>
                )}
            </div>
        </section>
    );
}

/** Column width for n tiles across, including the gaps and padding between them. */
function columnWidth(n: number): string {
    return `calc(${n * TILE_REM}rem + ${n + 1} * 0.375rem)`;
}

/**
 * Carousel arrow pinned to one edge of the timeline. It sits in a
 * full-height overlay strip and is sticky, so it stays mid-screen while the
 * page scrolls past a tall timeline.
 */
function EdgeArrow({ side, hidden, onClick }: { side: 'left' | 'right'; hidden: boolean; onClick: () => void }) {
    const Icon = side === 'left' ? ChevronLeft : ChevronRight;

    return (
        <div className={`pointer-events-none absolute inset-y-0 z-20 w-12 ${side === 'left' ? 'left-0' : 'right-0'}`}>
            <button
                type="button"
                onClick={onClick}
                tabIndex={hidden ? -1 : 0}
                aria-hidden={hidden}
                aria-label={side === 'left' ? 'Scroll to earlier days' : 'Scroll to later days'}
                className={`pointer-events-auto sticky top-[calc(50vh-1.25rem)] mx-auto my-4 grid size-10 place-items-center bg-black text-white shadow-lg transition-opacity hover:bg-brand ${
                    hidden ? 'pointer-events-none opacity-0' : 'opacity-90'
                }`}
            >
                <Icon aria-hidden="true" className="size-5" />
            </button>
        </div>
    );
}
