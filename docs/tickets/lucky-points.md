# Ticket: Lucky points balance and one-click redeem

**Status:** built around two unknown endpoints (balance field, redeem call) · **Area:** backend (account sync) + dashboard · **Blocks:** "Lucky points" tile

## Why

Users want their Pop Mart lucky points on the dashboard with a button to redeem them without opening the Pop Mart app. The tile is built (`components/dashboard/LuckyPointsTile.tsx`) against the proposed `LuckyPoints` type in `resources/js/types/account.ts`, using fixture data and a fake redeem call.

## What to build

1. **Find the endpoints.** Identify Pop Mart's points balance and redeem endpoints for a logged-in member, what "redeem" actually converts points into, and any minimum or expiry rules.
2. **Sync the balance.** On the existing account sync (using `popmart_accounts.session_cookie`), store the balance, minimum, expiring points and `synced_at`, either on `popmart_accounts` or in a new `popmart_account_points` table.
3. **Redeem action.** Add a POST route and controller that calls Pop Mart's redeem endpoint with the user's session and returns success or failure. The UI already asks for confirmation before calling it.
4. **Expired sessions.** When `session_expires_at` has passed, return `session_valid: false` so the tile shows "reconnect" instead of a broken button.

## Risks to decide before building

- **Terms of service.** Automating actions on a user's Pop Mart account may break Pop Mart's terms and could get the account flagged. Check this before shipping, and keep redeem manual (one confirmed click, never automatic in the background).
- **Security.** The redeem route acts with the user's stored session. It needs auth, CSRF, rate limiting and an audit log entry (who, when, how many points, response).
- **Idempotency.** A double-click or retry must not redeem twice. Use a lock or idempotency key per request.

## Done when

- [ ] Balance synced and shown from real data (sync built: `LuckyPoints::sync`, `popmart:sync-points` every 30 min and after claiming tasks; the field names in `LuckyPointsAdapter` are guesses until captured)
- [x] Redeem route with auth, CSRF, rate limit, idempotency and an audit log (`POST /api/popmart-accounts/{account}/redeem-points`, `AccountActions`, `popmart_actions`). It answers `501 not_available` until the redeem call is mapped in `LuckyPoints::redeem()`
- [ ] Feature tests for success, below minimum, expired session and a Pop Mart error
- [ ] `types/account.ts` shape confirmed (drop "proposal" note) and added to the `app/Models/README.md` sync table

## What's built (2026-10-03)

- `popmart_account_points` holds the balance; `GET /api/lucky-points` serves it as `LuckyPoints`, with `session_valid` from the account.
- The balance is read from the daily-task response (`GetTasksByActivityAndChannel`), the best lead so far since claimed rewards go into the gauge. If the guessed keys aren't there, nothing is stored.
- Every action on a real account goes through `AccountActions`: Idempotency-Key replay, 5 per minute per user and action, and a `popmart_actions` audit row with request and response.

## To finish

1. `php artisan popmart:capture GetTasksByActivityAndChannel '{"activityId":"489fc5e7-f8cb-44c7-8a8c-c95ada9350c8","taskChannel":"web","userId":"<member id>","area":"MY"}' --account=<id> --check` and fix `LuckyPointsAdapter`. If the gauge isn't there, capture the Pop Mart page that shows the balance and add an endpoint.
2. Capture the redeem call (e.g. redeeming an early pass on the spot) and implement `LuckyPoints::redeem()`. Map Pop Mart's "no pass" / "not enough points" errors to their own codes.
