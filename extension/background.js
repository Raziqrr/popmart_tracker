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

// --- Detection handshake: the website pings us to check we're installed ---
chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
    if (message?.type === 'ping') {
        sendResponse({ installed: true, version: chrome.runtime.getManifest().version });
    } else if (message?.type === 'clearCookies') {
        clearPopmartCookies().then((count) => sendResponse({ cleared: count }));
    }

    return true; // keep the message channel open for the async sendResponse above
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

    port.postMessage({ status: 'sending_to_server' });

    try {
        await sendToServer(cookie);
        port.postMessage({ status: 'connected' });
    } catch (error) {
        port.postMessage({ status: 'error', message: String(error) });
    }
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

async function sendToServer({ token, data }) {
    const cookieHeader = `${token.name}=${token.value}; ${data.name}=${data.value}`;

    // chrome.cookies gives expirationDate as seconds since epoch; convert to ISO for the API.
    const expiresAt = token.expirationDate
        ? new Date(token.expirationDate * 1000).toISOString()
        : null;

    let response;

    try {
        response = await fetch(`${API_BASE_URL}/api/popmart-accounts/connect`, {
            method: 'POST',
            credentials: 'include', // rides along with the user's existing login session on our own site
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cookie: cookieHeader, expires_at: expiresAt }),
        });
    } catch {
        // fetch() itself throws (a generic "Failed to fetch") when the request never
        // reaches a server at all — wrong URL, server not running, DNS failure, etc.
        throw new Error(`Could not reach ${API_BASE_URL} — is the server running?`);
    }

    if (!response.ok) {
        const body = await response.text().catch(() => '');
        throw new Error(`Server rejected the captured session (HTTP ${response.status})${body ? `: ${body}` : ''}`);
    }
}
