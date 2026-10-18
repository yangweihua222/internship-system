/* AI 对话框 UI 测试：登录学生 → 打开 AI 助手 → 问轮转 → 断言流式回复包含科室名 */
const { chromium } = require('playwright-core');

const BASE = 'http://127.0.0.1:5173';
const results = [];
const ok = (name, pass, extra = '') => { const line = `${pass ? 'PASS' : 'FAIL'}  ${name}${extra ? ' => ' + extra : ''}`; results.push(line); console.log('  ' + line); };

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)); });
  page.on('pageerror', (e) => consoleErrors.push('PAGEERROR: ' + String(e).slice(0, 200)));

  // 登录学生
  await page.goto(BASE + '/login');
  await page.fill('input[placeholder="用户名"]', 'student01');
  await page.fill('input[placeholder="密码"]', 'student123');
  await page.click('button:has-text("登 录")');
  await page.waitForURL('**/dashboard', { timeout: 8000 }).catch(() => {});
  ok('学生登录', page.url().includes('/dashboard'), page.url());

  // 打开 AI 对话框
  await page.click('.ai-fab');
  await page.waitForTimeout(500);
  ok('对话框打开', await page.locator('.ai-panel').isVisible());

  // 等 SSE 流真正结束（真实 LLM 约 5~10s，mock 约 0.5s，故轮询而非死等固定时长）
  const waitStreamDone = async () => {
    await page.waitForTimeout(1200); // 让流先开始
    await page
      .waitForFunction(() => !document.querySelector('.ai-cursor'), null, { timeout: 90000 })
      .catch(() => {})
    await page.waitForTimeout(400)
  }

  // 快捷提问：轮转
  await page.click('.ai-suggestion:has-text("轮转")');
  await waitStreamDone()
  const reply = await page.locator('.ai-msg.assistant .ai-bubble').last().textContent();
  ok('轮转问答返回科室', (reply || '').includes('内科') || (reply || '').includes('轮转安排'), (reply || '').slice(0, 80));

  // 再问考勤
  await page.fill('.ai-input textarea', '我这个月考勤怎么样？');
  await page.click('button:has-text("发送")');
  await waitStreamDone()
  const reply2 = await page.locator('.ai-msg.assistant .ai-bubble').last().textContent();
  ok('考勤问答返回统计', (reply2 || '').includes('考勤统计'), (reply2 || '').slice(0, 80));

  // 刷新页面：历史对话与会话 ID 都应保留（localStorage 持久化）
  const beforeReload = await page.locator('.ai-msg').count()
  const sidBefore = await page.evaluate(() => localStorage.getItem('ai-chat-session:3'))
  await page.reload()
  await page.waitForURL('**/dashboard', { timeout: 8000 }).catch(() => {})
  await page.waitForTimeout(1200)
  await page.click('.ai-fab')
  await page.waitForTimeout(600)
  const afterReload = await page.locator('.ai-msg').count()
  const sidAfter = await page.evaluate(() => localStorage.getItem('ai-chat-session:3'))
  ok('刷新后保留历史对话', afterReload === beforeReload && afterReload > 0, `刷新前 ${beforeReload} 条 / 刷新后 ${afterReload} 条`)
  ok('刷新后会话 ID 不变', !!sidBefore && sidBefore === sidAfter, `${sidBefore} -> ${sidAfter}`)

  // 清空对话
  await page.click('.ai-header-btn'); // 第一个是清空
  await page.waitForTimeout(300);
  ok('清空对话', (await page.locator('.ai-msg').count()) === 0);
  ok('清空后 localStorage 已清', (await page.evaluate(() => localStorage.getItem('ai-chat-messages:3'))) === null);

  ok('控制台无报错', consoleErrors.length === 0, consoleErrors.join(' | '));

  await browser.close();
  const failed = results.filter((r) => r.startsWith('FAIL')).length;
  console.log(`\nAI UI 测试：${results.length - failed}/${results.length} 通过`);
  process.exit(failed ? 1 : 0);
})();
