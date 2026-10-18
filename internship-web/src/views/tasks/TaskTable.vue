<template>
  <div>
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px">
      <el-radio-group v-model="statusFilter" @change="load">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="submitted">待批改</el-radio-button>
        <el-radio-button value="approved">已通过</el-radio-button>
        <el-radio-button value="rejected">已驳回</el-radio-button>
      </el-radio-group>
      <el-button v-if="isStudent" type="primary" :icon="Plus" @click="openCreate">写{{ title }}</el-button>
    </div>

    <el-table :data="list" v-loading="loading">
      <el-table-column v-for="col in columns" :key="col.key" :prop="col.key" :label="col.label" :width="col.width" :min-width="col.minWidth">
        <template #default="{ row }">{{ col.format ? col.format(row[col.key]) : row[col.key] || '-' }}</template>
      </el-table-column>
      <el-table-column label="学生" width="110">
        <template #default="{ row }">{{ row.student?.name }}</template>
      </el-table-column>
      <el-table-column label="科室" width="100">
        <template #default="{ row }">{{ row.department?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="评分/评语" min-width="140">
        <template #default="{ row }">
          <span v-if="row.score !== null && row.score !== undefined">
            <el-tag type="success" size="small">{{ row.score }} 分</el-tag>
          </span>
          <span style="font-size: 12px; color: #999"> {{ row.comment || '' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="viewDetail(row)">查看</el-button>
          <template v-if="isStudent && row.student_id === auth.user?.id && ['draft', 'submitted', 'rejected'].includes(row.status)">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
          <el-button v-if="canReview && row.status === 'submitted'" link type="warning" @click="openReview(row)">批改</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 写/编辑 -->
    <el-dialog v-model="editDialog" :title="(form.id ? '编辑' : '写') + title" width="640px">
      <el-form label-width="90px">
        <el-form-item v-for="f in formFields" :key="f.key" :label="f.label" :required="f.required">
          <el-input v-if="f.type === 'input'" v-model="form[f.key]" :placeholder="f.placeholder" />
          <el-input v-else-if="f.type === 'textarea'" v-model="form[f.key]" type="textarea" :rows="f.rows || 6" :placeholder="f.placeholder" />
          <el-date-picker v-else-if="f.type === 'date'" v-model="form[f.key]" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          <el-select v-else-if="f.type === 'select'" v-model="form[f.key]" style="width: 100%">
            <el-option v-for="o in f.options" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="保存为">
          <el-radio-group v-model="form.status">
            <el-radio value="draft">草稿</el-radio>
            <el-radio value="submitted">直接提交批改</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 批改 -->
    <el-dialog v-model="reviewDialog" :title="`批改 - ${current?.student?.name || ''}`" width="640px">
      <div v-if="current" style="max-height: 300px; overflow: auto; background: #f7f8fa; padding: 12px; border-radius: 6px; margin-bottom: 16px; white-space: pre-wrap">
        <b>{{ current.title || current.project_name }}</b>
        <div style="margin-top: 8px">{{ current.content }}</div>
      </div>
      <el-form label-width="80px">
        <el-form-item label="结果">
          <el-radio-group v-model="reviewForm.action">
            <el-radio value="approve">通过并评分</el-radio>
            <el-radio value="reject">驳回重写</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="reviewForm.action === 'approve'" label="评分">
          <el-input-number v-model="reviewForm.score" :min="0" :max="100" /> 分
        </el-form-item>
        <el-form-item label="评语">
          <el-input v-model="reviewForm.comment" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reviewDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onReview">提交批改</el-button>
      </template>
    </el-dialog>

    <!-- 查看 -->
    <el-dialog v-model="viewDialog" :title="current?.title || current?.project_name" width="640px">
      <div v-if="current" style="white-space: pre-wrap">{{ current.content }}</div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import { listTasks, createTask, updateTask, deleteTask, reviewTask, type TaskItem, type TaskKind } from '@/api/tasks'

export interface TaskColumn {
  key: string
  label: string
  width?: number
  minWidth?: number
  format?: (v: unknown) => string
}

export interface TaskField {
  key: string
  label: string
  type: 'input' | 'textarea' | 'date' | 'select'
  required?: boolean
  placeholder?: string
  rows?: number
  options?: { label: string; value: string }[]
}

const props = defineProps<{
  kind: TaskKind
  title: string
  columns: TaskColumn[]
  formFields: TaskField[]
}>()

const auth = useAuthStore()
const isStudent = computed(() => auth.hasRole('student'))
const canReview = computed(() => auth.hasAnyRole(['teacher', 'admin', 'teaching-admin']))

const list = ref<TaskItem[]>([])
const loading = ref(false)
const saving = ref(false)
const statusFilter = ref('')

const editDialog = ref(false)
const reviewDialog = ref(false)
const viewDialog = ref(false)
const current = ref<TaskItem | null>(null)
const form = reactive<Record<string, any>>({ id: null, status: 'submitted' })
const reviewForm = reactive({ action: 'approve' as 'approve' | 'reject', score: 80, comment: '' })

const statusText = (s: string) => ({ draft: '草稿', submitted: '待批改', approved: '已通过', rejected: '已驳回' }[s] ?? s)
const statusTag = (s: string) => ({ draft: 'info', submitted: 'warning', approved: 'success', rejected: 'danger' }[s] ?? 'info')

async function load() {
  loading.value = true
  try {
    list.value = (await listTasks(props.kind, { status: statusFilter.value || undefined })).list
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.id = null
  props.formFields.forEach((f) => (form[f.key] = f.type === 'select' ? f.options?.[0]?.value : ''))
  form.status = 'submitted'
  editDialog.value = true
}

function openEdit(row: TaskItem) {
  form.id = row.id
  props.formFields.forEach((f) => (form[f.key] = (row as any)[f.key] ?? ''))
  form.status = 'submitted'
  editDialog.value = true
}

async function onSave() {
  for (const f of props.formFields) {
    if (f.required && !form[f.key]) {
      ElMessage.warning(`请填写${f.label}`)
      return
    }
  }
  saving.value = true
  try {
    const data: Record<string, unknown> = { status: form.status }
    props.formFields.forEach((f) => (data[f.key] = form[f.key]))
    if (form.id) await updateTask(props.kind, form.id, data)
    else await createTask(props.kind, data)
    ElMessage.success('保存成功')
    editDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function onDelete(row: TaskItem) {
  await ElMessageBox.confirm('确定删除吗？', '提示', { type: 'warning' })
  await deleteTask(props.kind, row.id)
  ElMessage.success('已删除')
  await load()
}

function openReview(row: TaskItem) {
  current.value = row
  reviewForm.action = 'approve'
  reviewForm.score = 80
  reviewForm.comment = ''
  reviewDialog.value = true
}

async function onReview() {
  if (!current.value) return
  saving.value = true
  try {
    await reviewTask(props.kind, current.value.id, {
      action: reviewForm.action,
      score: reviewForm.action === 'approve' ? reviewForm.score : undefined,
      comment: reviewForm.comment || undefined,
    })
    ElMessage.success('批改完成')
    reviewDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

function viewDetail(row: TaskItem) {
  current.value = row
  viewDialog.value = true
}

onMounted(load)
</script>
