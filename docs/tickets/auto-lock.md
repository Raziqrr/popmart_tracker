# Ticket: POP NOW auto-lock

**Status:** open · **Area:** backend (rules, queue worker, Pop Mart client) + UI (built in the lab) · **Needs a decision first:** see Risks

## Why

Users want the tracker to lock a POP NOW box for them the moment it matters (a draw opens, a set restocks, or a box is hinted to hold the figure they want), then alert them to pay before the hold runs out. Alerts alone are too slow for popular draws.

The UI is built against proposed types in `resources/js/types/lock.ts`, using fixtures and in-memory state:

- **Components** (`resources/js/components/lock/`): `LockToggle` (POP NOW products only), `AutoLockDialog` (quick setup), `AutoLockForm` (every setting, used by the full page), `LockPicker` (Random / Specific), `ActiveLocks` (held boxes with countdown), `LockRules`, `LockHistory`.
- **Lab screens:** the page mockups `ui-lab/pages/AutoLockPage.tsx` and `AutoLockSetupPage.tsx`, and the module sheet `ui-lab/modules/LockLab.tsx`.

### Setup flow

- **Quick setup (dialog, from a product's lock button):** pick **Random** (the system locks any free boxes; set how many with − n +) or **Specific** (click a figure to add a box of it, click again for another, or use its − n + stepper). Then press Confirm. The trigger defaults from the product's status and the hold time defaults to Pop Mart's max. "More options" carries the draft to the full page.
- **Full setup (page):** the same picker plus hint trust (min confirmations, verified only), trigger, hold time and when the rule ends, with a sticky summary and Confirm.
- The risk acknowledgement is asked once per user (`acknowledged_at`), then skipped.
- **Auto-renew (optional, off by default):** keep a box past Pop Mart's single-hold limit by re-locking it shortly before each hold runs out. Per rule: *re-lock when N seconds are left* and *keep holding for up to T in total* (T is required whenever auto-renew is on). The full page shows a timeline of when each re-lock happens. Held boxes show the current hold's countdown, the number of re-locks so far, and when the total hold ends. Component: `RenewalControls`.
- **No cap** on boxes per rule (product decision). Pop Mart may still enforce its own per-account limit; surface its refusal as a failed attempt.

## Where the data lives

Rules and attempts are **per-user data in the app database**, next to `wishlist_items`, `pinned_items` and `popmart_accounts`. They don't go in browser storage, for two reasons:

- the lock has to run on the server, using the user's saved Pop Mart session, even when their browser is closed;
- the user must see their rules and held boxes on every device.

They're separate from `wishlist_items`: an alert only notifies, while a lock performs an action on Pop Mart that can fail, time out or conflict with other shoppers, so it needs its own history.

### Proposed tables

**`auto_lock_rules`**

| column | type | notes |
| --- | --- | --- |
| id | bigint pk | |
| user_id | fk users | cascade |
| popmart_account_id | fk popmart_accounts | the account the lock acts as |
| product_id | fk products (uuid) | must be `business_type = draw` |
| target_kind | enum `random`, `specific` | |
| random_count | unsigned int, nullable | boxes to lock when `random` |
| min_confirmations | unsigned int, default 1 | for hint-based targets (`pop_now_box_hints.confirmations`) |
| verified_only | bool | ignore `user_reported` hints |
| trigger | enum `sale_opens`, `restock`, `hint_match` | `hint_match` requires a figure |
| lock_duration_seconds | unsigned int | capped at Pop Mart's max for the set |
| renew_when_seconds_left | unsigned int, nullable | null = no auto-renew; must be less than lock_duration_seconds |
| max_total_hold_seconds | unsigned int, nullable | required when renewing; must be more than lock_duration_seconds |
| locks_made | unsigned int, default 0 | the rule is done when this reaches the total (random_count, or the sum of pick counts) |
| enabled | bool | |
| expires_at | timestamp, nullable | null = until the draw ends |
| acknowledged_at | timestamp | when the user ticked the risk acknowledgement |
| timestamps | | |

Indexes: `(enabled, product_id)`, `(user_id)`, `(trigger, enabled)`.

**`auto_lock_rule_picks`** (only for `specific` rules)

| column | type | notes |
| --- | --- | --- |
| auto_lock_rule_id | fk auto_lock_rules | cascade |
| sku_id | fk skus (uuid) | the chosen figure |
| count | unsigned int | boxes of this figure to lock |
| locks_made | unsigned int, default 0 | per-figure progress |

Unique `(auto_lock_rule_id, sku_id)`.

**`auto_lock_attempts`**

| column | type | notes |
| --- | --- | --- |
| id | bigint pk | |
| auto_lock_rule_id | fk auto_lock_rules | cascade |
| pop_now_box_id | fk pop_now_boxes | |
| status | enum `pending`, `locked`, `purchased`, `expired`, `released`, `failed` | |
| attempted_at | timestamp | |
| locked_at / lock_expires_at | timestamp, nullable | |
| renewals | unsigned int, default 0 | re-locks done for this box |
| hold_ends_at | timestamp, nullable | first lock + max_total_hold_seconds when renewing |
| error | string, nullable | e.g. "Box already locked by another shopper" |
| raw_response | json, nullable | Pop Mart's reply, for debugging |
| idempotency_key | string unique | one attempt per (rule, box, trigger event) |

## How it runs

1. Triggers come from the scraper diffs that already exist conceptually: the sale opens (`products.sale_start_at` reached), a restock (`stock_snapshots` goes from 0 to more than 0), or a new or strengthened hint (`pop_now_box_hints`).
2. A queued job per matching enabled rule picks free boxes (`pop_now_boxes.is_locked = false`) up to what's still owed. `random` takes any free box. `specific` only takes boxes whose hint names a picked figure that still has count left, and meets `min_confirmations` / `verified_only`.
3. The job calls Pop Mart's lock endpoint with the account's session, records the attempt, and alerts the user ("Box 07 held for you, pay within 5:00").
4. **Auto-renew:** when a held box reaches `renew_when_seconds_left` and `now + lock_duration_seconds` is still before `hold_ends_at`, a delayed job re-selects the same box. **Confirmed manually: re-selecting a box you hold resets Pop Mart's hold timer.** On success it updates `locked_at` / `lock_expires_at` and increments `renewals`; on failure the attempt keeps its current expiry and the user is alerted. The last renewal is skipped once it would run past `hold_ends_at`, so the total never exceeds the limit the user set. Jobs must be scheduled with enough slack for queue latency (the renew point is the latest safe moment, not a target).
5. A scheduled check marks holds `expired` once `lock_expires_at` passes, and `purchased` when the order sync sees the order.

## Open questions

- **What POP NOW's lock actually is.** Endpoint, maximum hold time (the UI assumes `pop_now_boxes.lock_duration_seconds`, 300s in fixtures), and whether the user can shorten it or only release early.
- **Release.** Does Pop Mart have a release call, or does the hold simply lapse?
- **Renew limits.** Re-selecting resets the timer (confirmed). Is there a cap on how many times, or a cooldown, before Pop Mart refuses or flags the account?
- **Hint freshness.** How stale can a hint be before it's ignored?

## Risks: decide before building

- **Terms of service.** Automated locking is bot behaviour on a live shop. It's very likely against Pop Mart's terms, and accounts could be banned. Get a clear decision before shipping.
- **Fairness.** Held boxes are unavailable to other shoppers, and auto-renew extends that past Pop Mart's intended limit, which is the part most likely to be treated as abuse. It stays opt-in per rule with a required total, and holds are released automatically when the total is reached. The default is 1 box and there is no cap per rule (decided), so rate-limit per account and never lock in bulk across accounts, and always hand the payment step to the user.
- **Security.** It acts with stored session cookies. It needs auth, CSRF, rate limiting per account, an audit log (who, what box, when, result) and the idempotency key so retries never double-lock.
- **Session expiry.** Refuse to enable rules while `popmart_accounts.session_expires_at` has passed. The dialog already blocks saving.

## Done when

- [ ] Decision recorded on the ToS and fairness risks
- [ ] Migrations and models for both tables, plus policies (users only see their own rules)
- [ ] Trigger → queued lock job → attempt row → alert, with feature tests for each trigger and for failure paths (box taken, session expired, Pop Mart error)
- [ ] Expiry / purchase reconciliation job
- [ ] `types/lock.ts` confirmed (drop the "proposal" note) and added to the `app/Models/README.md` sync table
