import { Check, Clover, LoaderCircle, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { formatTimeAgo } from '@/lib/format';
import type { LuckyPoints } from '@/types/account';

type RedeemState = 'idle' | 'confirming' | 'redeeming' | 'done' | 'error';

interface LuckyPointsTileProps {
    points: LuckyPoints | null;
    /**
     * Performs the redemption on the linked Pop Mart account. Backend doesn't
     * exist yet (docs/tickets/lucky-points.md); resolves on success, rejects on failure.
     */
    onRedeem?: (points: LuckyPoints) => Promise<void>;
    connectHref?: string;
}

/**
 * Lucky points balance with a one-click redeem. Redeeming acts on the user's
 * real Pop Mart account, so it always asks for confirmation first.
 */
export function LuckyPointsTile({ points, onRedeem, connectHref = '/account/popmart' }: LuckyPointsTileProps) {
    const [state, setState] = useState<RedeemState>('idle');

    const header = (
        <p className="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-black/60 uppercase">
            <Clover aria-hidden="true" className="size-3.5 text-good-text" />
            Lucky points
        </p>
    );

    if (!points) {
        return (
            <div className="flex h-full flex-col gap-2 p-3">
                {header}
                <p className="text-xs text-black/60">Link your Pop Mart account to see your points.</p>
                <a href={connectHref} className="mt-auto w-fit text-xs font-bold underline underline-offset-4">
                    Connect account
                </a>
            </div>
        );
    }

    const belowMinimum = points.redeem_minimum !== null && points.balance < points.redeem_minimum;
    const blockedReason = !points.session_valid
        ? 'Pop Mart session expired: reconnect to redeem.'
        : belowMinimum
          ? `Need ${points.redeem_minimum!.toLocaleString()} to redeem.`
          : points.balance === 0
            ? 'Nothing to redeem.'
            : null;

    const redeem = async () => {
        setState('redeeming');
        try {
            await onRedeem?.(points);
            setState('done');
        } catch {
            setState('error');
        }
    };

    return (
        <div className="flex h-full flex-col gap-1 p-3">
            {header}
            <div className="flex items-end justify-between gap-2">
                <p className="text-2xl leading-tight font-bold tabular-nums">{points.balance.toLocaleString()}</p>

                {state === 'idle' && (
                    <button
                        type="button"
                        disabled={!!blockedReason || !onRedeem}
                        onClick={() => setState('confirming')}
                        title={blockedReason ?? 'Redeem all points'}
                        className="bg-black px-3 py-1.5 text-[11px] font-bold tracking-wide text-white uppercase hover:bg-black/80 disabled:cursor-not-allowed disabled:bg-black/20"
                    >
                        Redeem
                    </button>
                )}
                {state === 'redeeming' && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-bold">
                        <LoaderCircle aria-hidden="true" className="size-3.5 animate-spin" />
                        Redeeming…
                    </span>
                )}
                {state === 'done' && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-bold text-good-text" role="status">
                        <Check aria-hidden="true" className="size-3.5" />
                        Redeemed
                    </span>
                )}
            </div>

            {state === 'confirming' && (
                <div className="flex flex-wrap items-center gap-2 bg-tile p-2 text-[11px]" role="alertdialog" aria-label="Confirm redeem">
                    <span className="grow">Redeem {points.balance.toLocaleString()} points on Pop Mart?</span>
                    <button type="button" onClick={redeem} className="bg-black px-2 py-1 font-bold text-white hover:bg-black/80">
                        Confirm
                    </button>
                    <button type="button" onClick={() => setState('idle')} className="px-2 py-1 font-bold hover:underline">
                        Cancel
                    </button>
                </div>
            )}
            {state === 'error' && (
                <p className="flex items-center gap-1 text-[11px] font-medium text-status-critical-ink" role="alert">
                    <TriangleAlert aria-hidden="true" className="size-3.5" />
                    Redeem failed.
                    <button type="button" onClick={() => setState('idle')} className="underline">
                        Try again
                    </button>
                </p>
            )}

            <p className="text-[11px] leading-snug text-black/50">
                {blockedReason ??
                    (points.expiring
                        ? `${points.expiring.points.toLocaleString()} expire soon`
                        : `Synced ${formatTimeAgo(points.synced_at)}`)}
            </p>
        </div>
    );
}
