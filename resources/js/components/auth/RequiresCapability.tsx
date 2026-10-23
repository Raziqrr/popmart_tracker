import { Link2, LogIn } from 'lucide-react';
import type { ReactNode } from 'react';
import { CAPABILITIES, useCan } from '@/lib/capabilities';
import type { Capability } from '@/lib/capabilities';

interface RequiresCapabilityProps {
    capability: Capability;
    children: ReactNode;
    loginHref?: string;
    connectHref?: string;
    /** Replace the default prompt, e.g. to keep a disabled button in place. */
    fallback?: ReactNode;
}

/**
 * Renders children only when the user has the capability; otherwise the
 * next step: "Log in to …", "Connect Pop Mart to …" or "Reconnect Pop Mart
 * to …". Display only: the route still checks (app/Auth/Capability.php).
 */
export function RequiresCapability({ capability, children, loginHref = '/login', connectHref = '/account/popmart', fallback }: RequiresCapabilityProps) {
    const { allowed, reason } = useCan(capability);

    if (allowed) return <>{children}</>;
    if (fallback !== undefined) return <>{fallback}</>;

    const label = CAPABILITIES[capability].label;
    const linkClass = 'inline-flex w-fit items-center gap-1 text-[11px] font-bold underline underline-offset-2 hover:no-underline';

    return reason === 'login' ? (
        <a href={loginHref} className={linkClass}>
            <LogIn aria-hidden="true" className="size-3.5" />
            Log in to {label}
        </a>
    ) : (
        <a href={connectHref} className={linkClass}>
            <Link2 aria-hidden="true" className="size-3.5" />
            {reason === 'reconnect' ? 'Reconnect' : 'Connect'} Pop Mart to {label}
        </a>
    );
}
