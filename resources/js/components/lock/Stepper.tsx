import { Minus, Plus } from 'lucide-react';

interface StepperProps {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    /** Omit for no upper limit. */
    max?: number;
    /** What is being counted, for screen readers ("boxes of Sleepy Toast"). */
    label: string;
    size?: 'sm' | 'md';
}

/** − n + control. Buttons disable at the bounds. */
export function Stepper({ value, onChange, min = 0, max = Infinity, label, size = 'md' }: StepperProps) {
    const box = size === 'sm' ? 'size-6' : 'size-8';

    return (
        <div role="group" aria-label={label} className="inline-flex items-center border border-black/20 bg-white select-none">
            <button
                type="button"
                onClick={() => onChange(Math.max(min, value - 1))}
                disabled={value <= min}
                aria-label={`Fewer ${label}`}
                className={`${box} grid place-items-center hover:bg-tile disabled:cursor-not-allowed disabled:opacity-30`}
            >
                <Minus aria-hidden="true" className="size-3.5" />
            </button>
            <output aria-live="polite" className={`min-w-7 text-center font-bold tabular-nums ${size === 'sm' ? 'text-xs' : 'text-sm'}`}>
                {value}
            </output>
            <button
                type="button"
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={value >= max}
                aria-label={`More ${label}`}
                className={`${box} grid place-items-center hover:bg-tile disabled:cursor-not-allowed disabled:opacity-30`}
            >
                <Plus aria-hidden="true" className="size-3.5" />
            </button>
        </div>
    );
}
