// Fixture timestamps are relative to page load so "5m ago" and countdowns always look live.
const loadedAt = Date.now();

export const minutesAgo = (minutes: number) => new Date(loadedAt - minutes * 60_000).toISOString();
export const hoursAgo = (hours: number) => minutesAgo(hours * 60);
export const daysAgo = (days: number) => minutesAgo(days * 24 * 60);
export const hoursFromNow = (hours: number) => new Date(loadedAt + hours * 3_600_000).toISOString();
