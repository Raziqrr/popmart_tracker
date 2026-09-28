import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export interface Stat {
    id: string;
    label: string;
    icon: LucideIcon;
    /** Headline figure: about the user's pinned items where that applies. */
    value: ReactNode;
    /** Catalog-wide context under the figure, e.g. "9 across all products". */
    detail?: ReactNode;
    href?: string;
    /** Brand-red figure when it needs action (e.g. a pinned item sold out). */
    highlight?: boolean;
    /** Marks figures that aren't backed by real logic yet. */
    estimate?: boolean;
}

/**
 * One compact row of headline numbers. Single numbers read best as tiles, not
 * charts; each tile links to the list that explains it.
 */
export function StatRow({ stats, lead }: { stats: Stat[]; lead?: ReactNode }) {
    return (
        <div className="grid grid-cols-2 border-t border-l border-black/10 sm:grid-cols-3 xl:grid-cols-6">
            {lead && <div className="col-span-2 border-r border-b border-black/10 sm:col-span-1">{lead}</div>}
            {stats.map((stat) => (
                <div key={stat.id} className="min-w-0 border-r border-b border-black/10">
                    {stat.href ? (
                        <a href={stat.href} className="block h-full hover:bg-tile">
                            <StatBody stat={stat} />
                        </a>
                    ) : (
                        <StatBody stat={stat} />
                    )}
                </div>
            ))}
        </div>
    );
}

function StatBody({ stat }: { stat: Stat }) {
    const Icon = stat.icon;

    return (
        <div className="flex h-full flex-col gap-1 p-3">
            <p className="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-black/60 uppercase">
                <Icon aria-hidden="true" className="size-3.5 shrink-0" />
                <span className="truncate">{stat.label}</span>
                {stat.estimate && (
                    <span className="ml-auto bg-tile px-1 text-[9px] tracking-normal text-black/60 normal-case" title="Not calculated by the backend yet">
                        estimate
                    </span>
                )}
            </p>
            <div className={`text-2xl leading-tight font-bold tabular-nums ${stat.highlight ? 'text-brand' : 'text-black'}`}>
                {stat.value}
            </div>
            {stat.detail && <div className="text-[11px] leading-snug text-black/50">{stat.detail}</div>}
        </div>
    );
}
