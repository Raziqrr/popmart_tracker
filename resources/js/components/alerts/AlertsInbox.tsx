import { Bell, CheckCheck, Layers } from 'lucide-react';
import { changeMeta } from '@/components/product/changeMeta';
import { formatTimeAgo } from '@/lib/format';
import type { AlertEntry, AlertItem } from '@/types/alerts';

interface AlertsInboxProps {
    alerts: AlertItem[];
    unread: number;
    /** Mark these read, or everything when called without ids. */
    onMarkRead?: (ids?: string[]) => void;
    emptyLabel?: string;
}

/**
 * Alerts for watched products, newest first (GET /api/alerts). Unread rows
 * are marked; opening one marks it read. A digest (many changes in one
 * sync run) lists its products inside one row.
 */
export function AlertsInbox({ alerts, unread, onMarkRead, emptyLabel = 'No alerts yet. Watch a product to hear when it restocks or opens.' }: AlertsInboxProps) {
    return (
        <section aria-label="Alerts" className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
                <h3 className="flex items-center gap-1.5 text-sm font-bold">
                    <Bell aria-hidden="true" className="size-4" />
                    Alerts
                    {unread > 0 && <span className="bg-brand px-1.5 py-0.5 text-[10px] leading-none font-bold text-white tabular-nums">{unread} new</span>}
                </h3>
                {unread > 0 && onMarkRead && (
                    <button type="button" onClick={() => onMarkRead()} className="inline-flex items-center gap-1 text-[11px] font-bold hover:underline">
                        <CheckCheck aria-hidden="true" className="size-3.5" />
                        Mark all read
                    </button>
                )}
            </div>

            {alerts.length === 0 ? (
                <p className="text-xs text-black/50">{emptyLabel}</p>
            ) : (
                <ul className="flex flex-col divide-y divide-black/10 border border-black/10">
                    {alerts.map((alert) => (
                        <li key={alert.id} className={alert.read_at ? '' : 'bg-brand/5'}>
                            {alert.kind === 'digest' ? (
                                <DigestRow alert={alert} onOpen={() => !alert.read_at && onMarkRead?.([alert.id])} />
                            ) : (
                                <EntryRow entry={alert} when={alert.occurred_at ?? alert.created_at} unread={!alert.read_at} onOpen={() => !alert.read_at && onMarkRead?.([alert.id])} />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function EntryRow({ entry, when, unread, onOpen }: { entry: AlertEntry; when?: string; unread?: boolean; onOpen?: () => void }) {
    const meta = changeMeta[entry.event_type];
    const Icon = meta.icon;

    return (
        <a href={entry.url} onClick={onOpen} className="flex items-start gap-2 p-2 text-xs hover:bg-tile">
            <Icon aria-hidden="true" className={`mt-0.5 size-4 shrink-0 ${meta.ink}`} />
            <span className="flex min-w-0 grow flex-col">
                <span className="truncate">
                    <span className={`font-bold ${meta.ink}`}>{entry.title}</span> · <span className="font-medium">{entry.product_name}</span>
                </span>
                <span className="text-black/60">{entry.detail}</span>
            </span>
            {when && <span className="shrink-0 text-[10px] text-black/50">{formatTimeAgo(when)}</span>}
            {unread && <span className="mt-1 size-2 shrink-0 rounded-full bg-brand" aria-label="unread" />}
        </a>
    );
}

function DigestRow({ alert, onOpen }: { alert: Extract<AlertItem, { kind: 'digest' }>; onOpen: () => void }) {
    return (
        <details onToggle={(e) => (e.currentTarget as HTMLDetailsElement).open && onOpen()}>
            <summary className="flex cursor-pointer items-center gap-2 p-2 text-xs hover:bg-tile">
                <Layers aria-hidden="true" className="size-4 shrink-0" />
                <span className="grow font-bold">{alert.title}</span>
                {alert.occurred_at && <span className="text-[10px] text-black/50">{formatTimeAgo(alert.occurred_at)}</span>}
                {!alert.read_at && <span className="size-2 shrink-0 rounded-full bg-brand" aria-label="unread" />}
            </summary>
            <ul className="border-t border-black/10 pl-4">
                {alert.items.map((item) => (
                    <li key={item.event_id}>
                        <EntryRow entry={item} />
                    </li>
                ))}
            </ul>
        </details>
    );
}
