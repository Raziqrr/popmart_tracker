import { BadgeCheck, Crown, ExternalLink, Lock, LockOpen, MessageSquarePlus, PackageOpen, ThumbsUp, Users } from 'lucide-react';
import { useState } from 'react';
import { formatTimeAgo } from '@/lib/format';
import { hintStrength } from '@/lib/popnow';
import { useNow } from '@/lib/useNow';
import type { SkuSummary } from '@/types/catalog';
import type { BoxHint, PopNowBox, PopNowSet } from '@/types/popnow';
import { boxStateMeta, countdown } from './BoxGrid';

interface BoxDetailProps {
    set: PopNowSet;
    box: PopNowBox;
    /** Lock this one box now (a one-off lock, not an auto-lock rule). */
    onLock?: (box: PopNowBox) => void;
    onRelease?: (box: PopNowBox) => void;
    payHref?: (box: PopNowBox) => string;
    /** Report which figure you believe is in the box (adds a user_reported hint). */
    onReportHint?: (box: PopNowBox, sku: SkuSummary) => void;
    /** Agree with an existing user hint (+1 confirmation). */
    onConfirmHint?: (box: PopNowBox, hint: BoxHint) => void;
}

/** Everything known about one box: state, lock timer, hints with their trust, reveal, and actions. */
export function BoxDetail({ set, box, onLock, onRelease, payHref, onReportHint, onConfirmHint }: BoxDetailProps) {
    const now = useNow(1000);
    const [reporting, setReporting] = useState(false);
    const [confirmingLock, setConfirmingLock] = useState(false);
    const left = box.lock_expires_at ? new Date(box.lock_expires_at).getTime() - now : 0;

    return (
        <section aria-label={`Box ${box.box_no}`} className="flex flex-col gap-4 border border-black/10 p-4">
            <header className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[10px] font-bold tracking-wider text-black/50 uppercase">Set {set.set_no}</p>
                    <h3 className="text-xl font-bold">Box {box.box_no}</h3>
                </div>
                <span className={`border px-2 py-1 text-[11px] font-bold ${boxStateMeta[box.state].tile}`}>
                    {boxStateMeta[box.state].label}
                    {left > 0 && ` · ${countdown(left)}`}
                </span>
            </header>

            {box.reveal ? (
                <div className="flex items-center gap-3 bg-tile p-3">
                    {box.reveal.sku.image_url && <img src={box.reveal.sku.image_url} alt="" className="size-14 object-contain" />}
                    <div className="text-xs">
                        <p className="inline-flex items-center gap-1 font-bold">
                            <PackageOpen aria-hidden="true" className="size-3.5" />
                            Revealed: {box.reveal.sku.name}
                            {box.reveal.sku.is_secret && <Crown aria-label="secret" className="size-3.5" fill="currentColor" />}
                        </p>
                        <p className="text-black/60">
                            {box.reveal.source === 'order_reveal' ? 'From an order' : box.reveal.source === 'open_box_api' ? 'Opened on Pop Mart' : 'Reported by a user'} ·{' '}
                            {formatTimeAgo(box.reveal.revealed_at)}
                        </p>
                    </div>
                </div>
            ) : (
                <div className="flex flex-col gap-2">
                    <h4 className="text-xs font-bold tracking-wider uppercase">Hints</h4>
                    {box.hints.length === 0 ? (
                        <p className="text-xs text-black/50">No hints for this box yet.</p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {[...box.hints]
                                .sort((a, b) => b.confirmations - a.confirmations)
                                .map((hint) => {
                                    const strength = hintStrength(hint);
                                    return (
                                        <li key={hint.sku.id + hint.source} className="flex items-center gap-2 border border-black/10 p-2">
                                            {hint.sku.image_url && <img src={hint.sku.image_url} alt="" className="size-10 shrink-0 object-contain" />}
                                            <div className="min-w-0 grow text-xs">
                                                <p className="inline-flex items-center gap-1 font-bold">
                                                    {hint.sku.name}
                                                    {hint.sku.is_secret && <Crown aria-label="secret" className="size-3.5" fill="currentColor" />}
                                                </p>
                                                <p className="flex items-center gap-1 text-black/60">
                                                    {strength === 'verified' ? (
                                                        <>
                                                            <BadgeCheck aria-hidden="true" className="size-3.5 text-status-good-ink" />
                                                            Verified by Pop Mart data
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Users aria-hidden="true" className="size-3.5" />
                                                            {hint.confirmations} user{hint.confirmations > 1 ? 's' : ''} {strength === 'strong' ? '(trusted)' : '(unconfirmed)'}
                                                        </>
                                                    )}
                                                    <span className="text-black/40">· {formatTimeAgo(hint.last_seen_at)}</span>
                                                </p>
                                            </div>
                                            {hint.source === 'user_reported' && onConfirmHint && (
                                                <button
                                                    type="button"
                                                    onClick={() => onConfirmHint(box, hint)}
                                                    aria-label={`Agree: box ${box.box_no} holds ${hint.sku.name}`}
                                                    title="I saw this too"
                                                    className="inline-flex shrink-0 items-center gap-1 border border-black/20 px-2 py-1 text-[11px] font-bold hover:border-black"
                                                >
                                                    <ThumbsUp aria-hidden="true" className="size-3.5" />
                                                    Agree
                                                </button>
                                            )}
                                        </li>
                                    );
                                })}
                        </ul>
                    )}
                </div>
            )}

            {/* Actions depend on state. */}
            <div className="flex flex-col gap-2 border-t border-black/10 pt-3">
                {box.state === 'available' && onLock && (
                    confirmingLock ? (
                        <div className="flex flex-wrap items-center gap-2 bg-status-warning-tint p-2 text-xs text-status-warning-ink" role="alertdialog" aria-label="Confirm lock">
                            <span className="grow">Lock box {box.box_no} on your Pop Mart account now?</span>
                            <button
                                type="button"
                                onClick={() => {
                                    setConfirmingLock(false);
                                    onLock(box);
                                }}
                                className="bg-black px-3 py-1.5 font-bold text-white hover:bg-black/80"
                            >
                                Lock
                            </button>
                            <button type="button" onClick={() => setConfirmingLock(false)} className="px-2 py-1.5 font-bold hover:underline">
                                Cancel
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setConfirmingLock(true)}
                            className="inline-flex items-center justify-center gap-1.5 bg-black px-3 py-2 text-xs font-bold tracking-wide text-white uppercase hover:bg-black/80"
                        >
                            <Lock aria-hidden="true" className="size-3.5" />
                            Lock this box
                        </button>
                    )
                )}

                {box.state === 'locked_mine' && (
                    <div className="flex flex-wrap gap-2">
                        <a
                            href={payHref?.(box) ?? '#'}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex grow items-center justify-center gap-1.5 bg-brand px-3 py-2 text-xs font-bold tracking-wide text-white uppercase hover:bg-brand/85"
                        >
                            Pay on Pop Mart
                            <ExternalLink aria-hidden="true" className="size-3.5" />
                        </a>
                        {onRelease && (
                            <button
                                type="button"
                                onClick={() => onRelease(box)}
                                className="inline-flex items-center gap-1.5 border border-black/20 px-3 py-2 text-xs font-bold hover:border-black"
                            >
                                <LockOpen aria-hidden="true" className="size-3.5" />
                                Release
                            </button>
                        )}
                    </div>
                )}

                {box.state === 'locked_other' && <p className="text-xs text-black/60">Someone else holds this box. It frees up when their timer ends.</p>}

                {!box.reveal && onReportHint && (
                    reporting ? (
                        <div className="flex flex-col gap-2 bg-tile p-2">
                            <p className="text-xs font-bold">Which figure is in box {box.box_no}?</p>
                            <div className="grid grid-cols-3 gap-1.5">
                                {set.composition.map(({ sku }) => (
                                    <button
                                        key={sku.id}
                                        type="button"
                                        onClick={() => {
                                            setReporting(false);
                                            onReportHint(box, sku);
                                        }}
                                        className="flex flex-col items-center gap-0.5 border border-black/10 bg-white p-1 text-[10px] font-medium hover:border-black"
                                    >
                                        {sku.image_url && <img src={sku.image_url} alt="" className="size-8 object-contain" />}
                                        <span className="line-clamp-1">{sku.name}</span>
                                    </button>
                                ))}
                            </div>
                            <button type="button" onClick={() => setReporting(false)} className="self-start text-[11px] font-bold hover:underline">
                                Cancel
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setReporting(true)}
                            className="inline-flex items-center justify-center gap-1.5 border border-black/20 px-3 py-2 text-xs font-bold hover:border-black"
                        >
                            <MessageSquarePlus aria-hidden="true" className="size-3.5" />
                            Report a hint
                        </button>
                    )
                )}
            </div>
        </section>
    );
}
