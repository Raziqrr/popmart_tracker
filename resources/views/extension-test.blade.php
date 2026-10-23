<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Extension test</title>
    <style>
        body { font: 14px system-ui, sans-serif; max-width: 480px; margin: 40px auto; }
        label { display: block; margin-bottom: 4px; }
        input { width: 100%; padding: 6px; margin-bottom: 12px; box-sizing: border-box; }
        button { padding: 8px 14px; margin: 0 8px 8px 0; }
        fieldset { margin-bottom: 16px; }
        #log { margin-top: 16px; white-space: pre-wrap; background: #f5f5f5; padding: 10px; border-radius: 4px; min-height: 60px; }
    </style>
</head>
<body>
    <h1>Extension test</h1>

    <fieldset>
        <legend>1. Log in to Popmart Tracker (Fortify)</legend>
        <label for="email">Email</label>
        <input id="email" type="email" autocomplete="username" />
        <label for="password">Password</label>
        <input id="password" type="password" autocomplete="current-password" />
        <button id="login">Log in</button>
        <button id="logout">Log out</button>
        <button id="me">Who am I?</button>
    </fieldset>

    <fieldset>
        <legend>2. Connect Pop Mart through the extension</legend>
        <label for="extensionId">Extension ID (from chrome://extensions)</label>
        <input id="extensionId" value="mnjfjfajfpndedemfilmabopcbidhboe" placeholder="e.g. abcdefghijklmnopabcdefghijklmnop" />
        <button id="ping">Check installed</button>
        <button id="connect">Connect Pop Mart account</button>
        <button id="clearCookies">Clear Pop Mart cookies</button>
    </fieldset>

    <fieldset>
        <legend>3. Use it (needs a live Pop Mart session)</legend>
        <label for="accountId">Popmart Account ID (to claim tasks for)</label>
        <input id="accountId" placeholder="filled in by Who am I? / Connect" />
        <button id="claimTasks">Claim eligible daily tasks</button>
    </fieldset>

    <div id="log">Waiting...</div>

    <script>
        const log = (msg) => {
            document.getElementById('log').textContent += '\n' + msg;
        };
        const extensionId = () => document.getElementById('extensionId').value.trim();

        // Same-origin JSON call with the login session and CSRF token, like lib/api.ts.
        const api = async (path, { method = 'GET', body } = {}) => {
            const response = await fetch(path, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            const data = await response.json().catch(() => null);
            log(`${method} ${path} → ${response.status} ${JSON.stringify(data)}`);
            return { ok: response.ok, data };
        };

        document.getElementById('login').addEventListener('click', async () => {
            const { ok } = await api('/login', { method: 'POST', body: {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
            } });
            // Logging in rotates the session (and its CSRF token): reload to pick up the new one.
            if (ok) location.reload();
        });

        document.getElementById('logout').addEventListener('click', async () => {
            await api('/logout', { method: 'POST' });
            location.reload();
        });

        document.getElementById('me').addEventListener('click', async () => {
            const { data } = await api('/api/me');
            if (data?.popmart) document.getElementById('accountId').value = data.popmart.account_id;
        });

        document.getElementById('ping').addEventListener('click', () => {
            if (!extensionId()) return log('Enter the extension ID first.');

            if (!window.chrome?.runtime?.sendMessage) {
                return log('chrome.runtime not available — are you using Chrome/Edge?');
            }

            chrome.runtime.sendMessage(extensionId(), { type: 'ping' }, (response) => {
                if (chrome.runtime.lastError) {
                    log('NOT detected: ' + chrome.runtime.lastError.message);
                } else {
                    log('Detected. Version: ' + response?.version);
                }
            });
        });

        document.getElementById('clearCookies').addEventListener('click', () => {
            if (!extensionId()) return log('Enter the extension ID first.');

            chrome.runtime.sendMessage(extensionId(), { type: 'clearCookies' }, (response) => {
                if (chrome.runtime.lastError) {
                    log('Error: ' + chrome.runtime.lastError.message);
                } else if (response?.error) {
                    log('Refused: ' + response.error);
                } else {
                    log('Cleared ' + response?.cleared + ' popmart.com cookies. Close the Pop Mart tab and log in fresh.');
                }
            });
        });

        document.getElementById('connect').addEventListener('click', () => {
            if (!extensionId()) return log('Enter the extension ID first.');

            const port = chrome.runtime.connect(extensionId());
            port.onMessage.addListener(async (msg) => {
                log('status: ' + msg.status);

                // The extension hands the session back; this page saves it with our login.
                if (msg.status === 'captured') {
                    const { ok, data } = await api('/api/popmart-accounts/connect', {
                        method: 'POST',
                        body: { cookie: msg.cookie, expires_at: msg.expires_at },
                    });
                    if (ok) document.getElementById('accountId').value = data.account_id;
                }
            });
            port.postMessage({ type: 'connect' });
            log('Sent connect request...');
        });

        document.getElementById('claimTasks').addEventListener('click', () => {
            const accountId = document.getElementById('accountId').value.trim();
            if (!accountId) return log('Enter a Popmart Account ID first.');

            api(`/api/popmart-accounts/${accountId}/claim-tasks`, { method: 'POST' });
        });
    </script>
</body>
</html>
