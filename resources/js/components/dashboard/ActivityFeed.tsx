import { useMemo, useState } from 'react';
import { changeDetail, changeMeta, type ChangeGroup } from '@/components/product/changeMeta';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import { formatTimeAgo } from '@/lib/format';
import type { ProductEvent } from '@/types/catalog';

type EventGroup = 'all' | ChangeGroup;

const groupLabels: Record<EventGroup, string> = { all: 'All', stock: 'Stock', price: 'Price', new: 'New & drops' };


interface ActivityFeedProps {
    events: ProductEvent[];
    pinnedIds?: ReadonlySet<string>;
    productHref?: (product: ProductEvent['product']) => string;
    /** Collapse to this many rows with a "show more" button. */
    limit?: number;
}

/** "What changed" timeline, newest first, filterable by kind and to watched products only. */
export function ActivityFeed({ events, pinnedIds, productHref, limit = 8 }: ActivityFeedProps) {
    const [group, setGroup] = useState<EventGroup>('all');
    const [watchingOnly, setWatchingOnly] = useState(false);
    const [expanded, setExpanded] = useState(false);

    const filtered = useMemo(
        () =>
            events
                .filter((e) => group === 'all' || changeMeta[e.type].group === group)
                .filter((e) => !watchingOnly || pinnedIds?.has(e.product.id))
                .sort((a, b) => b.occurred_at.localeCompare(a.occurred_at)),
        [events, group, watchingOnly, pinnedIds],
    );
    const shown = expanded ? filtered : filtered.slice(0, limit);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <div role="group" aria-label="Filter activity" className="flex flex-wrap gap-1">
                    {(Object.keys(groupLabels) as EventGroup[]).map((key) => (
                        <button
                            key={key}
                            type="button"
                            aria-pressed={group === key}
                            onClick={() => setGroup(key)}
                            className="px-2 py-1 text-xs font-medium text-black/60 hover:text-black aria-pressed:bg-black aria-pressed:text-white"
                        >
                            {groupLabels[key]}
                        </button>
                    ))}
                </div>
                {pinnedIds && (
                    <label className="ml-auto flex items-center gap-1.5 text-xs font-medium">
                        <input
                            type="checkbox"
                            checked={watchingOnly}
                            onChange={(e) => setWatchingOnly(e.target.checked)}
                            className="accent-black"
                        />
                        Pinned only
                    </label>
                )}
            </div>

            {shown.length === 0 ? (
                <p className="py-10 text-center text-sm text-black/50">No changes to show.</p>
            ) : (
                <ol className="divide-y divide-black/10 border-y border-black/10">
                    {shown.map((event) => {
                        const meta = changeMeta[event.type];
                        const href = productHref?.(event.product);
                        const watched = pinnedIds?.has(event.product.id);

                        return (
                            <li key={event.id} className="flex items-center gap-3 py-2.5">
                                <div className="size-10 shrink-0 bg-tile">
                                    {event.product.image_url && (
                                        <img src={event.product.image_url} alt="" loading="lazy" className="size-full object-contain p-1" />
                                    )}
                                </div>
                                <div className="min-w-0 grow">
                                    <p className="flex flex-wrap items-center gap-x-2 text-[11px]">
                                        <span className={`inline-flex items-center gap-1 font-bold tracking-wide uppercase ${meta.ink}`}>
                                            <meta.icon aria-hidden="true" className="size-3.5" strokeWidth={2.25} />
                                            {meta.label}
                                        </span>
                                        <span className="text-black/70">{changeDetail(event, event.product)}</span>
                                        <SaleTypeBadge product={event.product} />
                                        {watched && (
                                            <span className="bg-tile px-1 text-[10px] font-medium text-black/60">Pinned</span>
                                        )}
                                    </p>
                                    {href ? (
                                        <a href={href} className="line-clamp-1 text-xs font-medium hover:underline">
                                            {event.product.name}
                                        </a>
                                    ) : (
                                        <p className="line-clamp-1 text-xs font-medium">{event.product.name}</p>
                                    )}
                                </div>
                                <time dateTime={event.occurred_at} className="shrink-0 text-[11px] whitespace-nowrap text-black/50">
                                    {formatTimeAgo(event.occurred_at)}
                                </time>
                            </li>
                        );
                    })}
                </ol>
            )}

            {filtered.length > limit && (
                <button
                    type="button"
                    onClick={() => setExpanded((open) => !open)}
                    className="self-start text-xs font-medium underline underline-offset-4"
                >
                    {expanded ? 'Show less' : `Show all ${filtered.length}`}
                </button>
            )}
        </div>
    );
}
