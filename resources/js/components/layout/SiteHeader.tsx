import { useState, type ReactNode } from 'react';
import type { AreaCode } from '@/types/catalog';
import { Bell, ChevronDown, CircleUserRound, Lock, Menu, Pin, Search, X } from 'lucide-react';
import { useAutoLockApi } from '@/components/lock/AutoLockContext';
import { AREAS, MAIN_NAV, SITE_NAME, type NavItem } from './site';

interface SiteHeaderProps {
    area: AreaCode;
    onAreaChange?: (area: AreaCode) => void;
    nav?: NavItem[];
    /** href of the current page, to highlight its nav item. */
    currentHref?: string;
    searchPlaceholder?: string;
    unreadAlerts?: number;
    pinnedCount?: number;
}

export function SiteHeader({
    area,
    onAreaChange,
    nav = MAIN_NAV,
    currentHref,
    searchPlaceholder = 'Search figures, themes…',
    unreadAlerts = 0,
    pinnedCount = 0,
}: SiteHeaderProps) {
    const [menuOpen, setMenuOpen] = useState(false);
    const autoLock = useAutoLockApi();

    return (
        <header className="sticky top-0 z-40 border-b border-black/10 bg-white">
            <div className="mx-auto grid h-14 max-w-7xl grid-cols-[1fr_auto_1fr] items-center gap-4 px-4 sm:px-8">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        className="-ml-1 p-1 lg:hidden"
                        aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                        aria-expanded={menuOpen}
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        {menuOpen ? <X aria-hidden="true" className="size-5" /> : <Menu aria-hidden="true" className="size-5" />}
                    </button>
                    <form role="search" className="hidden md:block" onSubmit={(e) => e.preventDefault()}>
                        <label className="flex w-56 items-center gap-2 rounded-full border border-black px-3 py-1.5">
                            <span className="sr-only">Search</span>
                            <input
                                type="search"
                                placeholder={searchPlaceholder}
                                className="w-full bg-transparent text-xs outline-none placeholder:text-black/50"
                            />
                            <Search aria-hidden="true" className="size-5" />
                        </label>
                    </form>
                </div>

                <a href="/" className="bg-brand px-2 py-0.5 text-sm font-bold tracking-tight text-white uppercase">
                    {SITE_NAME}
                </a>

                <div className="flex items-center justify-end gap-3 sm:gap-4">
                    <label className="hidden items-center gap-1 text-xs font-medium sm:flex">
                        <span className="sr-only">Region</span>
                        <select
                            value={area}
                            onChange={(e) => onAreaChange?.(e.target.value)}
                            className="cursor-pointer bg-transparent text-xs font-medium outline-none"
                        >
                            {AREAS.map((a) => (
                                <option key={a.code} value={a.code}>
                                    {a.code}
                                </option>
                            ))}
                        </select>
                    </label>
                    <a href="/search" className="md:hidden" aria-label="Search">
                        <Search aria-hidden="true" className="size-5" />
                    </a>
                    {autoLock && (
                        <button
                            type="button"
                            onClick={autoLock.openFinder}
                            aria-label="Auto-lock a POP NOW draw (shortcut L)"
                            title="Auto-lock a POP NOW draw (L)"
                            className="inline-flex items-center gap-1 border border-status-warning-ink bg-status-warning-tint px-2 py-1 text-xs font-bold text-status-warning-ink hover:bg-status-warning/30"
                        >
                            <Lock aria-hidden="true" className="size-4" />
                            <span className="hidden sm:inline">Lock</span>
                        </button>
                    )}
                    <HeaderIconLink href="/alerts" label="Alerts" count={unreadAlerts}>
                        <Bell aria-hidden="true" className="size-5" />
                    </HeaderIconLink>
                    <HeaderIconLink href="/watchlist" label="Pinned" count={pinnedCount}>
                        <Pin aria-hidden="true" className="size-5" />
                    </HeaderIconLink>
                    <a href="/account" aria-label="Account">
                        <CircleUserRound aria-hidden="true" className="size-5" />
                    </a>
                </div>
            </div>

            <nav aria-label="Main" className="hidden border-t border-black/5 lg:block">
                <ul className="mx-auto flex max-w-7xl justify-center gap-8 px-8">
                    {nav.map((item) => (
                        <li key={item.href}>
                            <a
                                href={item.href}
                                aria-current={item.href === currentHref ? 'page' : undefined}
                                className="flex items-center gap-1 border-b-2 border-transparent py-2.5 text-xs font-medium hover:border-black aria-[current=page]:border-brand aria-[current=page]:text-brand"
                            >
                                {item.label}
                                {item.hasMenu && <ChevronDown aria-hidden="true" className="size-3" />}
                            </a>
                        </li>
                    ))}
                </ul>
            </nav>

            {menuOpen && (
                <nav aria-label="Main" className="border-t border-black/10 lg:hidden">
                    <ul className="flex flex-col px-4 py-2">
                        {nav.map((item) => (
                            <li key={item.href}>
                                <a
                                    href={item.href}
                                    aria-current={item.href === currentHref ? 'page' : undefined}
                                    className="block border-b border-black/5 py-3 text-sm font-medium aria-[current=page]:text-brand"
                                >
                                    {item.label}
                                </a>
                            </li>
                        ))}
                        <li className="flex items-center justify-between py-3 text-sm font-medium">
                            Region
                            <select
                                value={area}
                                onChange={(e) => onAreaChange?.(e.target.value)}
                                className="border border-black/20 px-2 py-1 text-sm"
                            >
                                {AREAS.map((a) => (
                                    <option key={a.code} value={a.code}>
                                        {a.label}
                                    </option>
                                ))}
                            </select>
                        </li>
                    </ul>
                </nav>
            )}
        </header>
    );
}

function HeaderIconLink({
    href,
    label,
    count,
    children,
}: {
    href: string;
    label: string;
    count: number;
    children: ReactNode;
}) {
    return (
        <a href={href} className="relative" aria-label={count > 0 ? `${label} (${count})` : label}>
            {children}
            {count > 0 && (
                <span className="absolute -top-1.5 -right-2 min-w-4 rounded-full bg-brand px-1 text-center text-[10px] leading-4 font-bold text-white">
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </a>
    );
}
