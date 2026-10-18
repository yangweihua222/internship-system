# AI 教学助手对接设计

> 版本：v1.0（2026-10-18）
> 状态：待评审 → 通过后进入实现

## 1. 目标

在实习系统（internship-web）里加一个 AI 教学助手对话框，学生/老师可以直接用自然语言：

- 查自己的轮转安排、值班表、考勤/缺勤统计、考核成绩、教学活动、通知公告
- 查待办：待审批单（老师）、待写病历/报告（学生）
- 直接操作：提交请假申请
- 教学问答：基于知识库的 RAG 问答（由 AI 助手侧独立完成）

## 2. 总体架构

```
┌─────────────────────────────────────────────────────────────┐
│ 浏览器（internship-web, 127.0.0.1:5173）                      │
│                                                              │
│  AiChat.vue 对话框                                            │
│   │                                                          │
│   │ ① POST /v1/chat/completions (SSE 流式)                   │
│   │    带 messages + tools 定义 + user_token（Sanctum）       │
│   ▼                                                          │
└─────────────────────────────────────────────────────────────┘
             │ 直连（不经 Laravel 转发）
             ▼
┌─────────────────────────────────────────────────────────────┐
│ 本地 AI 教学助手（127.0.0.1:8001，OpenAI 兼容协议）            │
│                                                              │
│  - LLM 对话 + Agent 工具调用决策                              │
│  - RAG 检索（教学知识库，本侧独立完成，与实习系统无关）          │
│   │                                                          │
│   │ ② 需要查/操作系统数据时：                                 │
│   │    POST http://127.0.0.1:8000/api/v1/ai/tools/call       │
│   │    Header: Authorization: Bearer <user_token>            │
│   │    Body: { "tool": "get_my_rotation", "arguments": {} }  │
│   ▼                                                          │
└─────────────────────────────────────────────────────────────┘
             ▼
┌─────────────────────────────────────────────────────────────┐
│ internship-server（Laravel, 127.0.0.1:8000）                  │
│                                                              │
│  AiToolController                                             │
│   - auth:sanctum 鉴权（token 属于谁，数据就按谁过滤）          │
│   - 白名单工具分发，角色权限过滤（学生只看自己）                │
│   - 统一返回 { ok, data } / { ok:false, error }              │
└─────────────────────────────────────────────────────────────┘
```

**关键决策**

| 决策点 | 结论 | 理由 |
|---|---|---|
| 前端 → AI 助手 | 直连，不经 Laravel 转发 | 少一跳，SSE 流式简单；Laravel 不感知对话内容 |
| 对话协议 | OpenAI 兼容 `/v1/chat/completions`，SSE | 通用，Ollama/vLLM/自研网关都兼容 |
| 身份传递 | 透传用户 Sanctum token | 用户已确认；AI 助手和 Laravel 都在本机，风险可控 |
| 工具调用落地 | Laravel 单一入口 `POST /ai/tools/call` | 白名单集中管理，加工具只改一处 |
| RAG | 完全在 AI 助手侧 | 实习系统只提供业务数据工具，不做知识库 |
| 写操作 | 只开放「提交请假」，其余只读 | AI 误操作风险最小化；请假本身有审批流兜底 |

## 3. 工具清单（Laravel 侧白名单）

所有工具统一入口：`POST /api/v1/ai/tools/call`，Header 带用户 token。

