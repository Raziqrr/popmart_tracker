// Popmart Tracker Connector — background service worker (Manifest V3)
//
// Scope is deliberately narrow: this extension only ever reads cookies for
// Pop Mart's own domains (see host_permissions in manifest.json), never
// <all_urls>. It never sees the user's Pop Mart password — only the session
// cookie left behind after they log in themselves, on Pop Mart's real page.

const manifest = chrome.runtime.getManifest();

const API_BASE_URL = manifest.api_base_url;
const POPMART_COOKIE_URL = manifest.popmart_api_base_url;
const SESSION_TOKEN_COOKIE = manifest.session_token_cookie;
const SESSION_DATA_COOKIE = manifest.session_data_cookie;
const POPMART_LOGIN_URL = manifest.popmart_login_url;

// Our own site's origin. externally_connectable already limits who can message
// us; this re-checks the sender so a loosened manifest can't expose clearCookies.
const API_ORIGIN = new URL(API_BASE_URL).origin;
const isOwnSite = (sender) => sender?.origin === API_ORIGIN || (!!sender?.url && new URL(sender.url).origin === API_ORIGIN);

// --- Detection handshake: the website pings us to check we're installed ---
chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
    if (message?.type === 'ping') {
        sendResponse({ installed: true, version: chrome.runtime.getManifest().version });
        return false; // answered synchronously
    }

    if (message?.type === 'clearCookies') {
        if (!isOwnSite(sender)) {
            sendResponse({ error: 'not_allowed' });
            return false;
        }

        clearPopmartCookies()
            .then((count) => sendResponse({ cleared: count }))
            .catch((error) => sendResponse({ error: String(error) }));
        return true; // keep the channel open for the async sendResponse above
    }

    // Unknown message: don't hold the channel open.
    return false;
});

// Removes every cookie set for any *.popmart.com host — used to force a
// genuinely fresh login (a "logged out" browser session can otherwise
// silently resume the same account instead of prompting new credentials).
async function clearPopmartCookies() {
    const cookies = await chrome.cookies.getAll({ domain: 'popmart.com' });

    await Promise.all(cookies.map((cookie) => {
        const protocol = cookie.secure ? 'https://' : 'http://';
        const url = `${protocol}${cookie.domain.replace(/^\./, '')}${cookie.path}`;

        return chrome.cookies.remove({ url, name: cookie.name });
    }));

    return cookies.length;
}

// --- Long-lived connection: the website opens a Port and waits for status events ---
chrome.runtime.onConnectExternal.addListener((port) => {
    if (!isOwnSite(port.sender)) {
        port.disconnect();
        return;
    }

    port.onMessage.addListener((message) => {
        if (message?.type === 'connect') {
            startCapture(port);
        }
    });
});

async function startCapture(port) {
    port.postMessage({ status: 'checking_existing_session' });

    let cookie = await getSessionCookie();

    if (!cookie) {
        port.postMessage({ status: 'opening_login' });
        await chrome.tabs.create({ url: POPMART_LOGIN_URL });

        port.postMessage({ status: 'waiting_for_login' });

        const timeoutMs = 5 * 60 * 1000; // give the user 5 minutes to log in
        cookie = await waitForSessionCookie(Date.now() + timeoutMs);
    }

    if (!cookie) {
        port.postMessage({ status: 'timed_out' });
        return;
    }

    // Hand the session to our page (already checked to be our own site in
    // onConnectExternal). The page posts it to /api/popmart-accounts/connect
    // with the user's login session and CSRF token, which a background script
    // can't do cleanly.
    port.postMessage({ status: 'captured', ...toSession(cookie) });
}

// A single, immediate check — no waiting. Returns null if not logged in
// (or the cookie is present but already expired).
async function getSessionCookie() {
    const token = await chrome.cookies.get({ url: POPMART_COOKIE_URL, name: SESSION_TOKEN_COOKIE });
    const data = await chrome.cookies.get({ url: POPMART_COOKIE_URL, name: SESSION_DATA_COOKIE });

    if (!token || !data) return null;
    if (token.expirationDate && token.expirationDate * 1000 <= Date.now()) return null;

    return { token, data };
}

function waitForSessionCookie(deadline) {
    return new Promise((resolve) => {
        const check = async () => {
            const cookie = await getSessionCookie();

            if (cookie) {
                resolve(cookie);
                return;
            }

            if (Date.now() > deadline) {
                resolve(null);
                return;
            }

            setTimeout(check, 2000);
        };

        check();
    });
}

// The body for POST /api/popmart-accounts/connect: { cookie, expires_at }.
function toSession({ token, data }) {
    return {
        cookie: `${token.name}=${token.value}; ${data.name}=${data.value}`,
        // chrome.cookies gives expirationDate as seconds since epoch; convert to ISO for the API.
        expires_at: token.expirationDate ? new Date(token.expirationDate * 1000).toISOString() : null,
    };
}
