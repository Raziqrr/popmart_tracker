
## 2023-10-24 - Append-Only Table Queries Avoid PHP-side Hydration
**Learning:** When dealing with large append-only log tables (like `product_events` and `stock_snapshots`), using `->get()->unique(...)` or overwriting loop variables in PHP causes excessive memory usage and slow performance due to massive DB payload hydration.
**Action:** Use a grouping subquery (e.g., `MAX(id)` or `MAX(occurred_at)`) combined with a `whereIn` clause to ensure only the necessary, latest rows are pulled from the database, preventing DB bottleneck and PHP memory exhaustion.
