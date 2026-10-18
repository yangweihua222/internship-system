<template>
  <!-- 悬浮按钮 -->
  <div v-if="!visible" class="ai-fab" @click="visible = true">
    <el-icon :size="24"><ChatDotRound /></el-icon>
  </div>

  <!-- 聊天面板 -->
  <transition name="el-zoom-in-bottom">
    <div v-show="visible" class="ai-panel">
      <div class="ai-header">
        <span>AI 教学助手</span>
        <div>
          <el-tooltip content="清空对话"><el-icon class="ai-header-btn" @click="clear"><Delete /></el-icon></el-tooltip>
          <el-icon class="ai-header-btn" @click="visible = false"><Close /></el-icon>
        </div>
      </div>

      <div ref="msgBox" class="ai-messages">
        <div v-if="messages.length === 0" class="ai-empty">
          <p>你好，我是 AI 教学助手，可以帮你：</p>
          <div class="ai-suggestions">
            <el-tag v-for="q in suggestions" :key="q" class="ai-suggestion" @click="ask(q)">{{ q }}</el-tag>
          </div>
        </div>
        <div v-for="(m, i) in messages" :key="i" :class="['ai-msg', m.role]">
          <div class="ai-bubble">
            <!-- 工具调用步骤：展示助手查了什么 -->
            <div v-if="m.steps?.length" class="ai-steps">
              <div v-for="(s, si) in m.steps" :key="si" class="ai-step">
                <span class="ai-step-tool">🔧 {{ s.tool }}</span>
                <span v-if="s.args && Object.keys(s.args).length" class="ai-step-args">{{ JSON.stringify(s.args) }}</span>
                <div v-if="s.observation" class="ai-step-obs">{{ s.observation }}</div>
              </div>
            </div>
            <div>{{ m.content }}<span v-if="m.streaming" class="ai-cursor">▍</span></div>
          </div>
        </div>
      </div>

      <div class="ai-input">
        <el-input
          v-model="input"
          type="textarea"
          :rows="2"
          resize="none"
          placeholder="问我轮转、值班、考勤、请假……"
          :disabled="sending"
          @keydown.enter.exact.prevent="send"
        />
        <el-button type="primary" :loading="sending" :disabled="!input.trim()" @click="send">发送</el-button>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { nextTick, reactive, ref, watch } from 'vue'
import { ChatDotRound, Close, Delete } from '@element-plus/icons-vue'
import { chatStream, type ToolStep } from '@/api/ai'
import { useAuthStore } from '@/stores/auth'

interface UiMessage {
  role: 'user' | 'assistant'
  content: string
  streaming?: boolean
  steps?: ToolStep[]
}

const auth = useAuthStore()
const uid = auth.user?.id ?? 'anon'
const LS_MSG = `ai-chat-messages:${uid}`
const LS_SID = `ai-chat-session:${uid}`

const visible = ref(false)
const input = ref('')
const sending = ref(false)
const messages = reactive<UiMessage[]>([])
const msgBox = ref<HTMLElement>()
// 会话 ID：写入 localStorage，刷新后仍复用同一个，ai-service 侧多轮记忆才能接上
let sessionId = localStorage.getItem(LS_SID) || `web-${uid}-${Date.now()}`
localStorage.setItem(LS_SID, sessionId)

// 刷新后恢复历史对话（只恢复已生成完的消息）
try {
  const saved: UiMessage[] = JSON.parse(localStorage.getItem(LS_MSG) || '[]')
  saved.forEach((m) => messages.push({ role: m.role, content: m.content, steps: m.steps }))
} catch {
  localStorage.removeItem(LS_MSG) // 缓存损坏则丢弃
}

// 持久化到 localStorage：不存正在流式的消息，最多留最近 60 条
watch(
  messages,
  (v) => {
    const done = v.filter((m) => !m.streaming).slice(-60)
    // 清空对话后不要留下空数组，直接移除，避免下次进来显示异常
    if (done.length === 0) localStorage.removeItem(LS_MSG)
    else localStorage.setItem(LS_MSG, JSON.stringify(done))
  },
  { deep: true },
)

