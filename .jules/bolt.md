## 2024-10-28 - Optimize ProductEvent queries
**Learning:** Querying append-only tables (like `product_events` and `stock_snapshots`) with a simple `->get()->unique(...)` in PHP forces excessive memory usage and database hydration because it fetches all events before filtering them down in PHP.
**Action:** Use `MAX(id)` grouping subqueries combined with `whereIn('id', ...)` to do the filtering on the database level, avoiding large data transfers and PHP memory overhead. This is already known in `.claude` memories. I should apply this to `ProductCards.php`.
