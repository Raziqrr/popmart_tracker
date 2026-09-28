import { useEffect, useState } from 'react';

function parseHashQuery(): URLSearchParams {
    const queryString = window.location.hash.split('?')[1] ?? '';
    return new URLSearchParams(queryString);
}

/**
 * The query string portion of the hash route (#/page-pop-now?product=abc),
 * kept in sync as it changes — including when it's the same page entry with
 * a different query, which the router's own state (keyed by entry id only)
 * wouldn't otherwise notice.
 */
export function useHashQuery(): URLSearchParams {
    const [query, setQuery] = useState(parseHashQuery);

    useEffect(() => {
        const onHashChange = () => setQuery(parseHashQuery());
        window.addEventListener('hashchange', onHashChange);
        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    return query;
}
