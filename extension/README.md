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

**2. Trigger the connect flow, then save the session:**

```js
function capturePopmartSession(onStatus) {
  return new Promise((resolve, reject) => {
    const port = chrome.runtime.connect(EXTENSION_ID);
    port.onMessage.addListener((msg) => {
      onStatus(msg.status);
      // 'checking_existing_session' -> 'opening_login' -> 'waiting_for_login' -> 'captured'
      if (msg.status === 'captured') resolve({ cookie: msg.cookie, expires_at: msg.expires_at });
      if (msg.status === 'timed_out') reject(new Error('Timed out waiting for the Pop Mart login.'));
    });
    port.postMessage({ type: 'connect' });
  });
}

// Usage, from a page where the user is logged in to Popmart Tracker:
const session = await capturePopmartSession(console.log);
await connectPopMart(session); // resources/js/lib/api.ts: POST /api/popmart-accounts/connect
```

The extension hands the captured cookie back to the page instead of posting
it itself. The page's request carries the user's login session and CSRF
token (`capability:connect_popmart`, see `app/Auth/Capability.php`), which a
background script can't do cleanly. The extension only talks to pages on
`api_base_url`'s origin, so no other site can ask it for the cookie.

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

- Update `api_base_url`, `externally_connectable`, and `host_permissions`
  in `manifest.json` once there's a real deployed domain.
