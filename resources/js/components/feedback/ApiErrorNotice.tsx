import { Clock, Link2, LogIn, RotateCw, TriangleAlert } from 'lucide-react';
import { describeApiError } from '@/lib/api';

interface ApiErrorNoticeProps {
    error: unknown;
    /** Shown for retryable and rate-limited errors. */
    onRetry?: () => void;
    /** Where "Log in" goes when the user isn't logged in. */
    loginHref?: string;
    /** Where "Connect" / "Reconnect" goes when Pop Mart isn't connected or its session expired. */
    connectHref?: string;
    /** Tighter layout for small tiles. */
    compact?: boolean;
}

/**
 * The one way a failed API call is shown: what went wrong in plain words, plus
 * the next step (log in, connect, try again, wait or reload). Reads any error
 * through describeApiError in lib/api.ts.
 */
export function ApiErrorNotice({ error, onRetry, loginHref = '/login', connectHref = '/account/popmart', compact = false }: ApiErrorNoticeProps) {
    const { title, detail, action } = describeApiError(error);
    const actionClass = 'inline-flex items-center gap-1 font-bold underline underline-offset-2 hover:no-underline';

    return (
        <div role="alert" className={`flex items-start gap-1.5 border-l-2 border-status-critical bg-status-critical-tint text-status-critical-ink ${compact ? 'p-1.5 text-[11px]' : 'p-2 text-xs'}`}>
            <TriangleAlert aria-hidden="true" className={`shrink-0 ${compact ? 'mt-px size-3.5' : 'mt-0.5 size-4'}`} />
            <div className="flex min-w-0 flex-col gap-0.5">
                <p className="font-bold">{title}</p>
                <p className="text-black/70">{detail}</p>
                {action === 'login' && (
                    <a href={loginHref} className={actionClass}>
                        <LogIn aria-hidden="true" className="size-3.5" />
                        Log in
                    </a>
                )}
                {(action === 'connect' || action === 'reconnect') && (
                    <a href={connectHref} className={actionClass}>
                        <Link2 aria-hidden="true" className="size-3.5" />
                        {action === 'connect' ? 'Connect' : 'Reconnect'} Pop Mart
                    </a>
                )}
                {action === 'retry' && onRetry && (
                    <button type="button" onClick={onRetry} className={`${actionClass} self-start`}>
                        <RotateCw aria-hidden="true" className="size-3.5" />
                        Try again
                    </button>
                )}
                {action === 'wait' && onRetry && (
                    <button type="button" onClick={onRetry} className={`${actionClass} self-start`}>
                        <Clock aria-hidden="true" className="size-3.5" />
                        Try again later
                    </button>
                )}
                {action === 'reload' && (
                    <button type="button" onClick={() => window.location.reload()} className={`${actionClass} self-start`}>
                        <RotateCw aria-hidden="true" className="size-3.5" />
                        Reload page
                    </button>
                )}
            </div>
        </div>
    );
}
