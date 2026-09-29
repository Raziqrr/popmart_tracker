import { Lock, SlidersHorizontal, Trash2, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import type { ProductCardData } from '@/types/catalog';
import type { AutoLockRule, AutoLockRuleDraft, LockAccount, LockFigure, PopNowLockLimits } from '@/types/lock';
import { AccountNotice, accountBlocker, draftIsValid, RiskAcknowledgement, RuleSummary } from './AutoLockForm';
import { defaultTrigger, formatHold, newDraft, triggerLabels, isFigureTrigger } from './lockText';
import { LockPicker } from './LockPicker';

interface AutoLockDialogProps {
    open: boolean;
    product: ProductCardData;
    figures: LockFigure[];
    limits: PopNowLockLimits;
    account: LockAccount | null;
    /** Editing an existing rule; omit to create one. */
    rule?: AutoLockRule | null;
    /** The user already accepted the risk on an earlier rule, so the tick box is skipped. */
    acknowledged?: boolean;
    onSave: (draft: AutoLockRuleDraft) => void;
    onDelete?: (rule: AutoLockRule) => void;
    /** Opens the full setup page with the current draft. */
    onMoreOptions?: (draft: AutoLockRuleDraft) => void;
    onClose: () => void;
}

/**
 * Fast auto-lock setup: choose Random (how many) or Specific (which figures,
 * how many of each) and confirm. Trigger and hold time use defaults shown in
 * the summary; "More options" opens the full page for everything else.
 */
export function AutoLockDialog({
    open,
    product,
    figures,
    limits,
    account,
    rule,
    acknowledged = false,
    onSave,
    onDelete,
    onMoreOptions,
    onClose,
}: AutoLockDialogProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    const [draft, setDraft] = useState<AutoLockRuleDraft>(() =>
        rule
            ? { target: rule.target, trigger: rule.trigger, min_chance: rule.min_chance, lock_duration_seconds: rule.lock_duration_seconds, renew: rule.renew, enabled: true, expires_at: rule.expires_at }
            : newDraft(product, limits),
    );
    const [accepted, setAccepted] = useState(acknowledged || !!rule);

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const blocked = accountBlocker(account);
    const canSave = draftIsValid(draft, limits) && accepted && !blocked;

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby={titleId}
            onClose={onClose}
            onClick={(e) => e.target === dialogRef.current && onClose()}
            className="m-auto max-h-[90vh] w-[min(36rem,calc(100vw-2rem))] overflow-y-auto bg-white p-0 text-black backdrop:bg-black/50"
        >
            <form
                method="dialog"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (canSave) onSave(draft);
                }}
                className="flex flex-col"
            >
                <header className="sticky top-0 z-10 flex items-start gap-3 border-b border-black/10 bg-white p-4">
                    <div className="size-12 shrink-0 bg-tile">
                        {product.image_url && <img src={product.image_url} alt="" className="size-full object-contain p-1" />}
                    </div>
                    <div className="min-w-0 grow">
                        <p className="flex items-center gap-2 text-[10px] font-bold tracking-wider text-black/50 uppercase">
                            <Lock aria-hidden="true" className="size-3" />
                            {rule ? 'Edit auto-lock' : 'Quick auto-lock'}
                            <SaleTypeBadge product={product} />
                        </p>
                        <h2 id={titleId} className="line-clamp-2 text-base font-bold">
                            {product.name}
                        </h2>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close" className="grid size-8 shrink-0 place-items-center hover:bg-tile">
                        <X aria-hidden="true" className="size-5" />
                    </button>
                </header>

                <div className="flex flex-col gap-4 p-4">
                    <LockPicker
                        value={draft.target}
                        figures={figures}
                        onChange={(target) =>
                            setDraft((d) => ({
                                ...d,
                                target,
                                // Keep a chance trigger only while figures are chosen.
                                trigger: target.kind === 'random' && isFigureTrigger(d.trigger) ? defaultTrigger(product, target) : d.trigger,
                            }))
                        }
                    />

                    <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-black/60">
                        <span>
                            <strong className="text-black">{triggerLabels[draft.trigger].title}</strong> · hold {formatHold(draft.lock_duration_seconds)}
                            {draft.renew ? ` · auto-renew up to ${formatHold(draft.renew.max_total_hold_seconds)}` : ' · no auto-renew'}
                        </span>
                        {onMoreOptions && (
                            <button
                                type="button"
                                onClick={() => onMoreOptions(draft)}
                                className="inline-flex items-center gap-1 font-bold text-black underline underline-offset-2"
                            >
                                <SlidersHorizontal aria-hidden="true" className="size-3.5" />
                                More options
                            </button>
                        )}
                    </p>

                    <RuleSummary draft={draft} />
                    {blocked && <AccountNotice account={account} />}
                    {!acknowledged && !rule && <RiskAcknowledgement checked={accepted} onChange={setAccepted} />}
                </div>

                <footer className="sticky bottom-0 flex items-center gap-2 border-t border-black/10 bg-white p-4">
                    {rule && onDelete && (
                        <button
                            type="button"
                            onClick={() => onDelete(rule)}
                            className="inline-flex items-center gap-1.5 px-2 py-2 text-xs font-bold text-status-critical-ink hover:underline"
                        >
                            <Trash2 aria-hidden="true" className="size-4" />
                            Delete
                        </button>
                    )}
                    <button type="button" onClick={onClose} className="ml-auto px-4 py-2 text-xs font-bold hover:underline">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={!canSave}
                        className="inline-flex items-center gap-1.5 bg-black px-4 py-2 text-xs font-bold tracking-wide text-white uppercase hover:bg-black/80 disabled:cursor-not-allowed disabled:bg-black/20"
                    >
                        <Lock aria-hidden="true" className="size-3.5" />
                        Confirm
                    </button>
                </footer>
            </form>
        </dialog>
    );
}
