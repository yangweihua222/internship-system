/**
 * mock-ai.js — 本地 AI 教学助手的 mock 实现（OpenAI 兼容协议）
 *
 * 用途：在真正的 AI 助手（LLM Agent）写好之前，用于全链路联调：
 *   浏览器 → mock(8001) → Laravel 工具网关(8000) → MySQL
 *
 * 行为：按关键词选择工具并真实回调 Laravel，把结果整理成中文回答后 SSE 流式返回。
 * 启动：node scripts/mock-ai.js   （监听 127.0.0.1:8001，已开 CORS）
 */
const http = require('http')

const LARAVEL = 'http://127.0.0.1:8000'

// 最近的调用记录（内存，最多 30 条），供调试面板查看
const callLogs = []
function logCall(entry) {
  callLogs.unshift({ time: new Date().toLocaleString('zh-CN', { hour12: false }), ...entry })
  if (callLogs.length > 30) callLogs.pop()
}

const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

function panelHtml() {
  const rows = callLogs.map((l, i) => `
    <div class="card">
      <div class="head"><span class="time">#${callLogs.length - i} · ${esc(l.time)}</span>
        <span class="q">问：${esc(l.question)}</span></div>
      <div class="row"><b>命中工具：</b><code>${esc(l.tool || '（无，走通用回答）')}</code>
        ${l.arguments ? `<b>参数：</b><code>${esc(JSON.stringify(l.arguments))}</code>` : ''}</div>
      ${l.tool ? `<div class="row"><b>Laravel 返回：</b><pre>${esc(JSON.stringify(l.result, null, 2))}</pre></div>` : ''}
      <div class="row"><b>最终回答：</b><pre class="ans">${esc(l.answer)}</pre></div>
    </div>`).join('')
  return `<!DOCTYPE html><html lang="zh"><head><meta charset="utf-8">
<meta http-equiv="refresh" content="5">
<title>AI 助手调试面板</title>
<style>
body{font-family:system-ui,sans-serif;background:#f5f6f8;margin:0;padding:20px}
h1{font-size:18px} .tip{color:#888;font-size:12px;margin-bottom:14px}
.card{background:#fff;border:1px solid #e8e8e8;border-radius:8px;padding:12px 14px;margin-bottom:12px}
.head{margin-bottom:6px}.time{color:#999;font-size:12px;margin-right:10px}.q{font-weight:600}
.row{margin:6px 0;font-size:13px} code{background:#f0f2f5;padding:1px 6px;border-radius:4px}
pre{background:#f8f9fa;border:1px solid #eee;border-radius:6px;padding:8px;font-size:12px;overflow:auto;max-height:240px;white-space:pre-wrap}
.ans{background:#ecf5ff;border-color:#d9ecff}
.empty{color:#999;text-align:center;padding:40px}
</style></head><body>
<h1>AI 教学助手 · 调试面板（mock）</h1>
<div class="tip">每 5 秒自动刷新 · 保留最近 30 次调用 · 这里能看到每次问答命中的工具、参数和 Laravel 返回的原始数据</div>
${rows || '<div class="empty">还没有调用记录，去系统里问 AI 助手几个问题试试</div>'}
</body></html>`
}

