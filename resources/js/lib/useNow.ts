import { useEffect, useState } from 'react';

/** Current time in ms, re-rendering every `intervalMs` so countdowns stay live. */
export function useNow(intervalMs: number): number {
    const [now, setNow] = useState(Date.now);

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), intervalMs);
        return () => window.clearInterval(id);
    }, [intervalMs]);

    return now;
}
