import { SITE_NAME } from './site';

const columns: { heading: string; links: { label: string; href: string }[] }[] = [
    {
        heading: 'Track',
        links: [
            { label: 'Dashboard', href: '/' },
            { label: 'Upcoming drops', href: '/drops' },
            { label: 'Catalog', href: '/catalog' },
            { label: 'POP NOW boxes', href: '/pop-now' },
            { label: 'Store finder', href: '/stores' },
        ],
    },
    {
        heading: 'Account',
        links: [
            { label: 'Watchlist', href: '/watchlist' },
            { label: 'Alerts', href: '/alerts' },
            { label: 'Connected accounts', href: '/account/popmart' },
            { label: 'Settings', href: '/settings' },
        ],
    },
    {
        heading: 'About',
        links: [
            { label: 'How tracking works', href: '/about' },
            { label: 'Privacy', href: '/privacy' },
        ],
    },
];

export function SiteFooter() {
    return (
        <footer className="mt-24 border-t border-black/10 bg-tile">
            <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
                <div className="flex flex-col gap-3">
                    <span className="w-fit bg-brand px-2 py-0.5 text-sm font-bold text-white uppercase">{SITE_NAME}</span>
                    <p className="max-w-xs text-xs leading-relaxed text-black/60">
                        Stock, restock and release tracking for designer toys. Independent fan project, not affiliated
                        with Pop Mart.
                    </p>
                </div>
                {columns.map((column) => (
                    <nav key={column.heading} aria-label={column.heading} className="flex flex-col gap-3">
                        <h2 className="text-xs font-bold tracking-wider uppercase">{column.heading}</h2>
                        <ul className="flex flex-col gap-2">
                            {column.links.map((link) => (
                                <li key={link.href}>
                                    <a href={link.href} className="text-xs text-black/70 hover:text-black hover:underline">
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>
                ))}
            </div>
        </footer>
    );
}
