import { router } from '@inertiajs/react';
import { Bell, CircleUserRound, Link2, LogIn, LogOut, Pin } from 'lucide-react';
import type { AuthState } from '@/lib/capabilities';

/**
 * The header's account control: "Log in" for guests, otherwise a small menu
 * with the user's Pop Mart connection state and Log out (Fortify POST /logout).
 */
export function AccountMenu({ auth, connectHref = '/extension-test' }: { auth: AuthState; connectHref?: string }) {
    if (!auth.user) {
        return (
            <a href="/login" className="inline-flex items-center gap-1 text-xs font-bold hover:underline">
                <LogIn aria-hidden="true" className="size-4" />
                Log in
            </a>
        );
    }

    const popmart = auth.popmart;
    const itemClass = 'flex items-center gap-2 px-3 py-2 text-xs hover:bg-tile';

    return (
        <details className="relative">
            <summary className="flex cursor-pointer list-none items-center" aria-label={`Account: ${auth.user.name}`}>
                <CircleUserRound aria-hidden="true" className="size-5" />
            </summary>
            <div className="absolute right-0 z-50 mt-2 flex w-56 flex-col border border-black bg-white shadow-lg">
                <div className="border-b border-black/10 px-3 py-2">
                    <p className="truncate text-xs font-bold">{auth.user.name}</p>
                    <p className="truncate text-[11px] text-black/60">{auth.user.email}</p>
                </div>
                <a href={connectHref} className={itemClass}>
                    <Link2 aria-hidden="true" className="size-3.5" />
                    {!popmart ? 'Connect Pop Mart' : popmart.session_valid ? 'Pop Mart connected' : 'Reconnect Pop Mart'}
                    {popmart && <span className={`ml-auto size-2 rounded-full ${popmart.session_valid ? 'bg-status-good' : 'bg-status-critical'}`} aria-hidden="true" />}
                </a>
                <a href="/watchlist" className={itemClass}>
                    <Pin aria-hidden="true" className="size-3.5" />
                    Watchlist
                </a>
                <a href="/alerts" className={itemClass}>
                    <Bell aria-hidden="true" className="size-3.5" />
                    Alerts and settings
                </a>
                <button type="button" onClick={() => router.post('/logout')} className={`${itemClass} border-t border-black/10 text-left`}>
                    <LogOut aria-hidden="true" className="size-3.5" />
                    Log out
                </button>
            </div>
        </details>
    );
}
