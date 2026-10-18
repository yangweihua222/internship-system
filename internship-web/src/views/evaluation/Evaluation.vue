<template>
  <el-card>
    <el-tabs v-model="tab">
      <el-tab-pane v-if="auth.isAdmin" label="模板管理" name="templates">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px">
          <el-button type="primary" :icon="Plus" @click="openTemplateDialog">新建模板</el-button>
        </div>
        <el-table :data="templates" v-loading="loadingTemplates">
          <el-table-column prop="name" label="模板名称" min-width="160" />
          <el-table-column label="评价对象" width="110">
            <template #default="{ row }">{{ targetText(row.target) }}</template>
          </el-table-column>
          <el-table-column label="题目" min-width="240">
            <template #default="{ row }">
              <el-tag v-for="q in row.questions" :key="q.id" size="small" style="margin-right: 4px">
                {{ q.content }}（{{ q.max_score }}分）
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100">
            <template #default="{ row }">
              <el-button link :type="row.is_active ? 'warning' : 'success'" @click="onToggle(row)">
                {{ row.is_active ? '停用' : '启用' }}
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="发起评价" name="submit">
        <el-form label-width="90px" style="max-width: 720px">
          <el-form-item label="评价模板" required>
            <el-select v-model="evalForm.template_id" style="width: 320px" @change="onTemplateChange">
              <el-option
                v-for="t in activeTemplates"
                :key="t.id"
                :label="`${t.name}（${targetText(t.target)}）`"
                :value="t.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="被评价人" required>
            <el-select v-model="evalForm.target_user_id" filterable style="width: 320px" placeholder="选择评价对象">
              <el-option v-for="u in targetOptions" :key="u.id" :label="`${u.name}（${u.username}）`" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="评价周期">
            <el-input v-model="evalForm.period" placeholder="如 2026-10 或 第2轮" style="width: 320px" />
          </el-form-item>
          <el-form-item v-for="q in currentQuestions" :key="q.id" :label="q.content" required>
            <el-rate
              v-model="scores[q.id]"
              :max="5"
              show-text
              :texts="[`${Math.round(q.max_score / 5)}分`, `${Math.round((q.max_score / 5) * 2)}分`, `${Math.round((q.max_score / 5) * 3)}分`, `${Math.round((q.max_score / 5) * 4)}分`, `${q.max_score}分`]"
              @change="(v: number) => (scores[q.id] = v)"
            />
            <span style="margin-left: 8px; color: #999">实际得分：{{ actualScore(q) }} / {{ q.max_score }}</span>
          </el-form-item>
          <el-form-item label="评语">
            <el-input v-model="evalForm.comment" type="textarea" :rows="3" style="width: 480px" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :loading="saving" @click="onSubmitEvaluation">提交评价</el-button>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <el-tab-pane label="评价结果" name="results">
        <el-radio-group v-model="resultScope" style="margin-bottom: 12px" @change="loadResults">
          <el-radio-button value="received">我被评价的</el-radio-button>
          <el-radio-button value="given">我评价的</el-radio-button>
          <el-radio-button v-if="auth.hasAnyRole(['teacher', 'admin', 'teaching-admin'])" value="all">全部</el-radio-button>
        </el-radio-group>
        <el-table :data="results" v-loading="loadingResults">
          <el-table-column label="模板" min-width="140">
            <template #default="{ row }">{{ row.template?.name }}</template>
          </el-table-column>
          <el-table-column label="评价人" width="110">
            <template #default="{ row }">{{ row.evaluator?.name }}</template>
          </el-table-column>
          <el-table-column label="被评价人" width="110">
            <template #default="{ row }">{{ row.target?.name }}</template>
          </el-table-column>
          <el-table-column prop="period" label="周期" width="110" />
          <el-table-column label="总分" width="90">
            <template #default="{ row }">
              <el-tag type="success">{{ row.total_score }} 分</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="comment" label="评语" min-width="160">
            <template #default="{ row }">{{ row.comment || '-' }}</template>
          </el-table-column>
          <el-table-column prop="created_at" label="时间" width="170">
            <template #default="{ row }">{{ new Date(row.created_at).toLocaleString('zh-CN', { hour12: false }) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 新建模板 -->
    <el-dialog v-model="templateDialog" title="新建评价模板" width="560px">
      <el-form label-width="90px">
        <el-form-item label="名称" required><el-input v-model="templateForm.name" /></el-form-item>
        <el-form-item label="评价对象" required>
          <el-radio-group v-model="templateForm.target">
            <el-radio value="student">评学生</el-radio>
            <el-radio value="teacher">评老师</el-radio>
            <el-radio value="discharge">出科评价</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="题目">
          <div v-for="(q, i) in templateForm.questions" :key="i" style="display: flex; gap: 8px; margin-bottom: 8px; width: 100%">
            <el-input v-model="q.content" placeholder="题目内容" style="flex: 1" />
            <el-input-number v-model="q.max_score" :min="1" :max="100" style="width: 120px" />
            <el-button link type="danger" @click="templateForm.questions.splice(i, 1)">删</el-button>
          </div>
          <el-button size="small" :icon="Plus" @click="templateForm.questions.push({ content: '', max_score: 10 })">加题目</el-button>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="templateDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveTemplate">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import {
  listEvalTemplates, createEvalTemplate, toggleEvalTemplate,
  submitEvaluation, listEvaluations,
  type EvaluationTemplate, type EvaluationItem, type EvaluationQuestion,
} from '@/api/teaching'
import { listUsers, type UserItem } from '@/api/system'

