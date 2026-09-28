import { formatTimeAgo } from '@/lib/format';
import type { ProductCardData } from '@/types/catalog';
import { changeDetail, changeMeta } from './changeMeta';

/**
 * What last changed on a product and when: "Stock falling · 12 → 8 left · 2m ago".
 * Falls back to just the time when only last_changed_at is known.
 */
export function ChangeSummary({ product }: { product: ProductCardData }) {
    const change = product.last_change;

    if (!change) {
        return <span className="text-black/50">{product.last_changed_at ? formatTimeAgo(product.last_changed_at) : '—'}</span>;
    }

    const meta = changeMeta[change.type];
    const Icon = meta.icon;

    return (
        <div className="flex min-w-0 flex-col gap-0.5">
            <span className={`inline-flex items-center gap-1 text-[11px] font-bold whitespace-nowrap ${meta.ink}`}>
                <Icon aria-hidden="true" className="size-3.5 shrink-0" strokeWidth={2.25} />
                {meta.label}
            </span>
            <span className="text-[11px] whitespace-nowrap text-black/70">
                {changeDetail(change, product)}
                <span className="text-black/40"> · </span>
                <time dateTime={change.occurred_at} className="text-black/50">
                    {formatTimeAgo(change.occurred_at)}
                </time>
            </span>
        </div>
    );
}
