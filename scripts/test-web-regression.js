const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { chromium } = require(require.resolve('playwright', { paths: [process.env.MRBAR_PLAYWRIGHT_MODULES || 'C:/Users/admin/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'] }));
const source = path.resolve(__dirname, '..');
const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'mrbar-web-qa-'));
const artifacts = path.join(path.dirname(source), '_qa_v14917');
async function run() {
  for (const dir of ['app', 'api', 'config', 'assets', 'custumers']) fs.cpSync(path.join(source, dir), path.join(fixture, dir), { recursive: true });
  for (const file of fs.readdirSync(source).filter(name => name.endsWith('.php'))) fs.copyFileSync(path.join(source, file), path.join(fixture, file));
  fs.mkdirSync(path.join(fixture, 'storage')); fs.mkdirSync(artifacts, { recursive: true });
  const listener = net.createServer(); await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
  const port = listener.address().port; await new Promise(resolve => listener.close(resolve));
  const url = `http://127.0.0.1:${port}`;
  const env = { ...process.env, MRBAR_QA_ROOT: fixture, MRBAR_FORCE_STAFF_HTTPS: '0' };
  for (const name of Object.keys(env)) if (name.startsWith('MRBAR_LINE_') || name.startsWith('MRBAR_SLIP_')) delete env[name];
  const server = spawn(process.env.MRBAR_PHP || 'php', ['-S', `127.0.0.1:${port}`, '-t', fixture, path.join(__dirname, 'qa-regression-router.php')], { cwd: fixture, env, windowsHide: true });
  let log = ''; server.stderr.on('data', chunk => { log += chunk; });
  let browser;
  try {
    for (let attempt = 0; attempt < 60; attempt++) {
      try { const response = await fetch(`${url}/__qa/init`); assert.equal(response.status, 200); break; } catch (error) { if (attempt === 59) throw error; await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    browser = await chromium.launch({ headless: true, channel: process.env.MRBAR_BROWSER_CHANNEL || 'msedge' });
    const anonymous = await browser.newContext();
    for (const route of ['line-login.php', 'customer-line-auth.php']) {
      const csrfRejected = await anonymous.request.post(`${url}/api/${route}`, { form: {} });
      assert.equal(csrfRejected.status(), 419, `missing CSRF in ${route}`);
    }
    await anonymous.close();
    const context = await browser.newContext(); const page = await context.newPage(); page.setDefaultTimeout(10000);
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${url}/__qa/customer`);
    await page.goto(`${url}/custumers/reserve.php?embed=1`);
    fs.writeFileSync(path.join(artifacts, 'customer-form.html'), await page.content());
    const csrf = await page.locator('[name=csrf]').inputValue(); const key = await page.locator('[name=booking_request_key]').inputValue();
    const today = await page.locator('[name=date]').inputValue();
    const fields = { csrf, booking_request_key: key, guest_name: 'QA Customer', phone: '0812345678', party_size: '2', date: today, time: '19:00', sales_employee_id: 'none', public_branch: '', preferred_table_id: '0' };
    const posted = await context.request.post(`${url}/custumers/reserve.php?embed=1`, { form: fields, maxRedirects: 0 });
    assert.equal(posted.status(), 303, await posted.text());
    assert.match(posted.headers().location, /receipt=[a-f0-9]{32}/);
    const second = await context.request.post(`${url}/custumers/reserve.php?embed=1`, { form: fields, maxRedirects: 0 }); assert.equal(second.status(), 303);
    await page.goto(url + posted.headers().location); await page.reload();
    assert.equal(await page.locator('.flash.ok').count(), 1);
    const state = await (await context.request.get(`${url}/__qa/state`)).json(); assert.equal(state.reservations.length, 2, 'refresh and duplicate POST only create one booking');
    await page.goto(`${url}/__qa/admin`); await page.goto(`${url}/reservations.php`);
    const adminCsrf = await page.locator('[name=csrf]').first().inputValue();
    for (const route of ['reservations.php', 'night-ops.php']) {
      const blocked = await context.request.post(`${url}/${route}`, { form: { csrf: adminCsrf, action: 'seat_reservation', id: '1', table_id: '1' } });
      assert.match(await blocked.text(), /ต้องตรวจและรับรองสลิปมัดจำก่อนรับลูกค้าเข้าร้าน/);
    }
    const after = await (await context.request.get(`${url}/__qa/state`)).json();
    assert.equal(after.checkins.length, 0); assert.equal(after.tables[0].status, 'available');
    await page.goto(`${url}/reservations.php`);
    await page.waitForFunction(() => document.querySelectorAll('.mr-live-notification').length === 8);
    await page.locator('.mr-live-notification-ack').first().click();
    await page.waitForFunction(() => document.querySelectorAll('.mr-live-notification').length === 7);
    await page.reload();
    await page.waitForFunction(() => document.querySelectorAll('.mr-live-notification').length === 7);
    await page.setViewportSize({ width: 390, height: 950 });
    await page.waitForFunction(() => !document.documentElement.classList.contains('mr-loading-init') && !document.querySelector('#mrPageLoader.is-visible'));
    await page.screenshot({ path: path.join(artifacts, 'notifications-390.png') });
    for (let count = 7; count > 0; count--) {
      await page.locator('.mr-live-notification-ack').first().click();
      await page.waitForFunction(expected => document.querySelectorAll('.mr-live-notification').length === expected, count - 1);
    }
    for (const width of [320, 390, 768, 1200, 1440]) {
      await page.setViewportSize({ width, height: 950 }); await page.goto(`${url}/reservations.php`);
      await page.locator('.reservation-row').first().waitFor();
      await page.waitForFunction(() => !document.documentElement.classList.contains('mr-loading-init') && !document.querySelector('#mrPageLoader.is-visible'));
      const dimensions = await page.evaluate(() => {
        const main = document.querySelector('.reservation-main');
        const problems = [];
        for (const row of document.querySelectorAll('.reservation-row')) {
          const a = row.querySelector('.status-action')?.getBoundingClientRect(); const b = row.querySelector('.seat-action')?.getBoundingClientRect();
          if (a && b && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top) problems.push('overlapping row actions');
        }
        return { viewport: innerWidth, document: document.documentElement.scrollWidth, main: main.scrollWidth, mainWidth: main.clientWidth, problems };
      });
      await page.screenshot({ path: path.join(artifacts, `reservations-${width}.png`), fullPage: true });
      assert.equal(dimensions.problems.length, 0, JSON.stringify(dimensions));
      assert.ok(dimensions.document <= width + 1 && dimensions.main <= dimensions.mainWidth + 1, `horizontal overflow at ${width}: ${JSON.stringify(dimensions)}`);
    }
    await page.goto(`${url}/__qa/device`); await page.goto(`${url}/login.php`);
    const pinCsrf = await page.locator('#pinForm [name=csrf]').inputValue();
    for (let attempt = 1; attempt <= 5; attempt++) {
      const failure = await context.request.post(`${url}/login.php`, { form: { csrf: pinCsrf, action: 'pin', pin: '999999' }, maxRedirects: 0 }); assert.equal(failure.status(), 200);
      const persisted = await (await context.request.get(`${url}/__qa/state`)).json();
      assert.equal(persisted.device_attempts, attempt === 5 ? 0 : attempt, 'PIN failure counter is persisted to disk');
      if (attempt === 5) assert.ok(Date.parse(persisted.device_lock) > Date.now());
    }
    const locked = await context.request.post(`${url}/login.php`, { form: { csrf: pinCsrf, action: 'pin', pin: '123456' }, maxRedirects: 0 }); assert.equal(locked.status(), 200);
    await page.goto(`${url}/__qa/unlock`); await page.goto(`${url}/login.php`);
    await page.locator('#pin').fill('123456'); await page.waitForURL('**/admin.php');
    const unlocked = await (await context.request.get(`${url}/__qa/state`)).json(); assert.equal(unlocked.device_lock, null);
    await page.goto(`${url}/__qa/expire-device`);
    const expired = await context.request.post(`${url}/login.php`, { form: { csrf: pinCsrf, action: 'pin', pin: '123456' }, maxRedirects: 0 });
    assert.equal(expired.status(), 200); assert.match(await expired.text(), /ไม่พบอุปกรณ์ที่จดจำ/);
    assert.deepEqual(errors, [], 'no browser JS errors');
    assert.doesNotMatch(log, /PHP (?:Fatal|Warning|Parse)/);
    console.log(`Web request, duplicate booking, deposit gates and mobile QA passed. Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    server.kill(); await new Promise(resolve => server.once('exit', resolve));
    fs.writeFileSync(path.join(artifacts, 'server.log'), log);
  }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
