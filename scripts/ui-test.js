/* UI 全链路添加测试：登录 → 加科室 → 加用户 → 建轮转 → 加学生 → 分段 */
const { chromium } = require('playwright-core');

const BASE = 'http://127.0.0.1:5173';
const stamp = Date.now().toString().slice(-6);
const results = [];
const ok = (name, pass, extra = '') => { const line = `${pass ? 'PASS' : 'FAIL'}  ${name}${extra ? ' => ' + extra : ''}`; results.push(line); console.log('  ' + line); };

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)); });
  page.on('pageerror', (e) => consoleErrors.push('PAGEERROR: ' + String(e).slice(0, 200)));

  const toast = async () => {
    const t = await page.locator('.el-message').last().textContent({ timeout: 3000 }).catch(() => '');
    return (t || '').trim();
  };

  // 登录
  await page.goto(BASE + '/login');
  await page.fill('input[placeholder="用户名"]', 'admin');
  await page.fill('input[placeholder="密码"]', 'admin123456');
  await page.click('button:has-text("登 录")');
  await page.waitForURL('**/dashboard', { timeout: 8000 }).catch(() => {});
  ok('登录跳转工作台', page.url().includes('/dashboard'), page.url());

  // 1. 科室管理：新增科室
  await page.goto(BASE + '/system/departments');
  await page.waitForTimeout(800);
  await page.click('button:has-text("新增一级科室")');
  await page.waitForTimeout(500);
  await page.fill('.el-dialog input[placeholder="科室名称"]', `UI测试科室${stamp}`);
  await page.click('.el-dialog button:has-text("保存")');
  await page.waitForTimeout(800);
  ok('新增科室', (await toast()).includes('成功') || (await page.locator(`text=UI测试科室${stamp}`).count()) > 0, await toast());

  // 2. 用户管理：新增学生
  await page.goto(BASE + '/system/users');
  await page.waitForTimeout(800);
  await page.click('button:has-text("新增用户")');
  await page.waitForTimeout(500);
  const dlg = page.locator('.el-dialog');
  await dlg.locator('input[placeholder="登录用户名"]').fill(`ui${stamp}`);
  await dlg.locator('.el-form-item:has-text("姓名") input').fill(`UI学生${stamp}`);
  await dlg.locator('input[placeholder="初始密码"]').fill('student123');
  // 类型选择（点“学生”）
  await dlg.locator('.el-select').first().click();
  await page.waitForTimeout(500);
  const clickOption = async (text) => page.evaluate((t) => {
    const dds = [...document.querySelectorAll('.el-select-dropdown')].filter((d) => getComputedStyle(d).display !== 'none');
    const dd = dds[dds.length - 1];
    if (!dd) return 'NO_DROPDOWN';
    const item = [...dd.querySelectorAll('.el-select-dropdown__item')].find((i) => i.textContent.trim() === t);
    if (!item) return 'NO_ITEM:' + [...dd.querySelectorAll('.el-select-dropdown__item')].map((i) => i.textContent.trim()).join(',');
    item.click();
    return 'OK';
  }, text);
  console.log('DEBUG type select:', await clickOption('学生'));
  // 角色选择（点“学生”）
  await dlg.locator('.el-select').nth(2).click();
  await page.waitForTimeout(500);
  console.log('DEBUG role select:', await clickOption('学生'));
  await page.keyboard.press('Escape');
  await dlg.locator('button:has-text("保存")').click();
  await page.waitForTimeout(1000);
  const t2 = await toast();
  ok('新增用户', t2.includes('成功') || (await page.locator(`text=UI学生${stamp}`).count()) > 0, t2);

  // 3. 轮转：新建轮转
  await page.goto(BASE + '/rotation');
  await page.waitForTimeout(800);
  await page.click('button:has-text("新建轮转")');
  await page.waitForTimeout(500);
  await page.fill('.el-dialog input[placeholder="如：2026年秋季轮转"]', `UI测试轮转${stamp}`);
  // 日期范围
  await page.click('.el-dialog .el-date-editor');
  await page.waitForTimeout(300);
  await page.fill('.el-dialog .el-range-input >> nth=0', '2026-10-19');
  await page.fill('.el-dialog .el-range-input >> nth=1', '2026-11-15');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(300);
  await page.click('.el-dialog button:has-text("保存")');
  await page.waitForTimeout(1500);
  const planOk = (await page.locator(`text=UI测试轮转${stamp}`).count()) > 0;
  ok('新建轮转', planOk, await toast());
  if (!planOk) {
    await page.reload();
    await page.waitForTimeout(1500);
    console.log('DEBUG after reload, plan visible:', await page.locator(`text=UI测试轮转${stamp}`).count());
  }

  // 4. 轮转：打开管理抽屉加学生
  const row = page.locator('tr', { hasText: `UI测试轮转${stamp}` });
  await row.locator('button:has-text("学生/分段")').click();
  await page.waitForTimeout(800);
  await page.click('.el-drawer .el-select');
  await page.waitForTimeout(400);
  const memberDd = page.locator('.el-select-dropdown').filter({ visible: true }).last();
  const opts = memberDd.locator('.el-select-dropdown__item');
  const n = await opts.count();
  if (n > 0) { await opts.first().click(); }
  await page.keyboard.press('Escape');
  await page.click('.el-drawer button:has-text("添加")');
  await page.waitForTimeout(800);
  ok('轮转添加学生', (await toast()).includes('成功') || (await page.locator('.el-drawer .el-tag').count()) > 0, await toast());

  // 关闭抽屉
  await page.keyboard.press('Escape');

  console.log('===== UI 测试结果 =====');
  results.forEach((r) => console.log('  ' + r));
  console.log('===== 控制台错误 =====');
  console.log(consoleErrors.length ? consoleErrors.slice(0, 10).join('\n') : '（无）');

  await browser.close();
})().catch((e) => { console.error('SCRIPT_ERROR', String(e).slice(0, 300)); process.exit(1); });
