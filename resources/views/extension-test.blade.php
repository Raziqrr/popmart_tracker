<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Extension test</title>
    <style>
        body { font: 14px system-ui, sans-serif; max-width: 480px; margin: 40px auto; }
        label { display: block; margin-bottom: 4px; }
        input { width: 100%; padding: 6px; margin-bottom: 12px; box-sizing: border-box; }
        button { padding: 8px 14px; margin-right: 8px; }
        #log { margin-top: 16px; white-space: pre-wrap; background: #f5f5f5; padding: 10px; border-radius: 4px; min-height: 60px; }
    </style>
</head>
<body>
    <h1>Extension test</h1>

    <label for="extensionId">Extension ID (from chrome://extensions)</label>
    <input id="extensionId" value="mnjfjfajfpndedemfilmabopcbidhboe" placeholder="e.g. abcdefghijklmnopabcdefghijklmnop" />

    <button id="ping">Check installed</button>
    <button id="connect">Connect Pop Mart account</button>
    <button id="clearCookies">Clear Pop Mart cookies</button>

    <br><br>
    <label for="accountId">Popmart Account ID (to claim tasks for)</label>
    <input id="accountId" placeholder="e.g. 9" />
    <button id="claimTasks">Claim eligible daily tasks</button>

    <div id="log">Waiting...</div>

    <script>
        const log = (msg) => {
            document.getElementById('log').textContent += '\n' + msg;
        };

        document.getElementById('ping').addEventListener('click', () => {
            const extensionId = document.getElementById('extensionId').value.trim();
            if (!extensionId) return log('Enter the extension ID first.');

            if (!window.chrome?.runtime?.sendMessage) {
                return log('chrome.runtime not available — are you using Chrome/Edge?');
            }

            chrome.runtime.sendMessage(extensionId, { type: 'ping' }, (response) => {
                if (chrome.runtime.lastError) {
                    log('NOT detected: ' + chrome.runtime.lastError.message);
                } else {
                    log('Detected. Version: ' + response?.version);
                }
            });
        });

        document.getElementById('clearCookies').addEventListener('click', () => {
            const extensionId = document.getElementById('extensionId').value.trim();
            if (!extensionId) return log('Enter the extension ID first.');

            chrome.runtime.sendMessage(extensionId, { type: 'clearCookies' }, (response) => {
                if (chrome.runtime.lastError) {
                    log('Error: ' + chrome.runtime.lastError.message);
                } else {
                    log('Cleared ' + response?.cleared + ' popmart.com cookies. Close the Pop Mart tab and log in fresh.');
                }
            });
        });

        document.getElementById('claimTasks').addEventListener('click', () => {
            const accountId = document.getElementById('accountId').value.trim();
            if (!accountId) return log('Enter a Popmart Account ID first.');

            fetch(`/api/popmart-accounts/${accountId}/claim-tasks`, { method: 'POST' })
                .then((r) => r.json())
                .then((data) => log('claim-tasks result: ' + JSON.stringify(data.results)))
                .catch((err) => log('claim-tasks error: ' + err));
        });

        document.getElementById('connect').addEventListener('click', () => {
            const extensionId = document.getElementById('extensionId').value.trim();
            if (!extensionId) return log('Enter the extension ID first.');

            const port = chrome.runtime.connect(extensionId);
            port.onMessage.addListener((msg) => log('status: ' + JSON.stringify(msg)));
            port.postMessage({ type: 'connect' });
            log('Sent connect request...');
        });
    </script>
</body>
</html>
