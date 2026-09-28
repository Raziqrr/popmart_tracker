# Ticket: Lucky points balance and one-click redeem

**Status:** open · **Area:** backend (account sync) + dashboard · **Blocks:** "Lucky points" tile

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

- [ ] Balance synced and shown from real data
- [ ] Redeem route with auth, CSRF, rate limit, idempotency and an audit log
- [ ] Feature tests for success, below minimum, expired session and a Pop Mart error
- [ ] `types/account.ts` shape confirmed (drop "proposal" note) and added to the `app/Models/README.md` sync table
