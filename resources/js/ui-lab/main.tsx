import '../../css/app.css';
import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { LabAutoLockProvider } from './fixtures/useAutoLock';
import { labEntries, plannedEntries, type LabEntry } from './registry';

// UI lab: mock pages and per-module state sheets, served by Vite alone
// (`npm run ui-lab`), no Laravel needed. Routing is hash-based: #/<entry id>.

function currentEntryId(): string {
    const id = window.location.hash.replace(/^#\//, '');
    return labEntries.some((e) => e.id === id) ? id : labEntries[0].id;
}

function useHashRoute() {
    const [id, setId] = useState(currentEntryId);

    useEffect(() => {
        // Product links inside mocks use plain "#slug" anchors; only react to "#/entry" routes.
        const onHashChange = () => window.location.hash.startsWith('#/') && setId(currentEntryId());
        window.addEventListener('hashchange', onHashChange);
        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    return id;
}

function UiLab() {
    const activeId = useHashRoute();
    const active = labEntries.find((e) => e.id === activeId)!;
    const Active = active.component;

    return (
        <div className="flex min-h-screen flex-col lg:flex-row">
            <aside className="shrink-0 border-b border-black/10 bg-tile lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:overflow-y-auto lg:border-r lg:border-b-0">
                <div className="flex flex-col gap-6 p-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-widest text-brand uppercase">UI lab</p>
                        <p className="text-xs text-black/50">Mockups &amp; module states</p>
                    </div>
                    <NavGroup title="Pages" entries={labEntries.filter((e) => e.kind === 'page')} activeId={activeId} />
                    <NavGroup title="Modules" entries={labEntries.filter((e) => e.kind === 'module')} activeId={activeId} />
                    <div className="hidden flex-col gap-1.5 lg:flex">
                        <h2 className="text-[11px] font-bold tracking-wider text-black/40 uppercase">Planned</h2>
                        <ul className="flex flex-col gap-1">
                            {plannedEntries.map((p) => (
                                <li key={p.title} className="text-xs text-black/35">
                                    {p.title} <span className="text-[10px]">({p.kind})</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </aside>

            <div className="min-w-0 grow">
                {active.kind === 'page' ? (
                    <Active key={active.id} />
                ) : (
                    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-8">
                        <Active key={active.id} />
                    </div>
                )}
            </div>
        </div>
    );
}

function NavGroup({ title, entries, activeId }: { title: string; entries: LabEntry[]; activeId: string }) {
    return (
        <nav aria-label={title} className="flex flex-col gap-1.5">
            <h2 className="text-[11px] font-bold tracking-wider text-black/40 uppercase">{title}</h2>
            <ul className="flex flex-wrap gap-1 lg:flex-col">
                {entries.map((entry) => (
                    <li key={entry.id}>
                        <a
                            href={`#/${entry.id}`}
                            aria-current={entry.id === activeId ? 'page' : undefined}
                            className="block px-2 py-1 text-sm hover:bg-black/5 aria-[current=page]:bg-black aria-[current=page]:text-white"
                        >
                            {entry.title}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

createRoot(document.getElementById('root')!).render(
    <StrictMode>
        {/* Site-wide auto-lock, like the real app's root layout: every page and module can start a lock. */}
        <LabAutoLockProvider>
            <UiLab />
        </LabAutoLockProvider>
    </StrictMode>,
);
