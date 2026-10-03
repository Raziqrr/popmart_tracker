import { useId } from 'react';

/** A labelled input with its validation message, for the login and register forms. */
export function AuthField({
    label,
    value,
    onChange,
    error,
    type = 'text',
    autoComplete,
    autoFocus,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: string;
    autoComplete?: string;
    autoFocus?: boolean;
}) {
    const id = useId();

    return (
        <label htmlFor={id} className="flex flex-col gap-1 text-xs font-medium">
            {label}
            <input
                id={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                autoComplete={autoComplete}
                autoFocus={autoFocus}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                className="border border-black/20 px-2 py-1.5 text-sm aria-invalid:border-status-critical"
                required
            />
            {error && (
                <span id={`${id}-error`} className="text-status-critical-ink">
                    {error}
                </span>
            )}
        </label>
    );
}
