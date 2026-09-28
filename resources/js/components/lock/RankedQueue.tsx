import { Crown, GripVertical, X } from 'lucide-react';
import { useState } from 'react';
import type { LockRankedPick } from '@/types/lock';

interface RankedQueueProps {
    /** Sorted by priority ascending (1 first). */
    picks: LockRankedPick[];
    onReorder: (picks: LockRankedPick[]) => void;
    onRemove: (skuId: string) => void;
}

const renumber = (picks: LockRankedPick[]): LockRankedPick[] => picks.map((p, i) => ({ ...p, priority: i + 1 }));

/** Drag rows to change priority — 1 is tried first, the rest are fallbacks in order. */
export function RankedQueue({ picks, onReorder, onRemove }: RankedQueueProps) {
    const [draggingId, setDraggingId] = useState<string | null>(null);
    const [overId, setOverId] = useState<string | null>(null);

    if (picks.length === 0) {
        return <p className="text-xs text-black/50">Click a figure above to add it to the queue.</p>;
    }

    const move = (fromId: string, toId: string) => {
        if (fromId === toId) return;
        const from = picks.findIndex((p) => p.figure.sku_id === fromId);
        const to = picks.findIndex((p) => p.figure.sku_id === toId);
        if (from === -1 || to === -1) return;
        const next = [...picks];
        const [item] = next.splice(from, 1);
        next.splice(to, 0, item);
        onReorder(renumber(next));
    };

    return (
        <ul className="flex flex-col gap-1.5" aria-label="Figure priority, drag to reorder">
            {picks.map((pick) => {
                const id = pick.figure.sku_id;
                const dragging = id === draggingId;
                const over = id === overId && draggingId !== null && draggingId !== id;

                return (
                    <li
                        key={id}
                        draggable
                        onDragStart={() => setDraggingId(id)}
                        onDragEnd={() => {
                            setDraggingId(null);
                            setOverId(null);
                        }}
                        onDragOver={(e) => {
                            e.preventDefault();
                            if (draggingId && draggingId !== id) setOverId(id);
                        }}
                        onDrop={(e) => {
                            e.preventDefault();
                            if (draggingId) move(draggingId, id);
                            setDraggingId(null);
                            setOverId(null);
                        }}
                        className={`flex items-center gap-2 border bg-white p-1.5 text-xs transition-colors ${
                            dragging ? 'opacity-40' : ''
                        } ${over ? 'border-black border-t-2' : 'border-black/15'}`}
                    >
                        <GripVertical aria-hidden="true" className="size-4 shrink-0 cursor-grab text-black/40" />
                        <span className="grid size-6 shrink-0 place-items-center rounded-full bg-black text-[11px] font-bold text-white tabular-nums">
                            {pick.priority}
                        </span>
                        {pick.figure.image_url && <img src={pick.figure.image_url} alt="" className="size-8 shrink-0 object-contain" />}
                        <span className="min-w-0 grow truncate font-medium">
                            {pick.figure.name}
                            {pick.figure.is_secret && <Crown aria-label="secret" className="ml-1 inline size-3" fill="currentColor" />}
                        </span>
                        <button
                            type="button"
                            onClick={() => onRemove(id)}
                            aria-label={`Remove ${pick.figure.name} from the queue`}
                            className="grid size-6 shrink-0 place-items-center rounded-full text-black/50 hover:bg-status-critical-tint hover:text-status-critical-ink"
                        >
                            <X aria-hidden="true" className="size-3.5" />
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