| 工具名 | 说明 | 参数 | 角色限制 |
|---|---|---|---|
| `get_my_rotation` | 我的轮转分段安排 | 无 | 全部（查自己） |
| `get_my_duty` | 我的值班表 | `month?` (YYYY-MM) | 全部 |
| `get_my_attendance` | 我的考勤打卡记录 | `month?` | 全部 |
| `get_attendance_stats` | 我的月度考勤统计 | `month?` | 全部 |
| `get_my_leaves` | 我的请假/审批申请列表 | 无 | 全部 |
| `submit_leave` | 提交请假申请 | `leave_type, start_date, end_date, reason` | 全部（审批人自动选教学管理员） |
| `list_teaching_events` | 教学活动列表 | `status?` | 全部 |
| `list_notices` | 通知公告 | 无 | 全部 |
| `get_my_evaluations` | 我的考核成绩 | 无 | 全部（查自己） |
| `get_my_patient_cases` | 我的病历记录及评语 | 无 | 全部（查自己） |
| `get_my_tasks_summary` | 待办汇总：待写病历数/待审批数等 | 无 | 全部 |
| `get_department_students` | 某科室今天在科学生 | `department_id` | teacher/admin/teaching-admin |
| `get_pending_approvals` | 待我审批的单子 | 无 | teacher/admin/teaching-admin |

返回格式统一：

```json
// 成功
{ "ok": true, "data": { ... } }
// 失败
{ "ok": false, "error": "人类可读的错误信息" }
```

未知工具名 → `404 { ok:false, error:"unknown tool" }`；权限不足 → `403`。

## 4. 前端 → AI 助手请求约定

```http
POST {VITE_AI_API_URL}/v1/chat/completions
Content-Type: application/json

{
  "model": "teaching-assistant",
  "stream": true,
  "messages": [
    {"role": "system", "content": "..."},
    {"role": "user", "content": "我下周在哪个科室？"}
  ],
  "tools": [ ...OpenAI function 定义，与第 3 节工具一一对应... ],
  "user_token": "<当前登录用户的 Sanctum token>"
}
```

`user_token` 是扩展字段（OpenAI 协议允许额外字段）。AI 助手收到后，回调 Laravel 工具时放到 `Authorization: Bearer` 头里。

**SSE 响应**：标准 OpenAI 流式格式，前端逐 chunk 拼 `delta.content` 渲染；`finish_reason=tool_calls` 时由 AI 助手侧自己完成工具调用后再继续推流，前端无感知。

## 5. 安全模型

1. **工具网关走 `auth:sanctum`**：token 是谁的，数据权限就是谁的。学生 token 调 `get_department_students` → 403。
2. **工具白名单**：`AiToolController` 里显式 switch/map，AI 助手无法调用白名单外的任何接口。
3. **写操作仅请假**：且请假提交后仍走原有 OA 审批流，AI 只代替填表，不跳过审批。
4. **CORS**：浏览器直连 8001，AI 助手必须允许来源 `http://127.0.0.1:5173`（`Access-Control-Allow-Origin`），否则浏览器会拦。
5. **token 暴露面**：token 会经过 AI 助手进程。本机部署可接受；若以后 AI 助手移到服务器，需换成「短期 AI 专用 token」方案（设计已预留：只改前端取 token 那一行）。

## 6. 前端组件设计

- `src/components/AiChat.vue`：右下角悬浮按钮 + 弹出聊天面板（约 380×520px）
- 消息列表：用户右对齐气泡、AI 左对齐气泡，支持 Markdown 简单渲染（先纯文本+换行，不引库）
- SSE 流式打字机效果；tool 调用期间显示「正在查询系统数据…」
- 快捷问题建议：「我下周在哪个科室」「我这月缺勤几次」「帮我请明天一天事假」
- 配置：`.env` 加 `VITE_AI_API_URL=http://127.0.0.1:8001`，改地址不改代码
- 入口：`MainLayout.vue` 挂载，所有登录后页面可用

## 7. 联调与测试

1. **mock AI 服务**：`scripts/mock-ai.js`（Node，监听 8001）
   - 实现 OpenAI 兼容 SSE
   - 关键词触发 tool_calls（如「轮转」→ `get_my_rotation`），回调真实 Laravel 工具网关
   - 带 CORS 头
   - 用途：AI 助手本体没写好之前，全链路（浏览器→AI→Laravel→数据库）可回归
2. **工具网关 curl 测试**：13 个工具逐个验证（学生/老师两种身份）
3. **Playwright UI 测试**：登录 → 打开对话框 → 发「我下周在哪个科室」→ 断言流式回复包含科室名
4. 更新 `scripts/smoke-api.sh`

