## 2024-05-15 - Optimize append-only table querying
**Learning:** Querying large append-only tables (like product_events and stock_snapshots) and doing filtering on the PHP side using `->get()->unique(...)` or similar functions results in excessive memory usage and hydrates too many eloquent models.
**Action:** Instead, push the filtering to the database by using a subquery to find the maximum primary key (e.g. `MAX(id)`) grouped by the relevant grouping field, and then fetch only those specific records using `whereIn`.
