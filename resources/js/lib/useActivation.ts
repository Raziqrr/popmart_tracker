import { useEffect, useRef, useState } from 'react';

/**
 * Returns a counter that increments each time `active` flips from false to
 * true. 0 means "never switched on here", so nothing animates on first render.
 * Use it as a React `key` on the element that animates, so every activation
 * restarts the animation.
 */
export function useActivation(active: boolean): number {
    const [count, setCount] = useState(0);
    const previous = useRef(active);

    useEffect(() => {
        if (active && !previous.current) setCount((c) => c + 1);
        previous.current = active;
    }, [active]);

    return count;
}
