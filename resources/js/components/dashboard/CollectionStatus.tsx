import { CalendarClock, PinOff } from 'lucide-react';
import { StatusIcon } from '@/components/product/StatusBadge';
import { formatCountdown, formatTimeAgo } from '@/lib/format';
import { STATUS_ORDER, statusMeta } from '@/lib/productStatus';
import type { CollectionSummary } from '@/types/catalog';

interface CollectionStatusProps {
    collection: CollectionSummary;
    href?: string;
    onUnpin?: (collection: CollectionSummary) => void;
}

/**
 * Pinned collection at a glance: a part-to-whole bar of its products by
 * status, with icon + count labels so the split never relies on colour alone.
 */
export function CollectionStatus({ collection, href, onUnpin }: CollectionStatusProps) {
    const { status_counts: counts, product_count: total } = collection;
    const Title = href ? 'a' : 'span';

    return (
        <article className="flex min-w-0 flex-col gap-3 border border-black/10 p-3">
            <div className="flex items-start gap-3">
                <div className="size-12 shrink-0 bg-tile">
                    {collection.image_url && <img src={collection.image_url} alt="" loading="lazy" className="size-full object-contain p-1" />}
                </div>
                <div className="min-w-0 grow">
                    <p className="truncate text-[10px] font-medium tracking-wide text-black/50 uppercase">
                        {collection.theme_name ?? 'Collection'} · {collection.area}
                    </p>
                    <Title {...(href ? { href } : {})} className="line-clamp-1 text-sm font-bold hover:underline">
                        {collection.name}
                    </Title>
                    <p className="text-[11px] text-black/50">
                        {total} products
                        {collection.last_changed_at && ` · changed ${formatTimeAgo(collection.last_changed_at)}`}
                    </p>
                </div>
                {onUnpin && (
                    <button
                        type="button"
                        onClick={() => onUnpin(collection)}
                        aria-label={`Unpin ${collection.name}`}
                        title="Unpin from dashboard"
                        className="grid size-7 shrink-0 place-items-center text-black/40 hover:text-black"
                    >
                        <PinOff aria-hidden="true" className="size-4" />
                    </button>
                )}
            </div>

            {total > 0 && (
                <div
                    role="img"
                    aria-label={STATUS_ORDER.map((s) => `${counts[s]} ${statusMeta[s].label.toLowerCase()}`).join(', ')}
                    className="flex h-2 gap-0.5"
                >
                    {STATUS_ORDER.filter((s) => counts[s] > 0).map((s) => (
                        <span
                            key={s}
                            title={`${counts[s]} ${statusMeta[s].label.toLowerCase()}`}
                            className={`first:rounded-l-sm last:rounded-r-sm ${statusMeta[s].fill}`}
                            style={{ flexGrow: counts[s] }}
                        />
                    ))}
                </div>
            )}

            <ul className="flex flex-wrap gap-x-3 gap-y-1 text-[11px]">
                {STATUS_ORDER.filter((s) => counts[s] > 0).map((s) => (
                    <li key={s} className="inline-flex items-center gap-1">
                        <StatusIcon status={s} className="size-3.5" />
                        <span className="font-bold tabular-nums">{counts[s]}</span>
                        <span className="text-black/60">{statusMeta[s].label.toLowerCase()}</span>
                    </li>
                ))}
            </ul>

            {collection.next_sale_at && (
                <p className="mt-auto flex items-center gap-1.5 border-t border-black/5 pt-2 text-[11px] font-medium">
                    <CalendarClock aria-hidden="true" className="size-3.5 text-brand" />
                    Next drop in {formatCountdown(collection.next_sale_at)}
                </p>
            )}
        </article>
    );
}
