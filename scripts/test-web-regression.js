const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { chromium } = require(require.resolve('playwright', { paths: [process.env.MRBAR_PLAYWRIGHT_MODULES || 'C:/Users/admin/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'] }));
const source = path.resolve(__dirname, '..');
const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'mrbar-web-qa-'));
const artifacts = process.env.MRBAR_QA_ARTIFACTS || path.join(path.dirname(source), '_qa_v14918');
async function run() {
  for (const dir of ['app', 'api', 'config', 'assets', 'custumers']) fs.cpSync(path.join(source, dir), path.join(fixture, dir), { recursive: true });
  for (const file of fs.readdirSync(source).filter(name => name.endsWith('.php'))) fs.copyFileSync(path.join(source, file), path.join(fixture, file));
  fs.mkdirSync(path.join(fixture, 'scripts'));
  fs.copyFileSync(path.join(source, 'scripts', 'line-outbox-worker.php'), path.join(fixture, 'scripts', 'line-outbox-worker.php'));
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
    const publicWorker = await anonymous.request.get(`${url}/scripts/line-outbox-worker.php`);
    assert.equal(publicWorker.status(), 404, 'CLI worker is inaccessible over HTTP');
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
    assert.equal(state.line_outbox.length, 1, 'booking and its customer message commit atomically once');
    assert.equal(state.line_outbox[0].status, 'not_configured', 'missing token never loses queued payload');
    const failedWorker = await (await context.request.get(`${url}/__qa/outbox-worker?status=503`)).json();
    assert.equal(failedWorker.processed, 1);
    const failedState = await (await context.request.get(`${url}/__qa/state`)).json();
    assert.equal(failedState.line_outbox[0].status, 'retrying');
    assert.equal(failedState.reservations[1].line_customer_notification_status, 'retrying', 'worker result persists to booking receipt');
    const coolingWorker = await (await context.request.get(`${url}/__qa/outbox-worker?status=409`)).json();
    assert.equal(coolingWorker.processed, 0, 'server enforces retry cooldown');
    await context.request.get(`${url}/__qa/outbox-due`);
    const acceptedWorker = await (await context.request.get(`${url}/__qa/outbox-worker?status=409`)).json();
    assert.deepEqual(acceptedWorker.calls, failedWorker.calls, 'persisted worker retry uses exact original key and message');
    const acceptedState = await (await context.request.get(`${url}/__qa/state`)).json();
    assert.equal(acceptedState.line_outbox[0].status, 'sent');
    assert.equal(acceptedState.reservations[1].line_customer_notification_status, 'sent');
    assert.equal((await (await context.request.get(`${url}/__qa/outbox-worker?status=200`)).json()).processed, 0, 'accepted message cannot be sent again');
    await page.goto(`${url}/__qa/admin`); await page.goto(`${url}/reservations.php`);
    const adminCsrf = await page.locator('[name=csrf]').first().inputValue();
    const confirmFields = { csrf: adminCsrf, action: 'reservation_status', id: '2', status: 'confirmed' };
    await context.request.post(`${url}/reservations.php`, { form: confirmFields });
    const confirmationState = await (await context.request.get(`${url}/__qa/state`)).json();
    assert.equal(confirmationState.line_outbox.length, 2, 'confirmation creates a separate durable message');
    assert.equal(confirmationState.line_outbox[1].kind, 'confirmation');
    await context.request.post(`${url}/reservations.php`, { form: confirmFields });
    const reconfirmationState = await (await context.request.get(`${url}/__qa/state`)).json();
    assert.equal(reconfirmationState.line_outbox.length, 2, 'saving confirmed status twice never queues duplicate');
    await page.goto(`${url}/reservations.php`);
    assert.equal(await page.locator('.line-delivery-row').count(), 2, 'staff can inspect receipt and confirmation delivery separately');
    assert.equal(await page.locator('.line-delivery-row').filter({ hasText: 'ยืนยันให้ลูกค้า' }).locator('button').textContent(), 'ลองส่งใหม่');
    assert.ok(await page.locator('.line-delivery-row').filter({ hasText: 'ยืนยันให้ลูกค้า' }).locator('button').isDisabled(), 'no unusable retry when token is missing');
    const missingRetryCsrf = await context.request.post(`${url}/reservations.php`, { form: { action: 'line_delivery_retry', job_id: confirmationState.line_outbox[1].id } });
    assert.equal(missingRetryCsrf.status(), 419, 'retry delivery requires nonempty CSRF');
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
    const designReport = [];
    const designRoutes = ['admin.php', 'admin-tools.php', 'reservations.php', 'reservation-settings.php', 'night-ops.php', 'employees.php', 'workforce-schedule.php', 'payroll-attendance.php', 'hr-approval-center.php', 'hr-approval-center.php?view=exception', 'admin-leaves.php?embed=1', 'workforce-exceptions.php?embed=1', 'customers.php', 'customer-web.php', 'system-reports.php', 'admin-manage.php', 'role-permissions.php', 'settings.php', 'line-settings.php', 'account.php', 'employee-time.php', 'employee-calendar.php', 'employee-income.php'];
    for (const width of [390, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      for (const route of designRoutes) {
        const response = await page.goto(`${url}/${route}`);
        await page.waitForFunction(() => !document.documentElement.classList.contains('mr-loading-init') && !document.querySelector('#mrPageLoader.is-visible'));
        await page.waitForTimeout(350);
        const measurements = await page.evaluate(() => ({
          width: innerWidth, documentWidth: document.documentElement.scrollWidth,
          overflow: Array.from(document.querySelectorAll('main *')).filter(el => {
            const box = el.getBoundingClientRect();
            if (!box.width || box.left >= innerWidth || box.right <= 0) return false;
            for (let parent = el.parentElement; parent; parent = parent.parentElement) {
              if (['auto', 'scroll'].includes(getComputedStyle(parent).overflowX)) return false;
            }
            return box.right > innerWidth + 2 || box.left < -2;
          }).slice(0, 10).map(el => `${el.tagName}.${el.className}`)
        }));
        designReport.push({ route, width, status: response.status(), finalUrl: page.url().replace(url, ''), ...measurements });
        await page.screenshot({ path: path.join(artifacts, `design-${route.replace(/[^a-z0-9]/gi, '-')}-${width}.png`), fullPage: true });
      }
    }
    fs.writeFileSync(path.join(artifacts, 'design-report.json'), JSON.stringify(designReport, null, 2));
    assert.ok(designReport.every(row => row.status === 200), 'all audited screens must render');
    assert.ok(designReport.every(row => row.documentWidth <= row.width + 1 && row.overflow.length === 0), 'audited pages must fit the viewport; see design-report.json');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${url}/admin.php`);
    await page.locator('#adminMobileLauncher').click();
    assert.equal(await page.locator('#sidebar').evaluate(el => el.inert), false);
    assert.equal(await page.locator('#adminMobileLauncher').getAttribute('aria-expanded'), 'true');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#sidebar').evaluate(el => el.inert), true);
    assert.equal(await page.locator('#adminMobileLauncher').evaluate(el => el === document.activeElement), true);
    await page.goto(`${url}/admin-leaves.php?embed=1`);
    assert.equal(await page.locator('.mr-theme-toggle').count(), 0, 'embedded approvals use parent theme without a duplicate toggle');
    for (const width of [320, 768]) {
      await page.setViewportSize({ width, height: 844 });
      for (const route of ['employee-time.php', 'employee-calendar.php', 'employee-income.php', 'hr-approval-center.php?view=exception']) {
        await page.goto(`${url}/${route}`);
        await page.waitForFunction(() => !document.documentElement.classList.contains('mr-loading-init'));
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${route} fits ${width}`);
        await page.screenshot({ path: path.join(artifacts, `focused-${route.replace(/[^a-z0-9]/gi, '-')}-${width}.png`), fullPage: true });
      }
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${url}/hr-approval-center.php?view=exception`);
    const embedded = page.frameLocator('iframe[data-auto-height]');
    await embedded.locator('.wx-shell').waitFor();
    assert.equal(await embedded.locator('.mr-theme-toggle').count(), 0);
    await page.waitForFunction(() => {
      const frame = document.querySelector('iframe[data-auto-height]');
      return frame.style.height && frame.getBoundingClientRect().height >= frame.contentDocument.querySelector('main').scrollHeight;
    });
    await page.locator('.mr-theme-toggle').click();
    await page.waitForFunction(() => document.querySelector('iframe').contentDocument.documentElement.dataset.mrTheme === 'light');
    await page.screenshot({ path: path.join(artifacts, 'hr-approval-light-390.png'), fullPage: true });
    await page.locator('.mr-theme-toggle').click();
    await page.goto(`${url}/employee-calendar.php`);
    assert.equal(await page.locator('.mobile-dock').evaluate(el => getComputedStyle(el).position), 'fixed');
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
    assert.deepEqual(log.split('\n').filter(line => /PHP (?:Fatal|Warning|Parse)/.test(line)), [], 'no PHP errors');
    console.log(`Web request, duplicate booking, deposit gates and mobile QA passed. Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    server.kill(); await new Promise(resolve => server.once('exit', resolve));
    fs.writeFileSync(path.join(artifacts, 'server.log'), log);
  }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
