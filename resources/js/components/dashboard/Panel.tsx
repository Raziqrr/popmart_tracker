import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** Titled dashboard block with an optional "view all" link. */
export function Panel({
    title,
    icon: Icon,
    action,
    children,
    className = '',
}: {
    title: string;
    icon?: LucideIcon;
    action?: { label: string; href: string };
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`flex min-w-0 flex-col gap-3 ${className}`}>
            <div className="flex items-baseline justify-between gap-4">
                <h2 className="flex items-center gap-2 text-lg font-bold">
                    {Icon && <Icon aria-hidden="true" className="size-5" />}
                    {title}
                </h2>
                {action && (
                    <a href={action.href} className="text-xs font-medium whitespace-nowrap underline-offset-4 hover:underline">
                        {action.label} →
                    </a>
                )}
            </div>
            {children}
        </section>
    );
}
