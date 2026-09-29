# Ticket: POP NOW auto-lock

**Status:** open · **Area:** backend (rules, queue worker, Pop Mart client) + UI (built in the lab) · **Needs a decision first:** see Risks

## Why

Users want the tracker to lock a POP NOW box for them the moment it matters, then alert them to pay before the hold runs out. "The moment it matters" means one of:

- the draw opens;
- a sold-out set gets boxes back;
- a box becomes a good bet for a figure they want, because other figures have been ruled out of it.

Alerts alone are too slow for popular draws.

The UI is built against proposed types in `resources/js/types/lock.ts`, using fixtures and in-memory state:

- **Components** (`resources/js/components/lock/`):
  - `LockToggle`: the lock button on POP NOW products, plus the header **Lock** button and **L** shortcut via `AutoLockContext` / `QuickLockFinder`.
  - `AutoLockDialog`: quick setup.
  - `AutoLockForm`: every setting, used by the full page.
  - `LockPicker`: Random / Specific / Ranked.
  - `RankedQueue`: drag-to-reorder priority list.
  - `RenewalControls`: auto-renew settings.
  - `ActiveLocks`, `LockRules`, `LockHistory`: held boxes, rules and attempts.
- **Lab screens:** `ui-lab/pages/AutoLockPage.tsx`, `AutoLockSetupPage.tsx`, `PopNowPage.tsx` (per-box lock and "lock all free boxes in a set"), and `ui-lab/modules/LockLab.tsx`.

## How POP NOW hints work (read this first)

A row in `pop_now_box_hints` means the box is **not** that figure. Pop Mart's tip card only ever rules figures out; it never confirms what a box holds. This was checked across 36 real data points on 4 accounts: everyone who tips the same box sees the same exclusions.