// 关键词 → [工具名, 参数生成器, 回答模板]
const RULES = [
  [/轮转|哪个科室|科室/, 'get_my_rotation', () => ({}), (d) => {
    if (!d.list.length) return '你目前还没有轮转安排。'
    const lines = d.list.map((s) => `${s.start_date} ~ ${s.end_date}：${s.department}${s.is_current ? '（当前）' : ''}`)
    return `你的轮转安排如下：\n${lines.join('\n')}`
  }],
  [/值班/, 'get_my_duty', () => ({}), (d) => {
    if (!d.list.length) return `你 ${d.month} 没有值班安排。`
    return `你 ${d.month} 的值班安排：\n` + d.list.map((x) => `${x.date} ${x.shift}（${x.department || '-'}）`).join('\n')
  }],
  [/考勤|缺勤|打卡|迟到/, 'get_attendance_stats', () => ({}), (d) =>
    `你 ${d.month} 考勤统计：应出勤 ${d.scheduled_days} 天，正常 ${d.stat.normal}，迟到 ${d.stat.late}，早退 ${d.stat.early}，缺勤 ${d.stat.absent}，请假 ${d.stat.leave}。`],
  [/待办|要做什么|任务/, 'get_my_tasks_summary', () => ({}), (d) =>
    `你的待办：待评审病历 ${d.unreviewed_patient_cases} 份，进行中的申请 ${d.my_pending_processes} 个，本月剩余值班 ${d.duty_left_this_month} 次${d.pending_approvals !== undefined ? `，待你审批 ${d.pending_approvals} 单` : ''}。`],
  [/教学活动|讲座|培训/, 'list_teaching_events', () => ({}), (d) => {
    if (!d.list.length) return '最近没有教学活动。'
    return '最近的教学活动：\n' + d.list.map((e) => `${e.title}（${e.start_at}，${e.location || '地点待定'}）`).join('\n')
  }],
  [/通知|公告/, 'list_notices', () => ({}), (d) => {
    if (!d.list.length) return '暂无通知公告。'
    return '最新通知：\n' + d.list.map((n) => `《${n.title}》`).join('\n')
  }],
  [/考核|成绩|评价/, 'get_my_evaluations', () => ({}), (d) => {
    if (!d.list.length) return '你还没有考核成绩记录。'
    return '你的考核成绩：\n' + d.list.map((e) => `${e.template || e.period}：${e.total_score} 分${e.comment ? `，评语：${e.comment}` : ''}`).join('\n')
  }],
  [/病历/, 'get_my_patient_cases', () => ({}), (d) => {
    if (!d.list.length) return '你还没有提交病历记录。'
    return '你的病历记录：\n' + d.list.map((c) => `《${c.title}》（${c.department || '-'}，${c.status}${c.score != null ? `，${c.score} 分` : ''}）`).join('\n')
  }],
  [/审批/, 'get_pending_approvals', () => ({}), (d) => {
    if (!d.list || !d.list.length) return '没有待你审批的单子。'
    return `有 ${d.list.length} 单待你审批：\n` + d.list.map((p) => p.title).join('\n')
  }],
  [/请假/, 'submit_leave', () => {
    const d = new Date(Date.now() + 86400000).toISOString().slice(0, 10)
    return { leave_type: '事假', start_date: d, end_date: d, reason: '家中有事（mock 助手自动填写）' }
  }, (d) => `已为你提交请假申请（单号 #${d.process_id}），等待教学管理员审批。`],
]

function sseSend(res, text) {
  // 按字符切分模拟打字机
  const chunk = (t) => res.write(`data: ${JSON.stringify({ choices: [{ delta: { content: t } }] })}\n\n`)
  for (const ch of text) chunk(ch)
  res.write('data: [DONE]\n\n')
  res.end()
}

async function callTool(token, tool, args) {
  const resp = await fetch(`${LARAVEL}/api/v1/ai/tools/call`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` },
    body: JSON.stringify({ tool, arguments: args }),
  })
  return resp.json()
}

const server = http.createServer(async (req, res) => {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*')
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
  if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return }

  if (req.method === 'GET' && (req.url === '/' || req.url === '/panel')) {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' })
    res.end(panelHtml())
    return
  }

  if (req.method === 'POST' && req.url === '/v1/chat/completions') {
    let body = ''
    req.on('data', (c) => (body += c))
    req.on('end', async () => {
      const payload = JSON.parse(body)
      const userMsg = [...payload.messages].reverse().find((m) => m.role === 'user')?.content || ''
      const token = payload.user_token || ''

      res.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache', Connection: 'keep-alive' })

      const rule = RULES.find(([re]) => re.test(userMsg))
      if (!rule) {
        const answer = `（mock 助手）我收到了你的问题：「${userMsg}」。这类教学问答在正式版里会走 RAG 知识库回答；如果你想查系统数据，可以问我"我的轮转""我的值班""考勤统计""帮我请假"等。`
        logCall({ question: userMsg, tool: null, answer })
        sseSend(res, answer)
        return
      }

      const [, tool, argFn, render] = rule
      const args = argFn()
      const result = await callTool(token, tool, args)
      if (!result.ok) {
        const answer = `查询失败：${result.error}`
        logCall({ question: userMsg, tool, arguments: args, result, answer })
        sseSend(res, answer)
        return
      }
      const answer = render(result.data)
      logCall({ question: userMsg, tool, arguments: args, result: result.data, answer })
      sseSend(res, answer)
    })
    return
  }

  res.writeHead(404, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify({ error: 'not found' }))
})

server.listen(8001, '127.0.0.1', () => console.log('mock AI assistant listening on http://127.0.0.1:8001'))