const suggestions = ['我现在在哪个科室轮转？', '我这个月考勤怎么样？', '我有什么待办事项？', '最近有什么教学活动？']

async function scrollBottom() {
  await nextTick()
  if (msgBox.value) msgBox.value.scrollTop = msgBox.value.scrollHeight
}

function ask(q: string) {
  input.value = q
  send()
}

function send() {
  const text = input.value.trim()
  if (!text || sending.value) return

  messages.push({ role: 'user', content: text })
  input.value = ''
  sending.value = true

  const aiMsg = reactive<UiMessage>({ role: 'assistant', content: '', streaming: true })
  messages.push(aiMsg)
  scrollBottom()

  chatStream(
    text,
    sessionId,
    (steps) => {
      // 工具调用步骤（可能多次推送，累加）
      aiMsg.steps = [...(aiMsg.steps || []), ...steps]
      scrollBottom()
    },
    (delta) => {
      aiMsg.content += delta
      scrollBottom()
    },
    () => {
      aiMsg.streaming = false
      sending.value = false
      if (!aiMsg.content) aiMsg.content = '（AI 助手没有返回内容）'
      scrollBottom()
    },
    (err) => {
      aiMsg.streaming = false
      aiMsg.content = `⚠️ ${err.message}`
      sending.value = false
      scrollBottom()
    },
  )
}

function clear() {
  messages.splice(0)
  // 清空时换一个新会话，避免后端记忆把旧上下文带回来
  sessionId = `web-${uid}-${Date.now()}`
  localStorage.setItem(LS_SID, sessionId)
  localStorage.removeItem(LS_MSG)
}
</script>

<style scoped>
.ai-fab {
  position: fixed;
  right: 28px;
  bottom: 28px;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: #409eff;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.4);
  z-index: 2000;
}
.ai-fab:hover {
  background: #337ecc;
}
.ai-panel {
  position: fixed;
  right: 28px;
  bottom: 28px;
  width: 380px;
  height: 520px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.16);
  display: flex;
  flex-direction: column;
  z-index: 2000;
  overflow: hidden;
}
.ai-header {
  height: 46px;
  padding: 0 14px;
  background: #409eff;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
}
.ai-header-btn {
  cursor: pointer;
  margin-left: 10px;
}
.ai-messages {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  background: #f5f6f8;
}
.ai-empty {
  color: #666;
  font-size: 13px;
}
.ai-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.ai-suggestion {
  cursor: pointer;
}
.ai-msg {
  display: flex;
  margin-bottom: 10px;
}
.ai-msg.user {
  justify-content: flex-end;
}
.ai-bubble {
  max-width: 82%;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
}
.ai-msg.user .ai-bubble {
  background: #409eff;
  color: #fff;
}
.ai-msg.assistant .ai-bubble {
  background: #fff;
  color: #333;
  border: 1px solid #e8e8e8;
}
.ai-cursor {
  animation: blink 1s infinite;
}
.ai-steps {
  margin-bottom: 6px;
  padding-bottom: 6px;
  border-bottom: 1px dashed #e0e0e0;
}
.ai-step {
  font-size: 12px;
  color: #666;
  margin-bottom: 4px;
}
.ai-step-tool {
  font-weight: 600;
  color: #409eff;
  margin-right: 6px;
}
.ai-step-args {
  color: #999;
  word-break: break-all;
}
.ai-step-obs {
  margin-top: 2px;
  padding: 4px 6px;
  background: #f5f7fa;
  border-radius: 4px;
  white-space: pre-wrap;
  color: #555;
  max-height: 120px;
  overflow: auto;
}
@keyframes blink {
  50% {
    opacity: 0;
  }
}
.ai-input {
  padding: 10px;
  border-top: 1px solid #e8e8e8;
  display: flex;
  gap: 8px;
  align-items: flex-end;
}
</style>
