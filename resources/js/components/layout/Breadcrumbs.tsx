export interface Crumb {
    label: string;
    href?: string;
}

/** "Home / New arrivals" trail; the last crumb is the current page and renders in the brand colour. */
export function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav aria-label="Breadcrumb">
            <ol className="flex flex-wrap items-center gap-1.5 text-xs">
                {items.map((crumb, index) => {
                    const last = index === items.length - 1;

                    return (
                        <li key={crumb.label} className="flex items-center gap-1.5">
                            {last || !crumb.href ? (
                                <span aria-current={last ? 'page' : undefined} className={last ? 'text-brand' : ''}>
                                    {crumb.label}
                                </span>
                            ) : (
                                <a href={crumb.href} className="text-black/70 hover:underline">
                                    {crumb.label}
                                </a>
                            )}
                            {!last && <span className="text-black/40">/</span>}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
