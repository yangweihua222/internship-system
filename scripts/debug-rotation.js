const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage();
  page.on('console', (m) => { if (m.type() === 'error') console.log('CONSOLE_ERR:', m.text().slice(0, 300)); });
  page.on('pageerror', (e) => console.log('PAGEERROR:', String(e).slice(0, 300)));
  page.on('response', async (r) => {
    if (r.url().includes('/api/')) {
      console.log('API', r.status(), r.url().replace('http://127.0.0.1:5173', ''));
    }
  });
  await page.goto('http://127.0.0.1:5173/login');
  await page.fill('input[placeholder="用户名"]', 'admin');
  await page.fill('input[placeholder="密码"]', 'admin123456');
  await page.click('button:has-text("登 录")');
  await page.waitForURL('**/dashboard', { timeout: 8000 });
  await page.goto('http://127.0.0.1:5173/rotation');
  await page.waitForTimeout(2500);
  const rows = await page.locator('.el-table__row').count();
  console.log('TABLE_ROWS:', rows);
  const cellText = await page.locator('.el-table').first().textContent();
  console.log('TABLE_TEXT:', (cellText || '').replace(/\s+/g, ' ').slice(0, 300));
  await browser.close();
})().catch((e) => { console.error('ERR', String(e).slice(0, 200)); process.exit(1); });
