import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import { Sparkline } from '@/components/charts/Sparkline';
import { formatDuration } from '@/lib/format';
import type { StockPoint } from '@/types/catalog';

/** Net change across the history and the time it covers, e.g. { delta: -112, over: "2d" }. */
export function stockTrend(history: StockPoint[]) {
    const first = history[0];
    const last = history[history.length - 1];

    return {
        delta: last.stock - first.stock,
        over: formatDuration(new Date(last.checked_at).getTime() - new Date(first.checked_at).getTime()),
    };
}

/**
 * Stock sparkline plus a written summary of the net change and the period it
 * covers ("−112 over 2d"). Hovering the line gives per-check detail.
 */
export function StockTrend({ history, width = 64, height = 18 }: { history: StockPoint[]; width?: number; height?: number }) {
    if (history.length < 2) return null;

    const { delta, over } = stockTrend(history);
    const Icon = delta > 0 ? TrendingUp : delta < 0 ? TrendingDown : Minus;
    const ink = delta > 0 ? 'text-status-good-ink' : delta < 0 ? 'text-status-critical-ink' : 'text-black/50';

    return (
        <span className="relative z-10 inline-flex items-center gap-1.5">
            <Sparkline
                values={history.map((p) => p.stock)}
                times={history.map((p) => p.checked_at)}
                label="Stock"
                width={width}
                height={height}
            />
            <span className={`inline-flex items-center gap-0.5 text-[10px] font-bold whitespace-nowrap tabular-nums ${ink}`}>
                <Icon aria-hidden="true" className="size-3" />
                {delta === 0 ? 'No change' : `${delta > 0 ? '+' : '−'}${Math.abs(delta)}`}
                <span className="font-medium text-black/50">/{over}</span>
            </span>
        </span>
    );
}
