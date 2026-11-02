## 2024-03-24 - Grouping append-only tables
**Learning:** When querying append-only tables (like `product_events` and `stock_snapshots`), PHP-side grouping `->get()->unique(...)` causes excessive memory usage and database hydration.
**Action:** Use `MAX(id)` grouping subqueries combined with `whereIn` instead of PHP-side filtering.
