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

**A secret replaces a normal figure.** Checked on CRYBABY × Care Bears (`GetAssignSetStatic`, 2026-09-29): a 3×3 grid of 9 boxes, with 9 normal figures plus 1 secret. So a set without the secret is one of each normal figure. A set *with* the secret is missing one normal figure, and the predictor's odds for that set are slightly overconfident. Worth handling once we can see how often secrets turn up.

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
- **Auto-renew (optional, off by default): blocked, needs redesign.** It assumed re-selecting a held box resets Pop Mart's timer. Live tests (2026-09-29) show it doesn't: a held box keeps counting down whatever we send. See "What the lock API does". The UI's renew controls stay until we decide to drop the feature or turn it into "re-lock after it lapses".
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
| `AssignSet` / `GetAssignSetStatic` | get the set (and its boxes). **`AssignSet` releases every box the account holds**, so only call it before locking, never while holding |
| `EnterBox` | **the lock.** `{ spuId, setNo, boxNos: [...] }`. One call can lock many boxes |
| `SwitchBox` | moves one held box to another: `{ spuId, setNo, currentBoxNo, direction: 'direct', boxNo }`. Not needed for auto-lock |
| `CheckSetBoxLock` | per box: `isLockedByMe`, `lockRemainingSeconds`. **The only reliable way to confirm a hold** (`AssignSet`'s lock flags don't show the account's own holds) |
| `GetPropStatus` | how many tip cards the account has left (display only) |
| `UseTipCard` | spends a tip card to reveal exclusions for a box. **Never automated:** the user triggers it themselves, because it uses up their tip cards |

### What the lock API does (tested live, 2026-09-29, CRYBABY × Care Bears, 26 calls, no throttling)

- **Bulk:** one `EnterBox` with all 8 free boxes held all 8.
- **Adds, doesn't replace:** locking box 2 after box 1 left both held.
- **Per-box timers:** a newly locked box gets ~300s. A box already held keeps its running timer, even when it's in a later `EnterBox` call. The response's `lockRemainingSeconds` is the soonest-expiring hold, not the new box's.
- **No renewal:** re-sending a held box doesn't reset its timer (274s stayed 274s). `SwitchBox` gives a fresh 300s, but only by releasing the old box.
- **`AssignSet` drops holds:** calling it released every held box. Likely the same happens if the user opens the set on Pop Mart's own site with the same account (untested); warn users.

Still unknown: an explicit release call, a per-account box cap, and `SwitchBox`'s `left` / `right` directions.

### Buy now / checkout (recorded from Pop Mart's own page, 2026-09-29)

POP NOW has no cart: a held box can only be bought straight away. The site's flow:

1. **Buy now** → `draw/box/checkoutValidate` `{ spuId, setNo, boxNos, pageType: 'checkout' }` → `{ valid, lockRemainingSeconds }`. Takes a list, so one checkout can likely cover several held boxes (untested).
2. The site moves to `/my/checkout?channel=popNow` (no page reload) and polls `draw/box/getMinLockTTL` `{ spuId, setNo, boxNos }` → `{ lockRemainingSeconds }` to show the countdown.
3. **Reaching checkout does not extend the hold.** The box timer kept running (296s → 195s) and re-selecting the box on the way didn't reset it either.
4. **Place order** (not yet recorded) should create an unpaid draw order. `ec/order/getUserHasUnpaidOrder` returns `{ hasUnpaidOrder, closeCountdown }`, which suggests the order has its own payment window. If that window is longer than the remaining hold, "lock → place order at once → alert the user to pay" replaces auto-renew. **Unconfirmed**; also an unpaid order is a real order on the account, and repeatedly letting them lapse may be penalised.

`GetMinLockTTL` is the cheapest way to track the deadline of a whole group of held boxes (one call instead of one `CheckSetBoxLock` per box).

### Checkouts (`user_checkouts`, built)

"After locking, go straight to checkout" is part of the lock step: after `EnterBox` holds the boxes, `CheckoutValidate` sends them to checkout and a `user_checkouts` row is created, so the user only has to pay. It applies to auto-lock rules (`checkout_immediately`, on by default) and to manual locks from the box grid, a set's "lock all" and Lock All (one page-level setting, on by default). Each lock action creates one checkout per set, since `checkoutValidate` takes one set's boxes.

- **Tables:** `user_checkouts` (one row per checkout, per user and Pop Mart account) and `user_checkout_boxes` (which boxes it covers). Model `App\Models\UserCheckout`, mirrored by `UserCheckout` in `resources/js/types/lock.ts`.
- **States:** `pending` (sending to Pop Mart) → `ready` (`checkoutValidate` passed; `expires_at` = now + its `lockRemainingSeconds`) → `paid` (order sync sees the order) or `failed` (`failure_reason`: `expired` = not paid in time, `invalid` = Pop Mart refused).
- **Expiry:** `php artisan popnow:expire-checkouts` runs every minute and fails ready checkouts past `expires_at`.
- **Still to build:** the job that calls `CheckoutValidate` after a lock and creates the row, linking `paid` to the order sync, the `auto_lock_rule_id` foreign key once `auto_lock_rules` exists, and a UI list of checkouts with their countdown.

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
3. **The job locks all its boxes in one `EnterBox` call** with the account's session, then confirms each with `CheckSetBoxLock` (taking `lock_expires_at` from that box's own `lockRemainingSeconds`, not from the `EnterBox` response). It records one attempt per box (including `sku_id` and `chance_at_lock`) and alerts the user ("Box 07 held for you (Night Owl, 75%), pay within 5:00"). It must not call `AssignSet` while the account holds boxes.
4. **Auto-renew: not possible as designed** (a held box's timer can't be reset). Options: drop it, or re-lock a box after its hold lapses, which risks another shopper taking it in between. Decide before building.
5. **A scheduled check** marks holds `expired` once `lock_expires_at` passes, and `purchased` when the order sync sees the order.

## Rate limits (not handled yet)

We don't know Pop Mart's limits, so nothing throttles calls for now. It's up to the user to be careful with how many rules, boxes and renewals they set up. Once the app is deployed and we see real responses, we'll build a rate-limit detector (spot throttling responses, then slow down or pause).

Until then, one thing costs nothing and should be done anyway: compute `predictSet()` from our own database, never by calling Pop Mart.

## Access

API calls that act on a Pop Mart account (locking, renewing, claiming tasks, anything with a session cookie) are **only available to logged-in users, for accounts they have connected** (the extension's `connect` flow). Guests get the read-only parts: catalog, drops, odds.

## Open questions

- **Auto-renew.** Drop it, or redesign as re-lock after lapse (see "How it runs")?
- **Maximum hold time.** 300s confirmed live. Can the user shorten it, or only release early?
- **Release.** Does Pop Mart have a release call, or does the hold simply lapse? (`AssignSet` releases everything as a side effect.)
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
- [x] Which draw call locks confirmed against real traffic (`EnterBox`, 2026-09-29)
- [ ] Decision on auto-renew (drop, or re-lock after lapse)
- [ ] Migrations and models for the three tables, plus policies (users only see their own rules)
- [ ] Trigger → queued lock job → attempt row → alert, with feature tests for:
  - each trigger;
  - each target kind (including Ranked fallback order);
  - failure paths: box taken, session expired, Pop Mart error, throttled.
- [ ] Expiry / purchase reconciliation job
- [ ] `types/lock.ts` confirmed (drop the "proposal" note) and added to the `app/Models/README.md` sync table
