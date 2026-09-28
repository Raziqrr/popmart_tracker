const moneyFormatters = new Map<string, Intl.NumberFormat>();

function moneyFormatter(currency: string): Intl.NumberFormat {
    let formatter = moneyFormatters.get(currency);

    if (!formatter) {
        formatter = new Intl.NumberFormat('en', {
            style: 'currency',
            currency,
            currencyDisplay: 'narrowSymbol',
        });
        moneyFormatters.set(currency, formatter);
    }

    return formatter;
}

/** Formats a minor-unit amount, e.g. (1680, 'MYR') -> "RM16.80". */
export function formatMoney(minorUnits: number, currency: string): string {
    const formatter = moneyFormatter(currency);
    const digits = formatter.resolvedOptions().maximumFractionDigits ?? 2;

    // Pop Mart renders the symbol flush against the amount ("RM16.80").
    return formatter.format(minorUnits / 10 ** digits).replace(/\s/g, '');
}

/** "10:00" style time for sale-start labels. */
export function formatSaleTime(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}

/** "October 2" style date for section headings. */
export function formatSaleDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric' }).format(new Date(iso));
}

/** "Oct 2, 10:00" for compact drop listings. */
export function formatShortDateTime(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(
        new Date(iso),
    );
}

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/** Compact length of time: "45m", "6h", "2d". */
export function formatDuration(ms: number): string {
    const abs = Math.abs(ms);
    if (abs < HOUR) return `${Math.max(1, Math.round(abs / MINUTE))}m`;
    if (abs < DAY) return `${Math.round(abs / HOUR)}h`;
    return `${Math.round(abs / DAY)}d`;
}

/** Compact past-relative time: "just now", "5m ago", "3h ago", "2d ago". */
export function formatTimeAgo(iso: string, now: number = Date.now()): string {
    const diff = Math.max(0, now - new Date(iso).getTime());

    if (diff < MINUTE) return 'just now';
    if (diff < HOUR) return `${Math.floor(diff / MINUTE)}m ago`;
    if (diff < DAY) return `${Math.floor(diff / HOUR)}h ago`;
    return `${Math.floor(diff / DAY)}d ago`;
}

/** Countdown to a future time: "2d 4h", "3h 20m", "12m", or "now" once passed. */
export function formatCountdown(iso: string, now: number = Date.now()): string {
    const diff = new Date(iso).getTime() - now;

    if (diff <= 0) return 'now';
    const days = Math.floor(diff / DAY);
    const hours = Math.floor((diff % DAY) / HOUR);
    const minutes = Math.floor((diff % HOUR) / MINUTE);

    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${Math.max(1, minutes)}m`;
}
