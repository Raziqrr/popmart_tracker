import { Check, Clover, ListChecks, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { RequiresCapability } from '@/components/auth/RequiresCapability';
import { ApiErrorNotice } from '@/components/feedback/ApiErrorNotice';
import { formatTimeAgo } from '@/lib/format';
import type { LuckyPoints, TaskClaimResult } from '@/types/account';

type RedeemState = 'idle' | 'confirming' | 'redeeming' | 'done' | 'error';
type ClaimState = { kind: 'idle' } | { kind: 'claiming' } | { kind: 'done'; results: TaskClaimResult[] } | { kind: 'error'; error: unknown };

interface LuckyPointsTileProps {
    points: LuckyPoints | null;
    /**
     * Performs the redemption on the linked Pop Mart account. Backend doesn't
     * exist yet (docs/tickets/lucky-points.md); resolves on success, rejects on failure.
     */
    onRedeem?: (points: LuckyPoints) => Promise<void>;
    /**
     * Claims finished POP NOW daily tasks into the gauge, e.g. () => claimTasks(accountId).then((r) => r.results)
     * (lib/api.ts). Rejects with an ApiError when Pop Mart fails. Omit to hide the button.
     */
    onClaimTasks?: () => Promise<TaskClaimResult[]>;
    connectHref?: string;
}

/**
 * Lucky points balance with a one-click redeem. Redeeming acts on the user's
 * real Pop Mart account, so it always asks for confirmation first.
 */
export function LuckyPointsTile({ points, onRedeem, onClaimTasks, connectHref = '/account/popmart' }: LuckyPointsTileProps) {
    const [state, setState] = useState<RedeemState>('idle');
    const [redeemError, setRedeemError] = useState<unknown>(null);
    const [claim, setClaim] = useState<ClaimState>({ kind: 'idle' });

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
        } catch (error) {
            setRedeemError(error);
            setState('error');
        }
    };

    const claimTasks = async () => {
        if (!onClaimTasks) return;
        setClaim({ kind: 'claiming' });
        try {
            setClaim({ kind: 'done', results: await onClaimTasks() });
        } catch (error) {
            setClaim({ kind: 'error', error });
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
            {state === 'error' && <ApiErrorNotice error={redeemError} onRetry={() => setState('idle')} connectHref={connectHref} compact />}

            <p className="text-[11px] leading-snug text-black/50">
                {blockedReason ??
                    (points.expiring
                        ? `${points.expiring.points.toLocaleString()} expire soon`
                        : `Synced ${formatTimeAgo(points.synced_at)}`)}
            </p>

            {onClaimTasks && points.session_valid && (
                <div className="mt-1 flex flex-col gap-1 border-t border-black/10 pt-2">
                    {claim.kind === 'idle' && (
                        <RequiresCapability capability="claim_tasks" connectHref={connectHref}>
                            <button type="button" onClick={claimTasks} className="inline-flex w-fit items-center gap-1 text-[11px] font-bold hover:underline">
                                <ListChecks aria-hidden="true" className="size-3.5" />
                                Claim daily tasks
                            </button>
                        </RequiresCapability>
                    )}
                    {claim.kind === 'claiming' && (
                        <span className="inline-flex items-center gap-1 text-[11px] font-bold">
                            <LoaderCircle aria-hidden="true" className="size-3.5 animate-spin" />
                            Claiming…
                        </span>
                    )}
                    {claim.kind === 'done' && <ClaimSummary results={claim.results} onDismiss={() => setClaim({ kind: 'idle' })} />}
                    {claim.kind === 'error' && <ApiErrorNotice error={claim.error} onRetry={claimTasks} connectHref={connectHref} compact />}
                </div>
            )}
        </div>
    );
}

/** "2 claimed · 1 failed", with the reason for anything Pop Mart refused. */
function ClaimSummary({ results, onDismiss }: { results: TaskClaimResult[]; onDismiss: () => void }) {
    const claimed = results.filter((r) => r.status === 'claimed').length;
    const failed = results.filter((r) => r.status === 'failed');
    const summary = [claimed ? `${claimed} claimed` : null, failed.length ? `${failed.length} failed` : null].filter(Boolean).join(' · ') || 'Nothing to claim right now';

    return (
        <div role="status" className="flex flex-col gap-0.5 text-[11px]">
            <span className="flex items-center justify-between gap-2">
                <span className={`inline-flex items-center gap-1 font-bold ${failed.length ? 'text-status-critical-ink' : 'text-good-text'}`}>
                    <Check aria-hidden="true" className="size-3.5" />
                    {summary}
                </span>
                <button type="button" onClick={onDismiss} className="text-black/50 hover:underline">
                    OK
                </button>
            </span>
            {failed.map((task) => (
                <span key={task.alias} className="text-black/60">
                    {task.alias}: {task.message}
                </span>
            ))}
        </div>
    );
}
