/**
 * AI 教学助手对话接口
 *
 * 对接对象：本地真实 AI 服务（D:\简历\output\resume-ai-20260829\ai-service，FastAPI，自研 ReAct Agent + RAG）
 * 协议（不是 OpenAI 兼容，是它自己的）：
 *   POST {AI_BASE}/api/v1/chat/stream
 *   headers: X-Tenant-Id / X-User-Id / X-User-Num / X-Role / X-Internship-Token（实习系统用户 token，透传给 Laravel 网关）
 *   body:   { message, session_id, stream: true }
 *   SSE:    data: {"type":"steps","steps":[{tool,args,observation}]}
 *           data: {"type":"token","text":"..."}
 *           data: [DONE]
 *
 * AI 服务要查/写实习系统数据时，带着用户 token 回调 Laravel 工具网关
 * POST http://127.0.0.1:8000/api/v1/ai/tools/call，数据权限由 token 所属用户决定。
 */

const AI_BASE = import.meta.env.VITE_AI_API_URL || 'http://127.0.0.1:8001'

/** ai-service 返回的一步工具调用 */
export interface ToolStep {
  tool: string
  args: Record<string, unknown>
  observation: string
}

/** 把实习系统角色映射成 ai-service 的 RBAC 角色（admin | teacher | student） */
function aiRole(): string {
  const u = JSON.parse(localStorage.getItem('user') || 'null')
  const names: string[] = u?.roles?.map((r: { name: string }) => r.name) || []
  if (names.includes('admin') || names.includes('teaching-admin')) return 'admin'
  if (names.includes('teacher') || u?.type === 'teacher') return 'teacher'
  return 'student'
}

/**
 * 发起 SSE 流式对话。
 * onSteps: 工具调用步骤（可展示"助手查了什么"）；onDelta: 逐 token；onDone / onError。
 */
export async function chatStream(
  message: string,
  sessionId: string,
  onSteps: (steps: ToolStep[]) => void,
  onDelta: (text: string) => void,
  onDone: () => void,
  onError: (err: Error) => void,
): Promise<void> {
  const token = localStorage.getItem('token') || ''
  const u = JSON.parse(localStorage.getItem('user') || 'null')

  try {
    const resp = await fetch(`${AI_BASE}/api/v1/chat/stream`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Tenant-Id': 'capital-medical',
        'X-User-Id': String(u?.id ?? ''),
        'X-User-Num': u?.username || '',
        'X-Role': aiRole(),
        'X-Internship-Token': token,
      },
      body: JSON.stringify({ message, session_id: sessionId, stream: true }),
    })

    if (!resp.ok || !resp.body) {
      throw new Error(`AI 助手响应异常（HTTP ${resp.status}），请确认 AI 服务已启动（${AI_BASE}）`)
    }

    const reader = resp.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    for (;;) {
      const { done, value } = await reader.read()
      if (done) break
      buffer += decoder.decode(value, { stream: true })

      const lines = buffer.split('\n')
      buffer = lines.pop() || ''

      for (const line of lines) {
        const trimmed = line.trim()
        if (!trimmed.startsWith('data:')) continue
        const payload = trimmed.slice(5).trim()
        if (payload === '[DONE]') {
          onDone()
          return
        }
        try {
          const ev = JSON.parse(payload)
          if (ev.type === 'steps' && Array.isArray(ev.steps)) {
            onSteps(ev.steps)
          } else if (ev.type === 'token' && ev.text) {
            onDelta(ev.text)
          }
        } catch {
          // 忽略不完整的 JSON 片段
        }
      }
    }
    onDone()
  } catch (e) {
    onError(e instanceof Error ? e : new Error('无法连接 AI 助手，请确认它已启动'))
  }
}

/** AI 服务自带的可观测面板（每次调用的步骤与耗时），调试用 */
export const AI_PANEL_URL = `${AI_BASE}/observability`