const auth = useAuthStore()
const tab = ref(auth.isAdmin ? 'templates' : 'submit')

const templates = ref<EvaluationTemplate[]>([])
const results = ref<EvaluationItem[]>([])
const users = ref<UserItem[]>([])

const loadingTemplates = ref(false)
const loadingResults = ref(false)
const saving = ref(false)
const templateDialog = ref(false)
const resultScope = ref('received')

const templateForm = reactive({ name: '', target: 'student', questions: [{ content: '', max_score: 10 }] as { content: string; max_score: number }[] })
const evalForm = reactive({ template_id: null as number | null, target_user_id: null as number | null, period: '', comment: '' })
const scores = ref<Record<number, number>>({})

const activeTemplates = computed(() => templates.value.filter((t) => t.is_active))
const currentTemplate = computed(() => templates.value.find((t) => t.id === evalForm.template_id))
const currentQuestions = computed(() => currentTemplate.value?.questions ?? [])

// 评价对象过滤：评老师模板→老师列表，评学生→学生列表，出科→学生
const targetOptions = computed(() => {
  const target = currentTemplate.value?.target
  if (target === 'teacher') return users.value.filter((u) => u.type === 'teacher')
  return users.value.filter((u) => u.type === 'student')
})

const targetText = (t: string) => ({ student: '评学生', teacher: '评老师', discharge: '出科评价' }[t] ?? t)
const actualScore = (q: EvaluationQuestion) => Math.round(((scores.value[q.id] ?? 0) / 5) * q.max_score)

async function loadTemplates() {
  loadingTemplates.value = true
  try {
    templates.value = (await listEvalTemplates()).list
  } finally {
    loadingTemplates.value = false
  }
}

async function loadResults() {
  loadingResults.value = true
  try {
    results.value = (await listEvaluations({ scope: resultScope.value })).list
  } finally {
    loadingResults.value = false
  }
}

function onTemplateChange() {
  scores.value = {}
  evalForm.target_user_id = null
}

function openTemplateDialog() {
  templateForm.name = ''
  templateForm.target = 'student'
  templateForm.questions = [{ content: '', max_score: 10 }]
  templateDialog.value = true
}

async function onSaveTemplate() {
  if (!templateForm.name || templateForm.questions.some((q) => !q.content.trim())) {
    ElMessage.warning('请填写模板名称和全部题目')
    return
  }
  saving.value = true
  try {
    await createEvalTemplate({ name: templateForm.name, target: templateForm.target, questions: templateForm.questions })
    ElMessage.success('创建成功')
    templateDialog.value = false
    await loadTemplates()
  } finally {
    saving.value = false
  }
}

async function onToggle(row: EvaluationTemplate) {
  await toggleEvalTemplate(row.id)
  await loadTemplates()
}

async function onSubmitEvaluation() {
  if (!evalForm.template_id || !evalForm.target_user_id) {
    ElMessage.warning('请选择模板和被评价人')
    return
  }
  const unanswered = currentQuestions.value.filter((q) => !scores.value[q.id])
  if (unanswered.length) {
    ElMessage.warning(`还有 ${unanswered.length} 道题未评分`)
    return
  }
  saving.value = true
  try {
    const answers = currentQuestions.value.map((q) => ({ question_id: q.id, score: actualScore(q) }))
    const res = await submitEvaluation({
      template_id: evalForm.template_id,
      target_user_id: evalForm.target_user_id,
      period: evalForm.period || undefined,
      comment: evalForm.comment || undefined,
      answers,
    })
    ElMessage.success(`评价已提交，总分 ${res.total_score}`)
    evalForm.target_user_id = null
    scores.value = {}
    evalForm.comment = ''
    await loadResults()
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadTemplates()
  try {
    users.value = (await listUsers({ page: 1, page_size: 200 })).list
  } catch {
    // 学生没有用户管理权限，退化为审批人列表（含老师和管理员）
    const { listApprovers } = await import('@/api/oa')
    const r = await listApprovers()
    users.value = r.list.map((a) => ({
      id: a.id,
      username: '',
      name: a.name,
      type: a.type,
      phone: null,
      status: 1,
      roles: [],
    }))
  }
  await loadResults()
})
</script>