Every set seen so far holds exactly one of each non-secret figure. So `ExclusionBoxPredictor::predictSet()` can count every valid way to give each unopened box a different figure (respecting each box's exclusions and removing figures revealed elsewhere) and turn that into an exact chance per box and figure. A box whose only remaining option is one figure is "confirmed by elimination".

**Secrets have no odds.** Nothing in Pop Mart's API says which box is the secret, so no chance-based trigger can target a secret. Only Random targets (or `sale_opens` / `restock` triggers) can end up with one.

Everything below targets boxes through these **chances**, never through "a hint says it's figure X".

## Setup flow

- **Quick setup (dialog, from any lock button, the header Lock button or L):** choose one of three modes, then press Confirm.
  - **Random:** the system locks any free boxes; set how many with − n +.
  - **Specific:** click a figure to add a box of it, click again for another, or use its − n + stepper.
  - **Ranked:** set a total count and drag figures into priority order.

  The trigger defaults from the product's status and the hold time defaults to Pop Mart's max. "More options" carries the draft to the full page.
- **Full setup (page):** the same picker plus:
  - exclusion trust (only count user-reported exclusions with at least N confirmations, or verified exclusions only);
  - the trigger, and its chance threshold when relevant;
  - hold time, auto-renew, and when the rule ends.

  It has a sticky summary and a Confirm button.
- **Risk acknowledgement:** asked once per user (`acknowledged_at`), then skipped.
- **Auto-renew (optional, off by default):** keep a box past Pop Mart's single-hold limit by re-selecting it shortly before each hold runs out. Per rule you set *re-lock when N seconds are left* and *keep holding for up to T in total*; T is required whenever auto-renew is on.
- **No cap** on boxes per rule (product decision). Pop Mart may still enforce its own per-account limit; show its refusal as a failed attempt.

### Triggers

| trigger | fires when | needs |
| --- | --- | --- |
| `sale_opens` | `products.sale_start_at` is reached | — |
| `restock` | a sold-out set gets free boxes again (`stock_snapshots` 0 → more than 0, or boxes reappear) | — |
| `chance_reached` | the chance of a free box holding one of the rule's figures reaches `min_chance` | Specific or Ranked picks |
| `narrowed` | a free box is narrowed down to one of the rule's figures: every other figure is excluded from it or revealed elsewhere | Specific or Ranked picks |

`chance_reached` and `narrowed` are re-checked whenever the set's hints or reveals change, since each new exclusion can raise other boxes' chances.

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
| target_kind | enum `random`, `specific`, `ranked` | |
| count | unsigned int, nullable | total boxes for `random` and `ranked`; null for `specific` (sum of pick counts) |
| min_confirmations | unsigned int, default 1 | user-reported exclusions need at least this many confirmations to count |
| verified_only | bool | ignore `user_reported` exclusions when computing chances |
| trigger | enum `sale_opens`, `restock`, `chance_reached`, `narrowed` | the last two need `specific` or `ranked` |
| min_chance | decimal(4,3), nullable | 0–1, required for `chance_reached` |
| lock_duration_seconds | unsigned int | capped at Pop Mart's max for the set |
| renew_when_seconds_left | unsigned int, nullable | null = no auto-renew; must be less than `lock_duration_seconds` |
| max_total_hold_seconds | unsigned int, nullable | required when renewing; must be more than `lock_duration_seconds` |
| locks_made | unsigned int, default 0 | the rule is done when this reaches its total |
| enabled | bool | |
| expires_at | timestamp, nullable | null = until the draw ends |
| acknowledged_at | timestamp | when the user ticked the risk acknowledgement |
| timestamps | | |

Indexes: `(enabled, product_id)`, `(user_id)`, `(trigger, enabled)`.

**`auto_lock_rule_picks`** (for `specific` and `ranked` rules)

| column | type | notes |
| --- | --- | --- |
| auto_lock_rule_id | fk auto_lock_rules | cascade |
| sku_id | fk skus (uuid) | the chosen figure (never a secret for chance-based triggers) |
| count | unsigned int, nullable | `specific`: boxes of this figure to lock; null for `ranked` |
| priority | unsigned int, nullable | `ranked`: 1 is tried first; null for `specific` |
| locks_made | unsigned int, default 0 | per-figure progress |

Unique `(auto_lock_rule_id, sku_id)`.

**`auto_lock_attempts`**

| column | type | notes |
| --- | --- | --- |
| id | bigint pk | |
| auto_lock_rule_id | fk auto_lock_rules, nullable | null for a manual one-off lock from the box grid |
| pop_now_box_id | fk pop_now_boxes | |
| sku_id | fk skus, nullable | the figure the rule was chasing in this box |
| chance_at_lock | decimal(4,3), nullable | that figure's chance in this box when it was locked |
| status | enum `pending`, `locked`, `purchased`, `expired`, `released`, `failed` | |
| attempted_at | timestamp | |
| locked_at / lock_expires_at | timestamp, nullable | |
| renewals | unsigned int, default 0 | re-locks done for this box |
| hold_ends_at | timestamp, nullable | first lock + `max_total_hold_seconds` when renewing |
| error | string, nullable | e.g. "Box already locked by another shopper" |
| raw_response | json, nullable | Pop Mart's reply, for debugging |
| idempotency_key | string unique | one attempt per (rule, box, trigger event) |

## Which Pop Mart endpoints it uses

All of these are already in `app/Services/Api/PopMart/Endpoints/Draw/` and require the account's session:

| endpoint | role in auto-lock |
| --- | --- |
| `AssignSet` / `GetAssignSetStatic` | get the set (and its boxes) the account is working in |
| `EnterBox` / `SwitchBox` | **select a box, which locks it.** Re-selecting a box you already hold resets Pop Mart's hold timer (confirmed manually), which is how auto-renew works |
| `CheckSetBoxLock` | read which boxes are locked and until when; used to confirm a lock took, and for the expiry check |
| `GetPropStatus` | how many tip cards the account has left (display only) |
| `UseTipCard` | spends a tip card to reveal exclusions for a box. **Never automated:** the user triggers it themselves, because it uses up their tip cards |

Still to confirm against real traffic: which of `EnterBox` / `SwitchBox` is the lock (or whether both are needed), and whether there's an explicit release.

## How it runs

1. **Trigger events** come from the scraper:
   - the sale opens;
   - a restock;
   - a set's hints or reveals change. This re-runs `predictSet()` for that set and checks `chance_reached` / `narrowed` rules on the same product.
2. **A queued job per matching enabled rule** picks free boxes (`pop_now_boxes.is_locked = false`) up to what's still owed:
   - `random`: any free box.
   - `specific`: for each figure with count left, the free box with the highest chance for it (the same as `GET /api/products/{product}/box-ranking?sku_id=`). It must meet the trigger's condition, and is computed with only the exclusions the rule trusts.
   - `ranked`: work down the priority list, taking the best box for priority 1, then 2, and so on until `count` is reached. Skip a figure when no free box meets the condition.
   - Never lock the same box twice for one account; resolve clashes between rules by rule creation order.
3. **The job selects the box** (`EnterBox` / `SwitchBox`) with the account's session, confirms it with `CheckSetBoxLock`, records the attempt (including `sku_id` and `chance_at_lock`), and alerts the user ("Box 07 held for you (Night Owl, 75%), pay within 5:00").
4. **Auto-renew:** when a held box reaches `renew_when_seconds_left` and `now + lock_duration_seconds` is still before `hold_ends_at`, a delayed job re-selects the same box.
   - On success it updates `locked_at` / `lock_expires_at` and increments `renewals`.
   - On failure the attempt keeps its current expiry and the user is alerted.
   - The last renewal is skipped once it would run past `hold_ends_at`.
   - Schedule with slack for queue delays: the renew point is the latest safe moment, not a target.
5. **A scheduled check** marks holds `expired` once `lock_expires_at` passes, and `purchased` when the order sync sees the order.

## Rate limiting

Every call above goes to Pop Mart on the user's account, and too many will get us rate-limited. So:

- **Don't poll per box.** Check lock state with one `CheckSetBoxLock` per set per interval, only while the account holds a box in it.
- **Reuse predictions.** Compute `predictSet()` from our own database, never by calling Pop Mart. Only re-run it when hints or reveals actually change.
- **Queue lock calls one at a time per account** (one lock job per account in flight at any moment), with a small gap between calls.
- **Back off** on Pop Mart errors or throttling responses, and pause the account's rules after repeated failures instead of retrying in a tight loop.

## Access

API calls that act on a Pop Mart account (locking, renewing, claiming tasks, anything with a session cookie) are **only available to logged-in users, for accounts they have connected** (the extension's `connect` flow). Guests get the read-only parts: catalog, drops, odds.

## Open questions

- **Which call locks.** `EnterBox`, `SwitchBox`, or both (see above).
- **Maximum hold time.** The UI assumes `pop_now_boxes.lock_duration_seconds` (300s in fixtures). Can the user shorten it, or only release early?
- **Release.** Does Pop Mart have a release call, or does the hold simply lapse?
- **Renew limits.** Re-selecting resets the timer (confirmed). Is there a cap on how many times, or a cooldown, before Pop Mart refuses or flags the account?
- **Exclusion freshness.** Exclusions are fixed on Pop Mart's side, so they shouldn't go stale, but should a user-reported one expire if it's never confirmed?

## Risks: decide before building

- **Terms of service.** Automated locking is bot behaviour on a live shop. It's very likely against Pop Mart's terms, and accounts could be banned. Get a clear decision before shipping.
- **Fairness.** Held boxes are unavailable to other shoppers, and auto-renew extends that past Pop Mart's intended limit, which is the part most likely to be treated as abuse.
  - Auto-renew stays opt-in per rule with a required total, and holds are released automatically when the total is reached.
  - The default is 1 box. There's no cap per rule (decided), and the box grid has "lock all free boxes in a set". Keep that to one account at a time, and always hand the payment step to the user.
- **Security.** It acts with stored session cookies, so it needs:
  - the access rule above;
  - an audit log (who, what box, when, result);
  - the idempotency key, so retries never double-lock.
- **Session expiry.** Refuse to enable rules while `popmart_accounts.session_expires_at` has passed. The dialog already blocks saving.

## Done when

- [ ] Decision recorded on the ToS and fairness risks
- [ ] Which draw call locks (and releases) confirmed against real traffic
- [ ] Migrations and models for the three tables, plus policies (users only see their own rules)
- [ ] Trigger → queued lock job → attempt row → alert, with feature tests for:
  - each trigger;
  - each target kind (including Ranked fallback order);
  - failure paths: box taken, session expired, Pop Mart error, throttled.
- [ ] Rate limiting in place (one lock job in flight per account, backoff)
- [ ] Expiry / purchase reconciliation job
- [ ] `types/lock.ts` confirmed (drop the "proposal" note) and added to the `app/Models/README.md` sync table
