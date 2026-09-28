import { formatTimeAgo } from '@/lib/format';
import type { LockAttempt } from '@/types/lock';
import { attemptStatusMeta } from './lockText';

/** Every lock attempt, newest first, with the reason when Pop Mart refused. */
export function LockHistory({ attempts }: { attempts: LockAttempt[] }) {
    const sorted = [...attempts].sort((a, b) => b.attempted_at.localeCompare(a.attempted_at));

    if (sorted.length === 0) return <p className="py-6 text-center text-sm text-black/50">No lock attempts yet.</p>;

    return (
        <table className="w-full border-collapse text-left text-xs">
            <caption className="sr-only">Lock attempts</caption>
            <thead>
                <tr className="border-b border-black text-[10px] tracking-wider text-black/60 uppercase">
                    <th scope="col" className="py-2 pr-3 font-bold">When</th>
                    <th scope="col" className="px-3 py-2 font-bold">Product</th>
                    <th scope="col" className="hidden px-3 py-2 font-bold sm:table-cell">Box</th>
                    <th scope="col" className="px-3 py-2 font-bold">Result</th>
                </tr>
            </thead>
            <tbody>
                {sorted.map((attempt) => {
                    const meta = attemptStatusMeta[attempt.status];

                    return (
                        <tr key={attempt.id} className="border-b border-black/10 align-top">
                            <td className="py-2 pr-3 whitespace-nowrap text-black/60">{formatTimeAgo(attempt.attempted_at)}</td>
                            <td className="px-3 py-2">
                                <span className="line-clamp-1 font-medium">{attempt.product.name}</span>
                                {attempt.figure && <span className="text-[11px] text-black/50">Wanted: {attempt.figure.name}</span>}
                            </td>
                            <td className="hidden px-3 py-2 whitespace-nowrap text-black/60 sm:table-cell">
                                Set {attempt.set_no} · Box {attempt.box_no}
                            </td>
                            <td className="px-3 py-2">
                                <span className={`inline-block px-1.5 py-0.5 text-[10px] font-bold uppercase ${meta.tone}`}>{meta.label}</span>
                                {attempt.error && <p className="mt-0.5 text-[11px] text-status-critical-ink">{attempt.error}</p>}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}
