# Popmart Tracker Connector (browser extension)

Captures a user's Pop Mart session cookie after they log in for real, on Pop
Mart's own page — never sees their password. Scoped only to Pop Mart's
domains (see `host_permissions` in `manifest.json`), not `<all_urls>`.

## Loading it locally (Chrome/Edge)

1. Go to `chrome://extensions`, enable "Developer mode".
2. "Load unpacked" → select this `extension/` folder.
3. Copy the generated Extension ID (shown on the card) — the website needs it.

Current local dev extension ID: `mnjfjfajfpndedemfilmabopcbidhboe`
(unpacked IDs are derived from the folder path, so this stays stable on this
machine across reloads — but will differ on anyone else's machine, and once
published to the Chrome Web Store it gets a permanent, different ID.)

## How the website talks to it

The website never reads Pop Mart cookies directly (it can't — that's the
whole reason this extension exists). It only does two things:

**1. Detect the extension is installed:**

```js
const EXTENSION_ID = 'REPLACE_WITH_REAL_ID'; // from chrome://extensions

function isExtensionInstalled() {
  return new Promise((resolve) => {
    if (!window.chrome?.runtime?.sendMessage) return resolve(false);

    chrome.runtime.sendMessage(EXTENSION_ID, { type: 'ping' }, (response) => {
      resolve(!chrome.runtime.lastError && response?.installed === true);
    });
  });
}
```

**2. Trigger the connect flow and watch live status:**

```js
function connectPopmartAccount(onStatus) {
  const port = chrome.runtime.connect(EXTENSION_ID);
  port.onMessage.addListener((msg) => onStatus(msg.status, msg));
  port.postMessage({ type: 'connect' });
}

// Usage:
connectPopmartAccount((status) => {
  // 'opening_login' -> 'waiting_for_login' -> 'sending_to_server' -> 'connected'
  // or 'timed_out' / 'error'
  console.log(status);
});
```

The extension itself POSTs the captured cookie straight to
`POST /api/popmart-accounts/connect` (see `background.js`) — the website
just watches the status stream to update its UI while waiting. That route
is built (`PopmartAccountController::connect`) but currently stubs "current
user" to the seeded Test User, since there's no real auth system yet — see
the TODO in `PopmartAccountRepository::connect()`.

**3. Force a fresh login (clear Pop Mart's cookies):**

```js
chrome.runtime.sendMessage(EXTENSION_ID, { type: 'clearCookies' }, (response) => {
  console.log(`cleared ${response.cleared} cookies`);
});
```

Removes every cookie set for any `*.popmart.com` host. Useful because a
"logged out" browser session can otherwise silently resume the same account
on the next login (a persistent/remember-me cookie survives a normal
logout) — confirmed this actually happens: two supposedly different
connects both resolved to the same account via `GetSession`. Clear cookies
first, then log in, to guarantee a genuinely fresh session.

## Still to build (Laravel side)

- Real user auth — `PopmartAccountController::connect` currently attaches
  every captured session to the same stubbed Test User.
- `PopmartAccountRepository::connect()` currently always inserts a new row
  (rather than upserting) since we don't capture `popmart_member_id` yet —
  fine for testing multiple accounts, but means nothing dedupes.
- Since this endpoint is called from an extension background script (not a
  page load), it'll need either a Sanctum token or a CSRF-exempt route
  guarded some other way — plain session+CSRF auth won't work cleanly here.
- Update `api_base_url`, `externally_connectable`, and `host_permissions`
  in `manifest.json` once there's a real deployed domain.
