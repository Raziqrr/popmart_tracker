import type { ReactNode } from 'react';

/** Page heading for a lab entry. */
export function LabHeader({ title, description, source }: { title: string; description: string; source?: string }) {
    return (
        <header className="flex flex-col gap-2 border-b border-black/10 pb-6">
            <h1 className="text-2xl font-bold">{title}</h1>
            <p className="max-w-2xl text-sm text-black/60">{description}</p>
            {source && <code className="w-fit bg-tile px-2 py-1 text-[11px] text-black/70">{source}</code>}
        </header>
    );
}

/** One labelled state of a module, e.g. "Sold out" or "Watching". */
export function Specimen({ label, note, children }: { label: string; note?: string; children: ReactNode }) {
    return (
        <figure className="flex flex-col gap-3">
            <figcaption className="flex flex-col gap-0.5">
                <span className="text-[11px] font-bold tracking-wider text-brand uppercase">{label}</span>
                {note && <span className="text-xs text-black/50">{note}</span>}
            </figcaption>
            <div className="border border-dashed border-black/15 p-4">{children}</div>
        </figure>
    );
}

/** Responsive grid of specimens sized like product-grid columns. */
export function SpecimenGrid({ children }: { children: ReactNode }) {
    return <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{children}</div>;
}
