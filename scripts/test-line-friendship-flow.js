const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'line-flow-v14901.js'), 'utf8');
const reserveSource = fs.readFileSync(path.join(__dirname, '..', 'custumers', 'reserve.php'), 'utf8');
const authSource = fs.readFileSync(path.join(__dirname, '..', 'api', 'customer-line-auth.php'), 'utf8');
const lineLinkSource = fs.readFileSync(path.join(__dirname, '..', 'line-link.php'), 'utf8');

assert.match(reserveSource, /https:\/\/liff\.line\.me\//, 'customer booking must enter through the LIFF URL');
assert.match(reserveSource, /mrbar_customer_line_booking_access\(\$lineIdentity,\$customerOa\['configured'\]\)/, 'booking page applies the LINE OA gate');
assert.match(authSource, /mrbar_line_customer_friendship_result\(/, 'server auth endpoint verifies friendship against LINE directly');
assert.doesNotMatch(authSource, /\$_POST\[\x27friend_status\x27\]/, 'server auth endpoint does not trust a browser-supplied friend flag');
assert.match(lineLinkSource, /parse_url\(\$liffState,PHP_URL_QUERY\)/, 'LIFF launch flow is restored from liff.state before rendering');
assert.match(lineLinkSource, /<base href="<\?=h\(branch_public_base\(\)\)\?>\/">/, 'LIFF path routing keeps internal assets and actions rooted correctly');

async function runFlow({ configured = true, friendship = [], friendshipError = false, requestFriendshipError = false, audience = '2011745152', channelId = '2011745152', authResponse = { ok: true, status: 200, body: JSON.stringify({ ok: true, redirect: '/custumers/reserve.php' }) } }) {
  const events = [];
  const storage = new Map();
  const action = { disabled: false, addEventListener(name, callback) { this[name] = callback; } };
  const status = { dataset: {}, textContent: '' };
  const panel = {
    dataset: { lineFlow: 'customer_booking', lineLiffId: '2011745152-yvbsYagI', lineChannelId: channelId, lineCsrf: 'csrf', lineEndpoint: '/api/customer-line-auth.php', lineReturn: '/custumers/reserve.php', lineOaConfigured: configured ? '1' : '0' },
    querySelector(selector) { return selector === '[data-line-action]' ? action : selector === '[data-line-unlink]' ? null : status; },
  };
  let friendshipIndex = 0;
  const liff = {
    init: async () => {}, isLoggedIn: () => true,
    getDecodedIDToken: () => { events.push('readAudience'); return { aud: audience }; },
    getIDToken: () => { events.push('getIDToken'); return 'verified-id-token'; },
    getAccessToken: () => { events.push('getAccessToken'); return 'verified-access-token'; },
    getFriendship: async () => { events.push('getFriendship'); if (friendshipError) throw new Error('LIFF friendship unavailable'); return { friendFlag: friendship[friendshipIndex++] ?? false }; },
    requestFriendship: async () => { events.push('requestFriendship'); if (requestFriendshipError) throw new Error('LIFF prompt unavailable'); },
  };
  const window = { liff, sessionStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) }, location: { assign: url => events.push(`redirect:${url}`) } };
  const context = {
    window, liff, document: { querySelector: () => panel }, sessionStorage: window.sessionStorage,
    URLSearchParams, fetch: async (_url, options) => {
      events.push(`auth:${new URLSearchParams(options.body).has('access_token') ? 'access-token' : 'no-access-token'}`);
      return { ok: authResponse.ok, status: authResponse.status, text: async () => authResponse.body };
    },
  };
  vm.runInNewContext(source, context);
  await new Promise(resolve => setImmediate(resolve));
  await action.click();
  return { events, statusMessage: status.textContent, statusState: status.dataset.state };
}

(async () => {
  const alreadyFriend = await runFlow({ friendship: [true] });
  assert.deepEqual(alreadyFriend.events, ['getFriendship', 'readAudience', 'getIDToken', 'getAccessToken', 'auth:access-token', 'redirect:/custumers/reserve.php']);
  const addedDuringFlow = await runFlow({ friendship: [false, true] });
  assert.deepEqual(addedDuringFlow.events, ['getFriendship', 'requestFriendship', 'getFriendship', 'readAudience', 'getIDToken', 'getAccessToken', 'auth:access-token', 'redirect:/custumers/reserve.php']);
  const friendshipDeclined = await runFlow({ friendship: [false, false] });
  assert.deepEqual(friendshipDeclined.events, ['getFriendship', 'requestFriendship', 'getFriendship']);
  assert.match(friendshipDeclined.statusMessage, /ต้องเพิ่มเพื่อน LINE OA/);
  assert.equal(friendshipDeclined.statusState, 'error');
  const noConfiguredOa = await runFlow({ configured: false });
  assert.deepEqual(noConfiguredOa.events, []);
  assert.match(noConfiguredOa.statusMessage, /ยังไม่ได้ตั้งค่าลิงก์ OA/);
  const friendshipUnavailable = await runFlow({ friendshipError: true });
  assert.deepEqual(friendshipUnavailable.events, ['getFriendship']);
  assert.match(friendshipUnavailable.statusMessage, /ตรวจสถานะเพื่อน OA ไม่ได้/);
  const promptUnavailable = await runFlow({ friendship: [false], requestFriendshipError: true });
  assert.deepEqual(promptUnavailable.events, ['getFriendship', 'requestFriendship']);
  assert.match(promptUnavailable.statusMessage, /เปิดหน้าต่างเพิ่มเพื่อน OA ไม่สำเร็จ/);
  const channelMismatch = await runFlow({ friendship: [true], audience: '2011767003' });
  assert.deepEqual(channelMismatch.events, ['getFriendship', 'readAudience']);
  const emptyServerResponse = await runFlow({ friendship: [true], authResponse: { ok: false, status: 500, body: '' } });
  assert.match(emptyServerResponse.statusMessage, /HTTP 500/);
  assert.equal(emptyServerResponse.statusState, 'error');
  console.log('LINE customer friendship flow checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