## 8. 对 AI 助手本体（8001）的要求清单

给 AI 助手开发用的对接要求：

1. 暴露 `POST /v1/chat/completions`，支持 `stream:true`（SSE）
2. 接受额外字段 `user_token`，回调工具时透传为 `Authorization: Bearer`
3. 接受 `tools`（OpenAI function schema），需要时走 tool_calls 流程
4. 工具调用目标：`POST http://127.0.0.1:8000/api/v1/ai/tools/call`，body `{"tool": "<name>", "arguments": {...}}`
5. 开启 CORS：允许 `http://127.0.0.1:5173`，允许 `Content-Type` 头
6. RAG 教学问答自行实现，与实习系统无交互

> ⚠️ 第 7 节的 mock AI（`scripts/mock-ai.js`）已废弃：真实 AI 助手已接入，**不要再用 mock**。

---

## 9. 当前落地状态（2026-10-18 实际接入情况）

真实 AI 助手已接入，与上面第 2/4 节的初始设想有出入，以本节为准：

| 项 | 实际 |
|---|---|
| AI 助手本体 | `D:\简历\output\resume-ai-20260829\ai-service`（FastAPI，自研 ReAct Agent + RAG + RBAC），监听 **8001** |
| 协议 | **不是** OpenAI 兼容，而是它自研的 `POST /api/v1/chat/stream`（SSE：`{type:"steps"}` / `{type:"token"}` / `[DONE]`） |
| 鉴权头 | `X-Tenant-Id` / `X-User-Id` / `X-User-Num` / `X-Role` / **`X-Internship-Token`**（透传用户 Sanctum token，工具回调时用它换数据权限） |
| 工具回调 | ai-service 侧新增 13 个 `internship_*` 工具 → 统一走 `POST http://127.0.0.1:8000/api/v1/ai/tools/call` |
| 观察面板 | ai-service 自带：`http://127.0.0.1:8001/observability`（trace 审计）、`http://127.0.0.1:8001/`（独立问答台） |

**模型配置**（`.env`，已备份为 `.env.bak20261018_internship`）：

```
LLM_MODE=openai                  # openai=真实 LLM；mock=关键词规则兜底
OPENAI_API_BASE=https://dashscope.aliyuncs.com/compatible-mode/v1
OPENAI_MODEL=qwen3.8-max-0902    # 主模型：首轮工具选型 + 综合生成
OPENAI_MODEL_FAST=qwen3.8-flash  # 快模型：仅再决策步
LLM_ENABLE_THINKING=0            # 关思维链，实测 19.7s → 7.1s
```

已实测可用且支持 tool_calls 的备选：`qwen3.8-27b`、`qwen3.7-flash-2026-07-15`、`qwen3.8-flash`、`kimi-k3`、`qwen3.8-max-0902`。
（旧的 `qwen-plus-2025-07-28` 已下线会 404；`qwen-turbo` 等免费额度已耗尽。）

**会话记忆与已知限制**：

- 前端已做 localStorage 持久化：历史消息存 `ai-chat-messages:{uid}`（最近 60 条，含工具步骤），会话 ID 存 `ai-chat-session:{uid}`（清空对话时才换新）。**刷新页面不丢对话**。
- 后端 `MEMORY_BACKEND=memory`（进程内存）。ai-service 的记忆后端只有 `redis` 和 `memory` 两种（SQLite 后端已被移除），本机无 Redis。
- **已知限制（用户 2026-10-18 确认保持现状）：重启 ai-service 后，AI 会丢失上一轮上下文**（对话显示仍在，靠前端 localStorage，但回答时没有历史）。日常刷新不受影响。
- 若要彻底解决：装 Redis 后把 `MEMORY_BACKEND` 改回 `redis`（`REDIS_URL=redis://127.0.0.1:6380/0`），无需改代码。
