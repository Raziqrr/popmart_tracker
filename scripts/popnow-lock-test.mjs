#!/usr/bin/env node
// POP NOW bulk-lock test — runs on YOUR Pop Mart account, so you run it.
//
// Locks each set's free boxes in ONE box/enterBox call, then checks every box locked
// so far (set/public_checkSetBoxLock, one call per box) and prints which are still
// yours and how long each has left. With --sets 2+ it also shows whether locking in a
// second set keeps the first set's holds.
//
// What we already know (tested live 2026-09-29): enterBox takes boxNos as an array,
// adds to existing holds, gives each new box its own ~300s timer and does NOT extend
// a box already held. public_assignSet RELEASES every held box, so this script only
// loads sets before locking anything.
//
// Scope and safety:
//   - 1 to 3 sets per run (--sets, default 1), with a pause between calls
//   - asks you to type "lock" before anything is locked (--yes skips)
//   - STOPS on: rate limiting, an expired/invalid session, or a request-format error
//   - every call is counted and appended to scripts/popnow-calls.log
//   - never pays; holds lapse on their own (5 minutes)
//
// Usage (PowerShell), from the repo root:
//   $env:POPMART_COOKIE = "<your two __Secure-ea-production.* cookies>"   # never commit or paste into chat
//   node scripts/popnow-lock-test.mjs --spu e0472f8c-ba9b-42a8-b569-98b1572825dc --dry-run
//   node scripts/popnow-lock-test.mjs --spu e0472f8c-ba9b-42a8-b569-98b1572825dc
// Options:
//   --sets N            sets to try, 1-3 (default 1)
//   --boxes N           max boxes to lock per set (default: every free box)
//   --set <setNo>       start with this set instead of an assigned one
//   --gap MS            pause between account calls (default 800)
//   --dry-run           load the set(s) and show the plan; lock nothing
//   --yes               skip the confirmation prompt

import { appendFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { createInterface } from 'node:readline/promises';
import { fileURLToPath } from 'node:url';

const API = 'https://prod-apac-api.popmart.com/rpc';
const LOG = join(dirname(fileURLToPath(import.meta.url)), 'popnow-calls.log');

const argv = process.argv.slice(2);
const opt = (name, fallback = null) => {
    const i = argv.indexOf(`--${name}`);
    return i === -1 ? fallback : argv[i + 1];
};
const flag = (name) => argv.includes(`--${name}`);

const spuId = opt('spu');
const setCount = Math.min(3, Math.max(1, Number(opt('sets', 1))));
const boxLimit = opt('boxes') ? Number(opt('boxes')) : Infinity;
const startSet = opt('set');
const gapMs = Number(opt('gap', 800));
const cookie = process.env.POPMART_COOKIE;

// Declared before the checks below: stop() reads it.
let calls = 0;

if (!spuId) stop('Missing --spu <product spuId>.');
if (!cookie) stop('Set POPMART_COOKIE first (see the top of this file).');

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function stop(message) {
    console.error(`\n■ STOPPED: ${message}`);
    if (calls) console.error(`Calls made: ${calls}. Log: ${LOG}`);
    process.exit(1);
}

async function call(path, payload) {
    calls++;
    const started = Date.now();
    let status = 0;
    let body = null;
    try {
        const res = await fetch(`${API}/draw/${path}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Origin: 'https://m.popmart.com',
                Referer: 'https://m.popmart.com/',
                'X-Area': 'MY',
                'X-Device-Type': 'web-mobile',
                Cookie: cookie,
            },
            body: JSON.stringify({ json: payload }),
        });
        status = res.status;
        body = await res.json().catch(() => null);
    } catch (error) {
        body = { message: `network error: ${error.message}` };
    }
    appendFileSync(LOG, `${new Date().toISOString()} #${calls} ${status} ${Date.now() - started}ms draw/${path} ${JSON.stringify(payload)}\n`);
    return { status, ok: status >= 200 && status < 300, data: body?.json ?? body };
}

/** Sorts a failed response into: stop now, or skip and carry on. */
function classify(result) {
    const message = JSON.stringify(result.data ?? '').toLowerCase();
    const issues = result.data?.data?.issues;
    const detail = issues?.length ? issues.map((i) => `${i.path}: ${i.message}`).join('; ') : (result.data?.message ?? message.slice(0, 200));

    if (result.status === 429 || /rate|too many|frequen|throttl|busy/.test(message)) return { action: 'stop', why: `rate limited (HTTP ${result.status}): ${detail}` };
    if (result.status === 401 || result.status === 403 || /unauthor|login|session|token|expired/.test(message)) return { action: 'stop', why: `session problem (HTTP ${result.status}): ${detail}` };
    if (result.status === 422 || result.data?.code === 'INPUT_VALIDATION_FAILED') return { action: 'stop', why: `request format rejected: ${detail}` };
    if (/lock|occupied|sold|taken|unavailable|not available/.test(message)) return { action: 'skip', why: detail };
    return { action: 'stop', why: `unexpected response (HTTP ${result.status}): ${detail}` };
}

// 1. Load the set(s). All loading happens here, before any lock: assignSet releases holds.
const sets = [];
for (let i = 0; i < setCount; i++) {
    const payload = i === 0 && startSet ? { spuId, setNo: startSet } : { spuId };
    let assigned = await call('set/public_assignSet', payload);
    // Ask again if Pop Mart hands back a set we already have.
    for (let retry = 0; assigned.ok && sets.some((s) => s.setNo === assigned.data.setNo) && retry < 2; retry++) {
        await sleep(gapMs);
        assigned = await call('set/public_assignSet', { spuId });
    }
    if (!assigned.ok) {
        const c = classify(assigned);
        if (c.action === 'stop') stop(`loading a set: ${c.why}`);
        break;
    }
    if (sets.some((s) => s.setNo === assigned.data.setNo)) {
        console.log(`Pop Mart kept assigning the same set; continuing with ${sets.length} set(s).`);
        break;
    }
    sets.push(assigned.data);
    await sleep(gapMs);
}

for (const set of sets) {
    console.log(`\nSet ${set.setNo}`);
    console.table(set.boxes.map((b) => ({ pos: b.position, boxNo: b.boxNo, status: b.status, locked: b.isLocked })));
}

const plan = sets.map((set) => ({
    set,
    boxes: set.boxes.filter((b) => b.status === 'available' && !b.isLocked).slice(0, boxLimit),
}));
const planned = plan.reduce((n, p) => n + p.boxes.length, 0);
console.log(`Plan: lock ${planned} box(es) across ${plan.length} set(s), one box/enterBox call per set, ${gapMs}ms apart.`);

if (flag('dry-run')) {
    console.log(`Dry run, nothing locked. Calls made: ${calls}. Log: ${LOG}`);
    process.exit(0);
}
if (planned === 0) stop('No free boxes to lock.');

if (!flag('yes')) {
    const rl = createInterface({ input: process.stdin, output: process.stdout });
    const answer = await rl.question('This locks boxes on your Pop Mart account. Type "lock" to continue: ');
    rl.close();
    if (answer.trim() !== 'lock') stop('Cancelled, nothing locked.');
}

// 2. Lock each set in one call; after each, re-check every box locked so far.
const heldSoFar = [];
const timeline = [];

for (const { set, boxes } of plan) {
    if (boxes.length === 0) continue;
    await sleep(gapMs);
    const result = await call('box/enterBox', { spuId, setNo: set.setNo, boxNos: boxes.map((b) => b.boxNo) });

    if (!result.ok) {
        const c = classify(result);
        console.log(`  set ${set.setNo}: ${c.action === 'skip' ? 'skipped' : 'FAILED'} — ${c.why}`);
        if (c.action === 'stop') {
            console.log('\nRaw box/enterBox response:', JSON.stringify(result.data, null, 2));
            printTimeline();
            stop(c.why);
        }
        continue;
    }
    console.log(`  set ${set.setNo}: enterBox 200, soonest hold ends in ${result.data?.lockRemainingSeconds}s`);
    heldSoFar.push(...boxes.map((b) => ({ setNo: set.setNo, position: b.position, boxNo: b.boxNo })));

    // Which boxes from this run are still ours, and for how long?
    const stillMine = [];
    const lost = [];
    for (const held of heldSoFar) {
        await sleep(gapMs);
        const check = await call('set/public_checkSetBoxLock', { spuId, setNo: held.setNo, boxNo: held.boxNo });
        if (!check.ok) {
            const c = classify(check);
            if (c.action === 'stop') {
                printTimeline();
                stop(`checking locks: ${c.why}`);
            }
            continue;
        }
        const label = `${held.setNo}#${held.position}`;
        if (check.data?.isLockedByMe) stillMine.push(`${label} (${check.data.lockRemainingSeconds}s)`);
        else lost.push(label);
    }
    timeline.push({ after_locking: set.setNo, held_by_me: stillMine.length, lost: lost.join(', ') || '—' });
    console.log(`  holding ${stillMine.length}/${heldSoFar.length}: ${stillMine.join(', ') || 'none'}`);
    if (lost.length) console.log(`  not held: ${lost.join(', ')}`);
}

printTimeline();
console.log(`\nDone. Calls made: ${calls}. Log: ${LOG}`);

function printTimeline() {
    if (timeline.length === 0) return;
    console.log('\nHolds after each set');
    console.table(timeline);
}
