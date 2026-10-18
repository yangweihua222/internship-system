<template>
  <el-card>
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px">
      <el-radio-group v-model="statusFilter" @change="load">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="submitted">待批改</el-radio-button>
        <el-radio-button value="approved">已通过</el-radio-button>
        <el-radio-button value="rejected">已驳回</el-radio-button>
      </el-radio-group>
      <el-button v-if="auth.hasRole('student')" type="primary" :icon="Plus" @click="openCreate">写报告</el-button>
    </div>

    <el-table :data="list" v-loading="loading">
      <el-table-column label="类型" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="row.type === 'summary' ? 'primary' : 'warning'">
            {{ row.type === 'summary' ? '实习总结' : '出科报告' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="title" label="标题" min-width="200" />
      <el-table-column label="周期" width="200">
        <template #default="{ row }">
          {{ row.period_start || '-' }} ~ {{ row.period_end || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="学生" width="110">
        <template #default="{ row }">{{ row.student?.name }}</template>
      </el-table-column>
      <el-table-column label="科室" width="100">
        <template #default="{ row }">{{ row.department?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="statusTagMap[row.status] as never">
            {{ statusTextMap[row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="评分" width="80">
        <template #default="{ row }">{{ row.score ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="viewDetail(row)">查看</el-button>
          <template v-if="row.student_id === auth.user?.id && row.status !== 'approved'">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
          <el-button v-if="canReview && row.status === 'submitted'" link type="warning" @click="openReview(row)">批改</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="editDialog" :title="(form.id ? '编辑' : '写') + '实习报告'" width="680px">
      <el-form label-width="90px">
        <el-form-item label="类型" required>
          <el-radio-group v-model="form.type">
            <el-radio value="summary">实习总结</el-radio>
            <el-radio value="report">出科报告</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="实习周期">
          <el-date-picker v-model="form.range" type="daterange" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="内容" required>
          <el-input v-model="form.content" type="textarea" :rows="10" />
        </el-form-item>
        <el-form-item label="保存为">
          <el-radio-group v-model="form.status">
            <el-radio value="draft">草稿</el-radio>
            <el-radio value="submitted">提交批改</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="viewDialog" :title="current?.title" width="640px">
      <div style="color: #999; font-size: 13px; margin-bottom: 8px">
        {{ current?.student?.name }} · {{ current?.period_start }} ~ {{ current?.period_end }}
      </div>
      <div style="white-space: pre-wrap">{{ current?.content }}</div>
      <el-divider v-if="current?.comment" content-position="left">老师评语</el-divider>
      <div v-if="current?.comment" style="color: #666">{{ current.comment }}（{{ current.score }} 分）</div>
    </el-dialog>

    <el-dialog v-model="reviewDialog" title="批改报告" width="480px">
      <el-form label-width="80px">
        <el-form-item label="结果">
          <el-radio-group v-model="reviewForm.action">
            <el-radio value="approve">通过并评分</el-radio>
            <el-radio value="reject">驳回重写</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="reviewForm.action === 'approve'" label="评分">
          <el-input-number v-model="reviewForm.score" :min="0" :max="100" />
        </el-form-item>
        <el-form-item label="评语">
          <el-input v-model="reviewForm.comment" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reviewDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onReview">提交</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import { listReports, createReport, updateReport, deleteReport, reviewReport, type ReportItem } from '@/api/tasks'

const auth = useAuthStore()
const canReview = computed(() => auth.hasAnyRole(['teacher', 'admin', 'teaching-admin']))

const list = ref<ReportItem[]>([])
const statusTagMap: Record<string, string> = { draft: 'info', submitted: 'warning', approved: 'success', rejected: 'danger' }
const statusTextMap: Record<string, string> = { draft: '草稿', submitted: '待批改', approved: '已通过', rejected: '已驳回' }
const loading = ref(false)
const saving = ref(false)
const statusFilter = ref('')

const editDialog = ref(false)
const viewDialog = ref(false)
const reviewDialog = ref(false)
const current = ref<ReportItem | null>(null)
const form = reactive({ id: null as number | null, type: 'summary', title: '', range: null as [string, string] | null, content: '', status: 'submitted' })
const reviewForm = reactive({ action: 'approve' as 'approve' | 'reject', score: 80, comment: '' })

async function load() {
  loading.value = true
  try {
    list.value = (await listReports({ status: statusFilter.value || undefined })).list
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.id = null
  form.type = 'summary'
  form.title = ''
  form.range = null
  form.content = ''
  form.status = 'submitted'
  editDialog.value = true
}

function openEdit(row: ReportItem) {
  form.id = row.id
  form.type = row.type ?? 'summary'
  form.title = row.title
  form.range = row.period_start && row.period_end ? [row.period_start, row.period_end] : null
  form.content = row.content
  form.status = 'submitted'
  editDialog.value = true
}

async function onSave() {
  if (!form.title || !form.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  saving.value = true
  try {
    const data = {
      type: form.type,
      title: form.title,
      period_start: form.range?.[0],
      period_end: form.range?.[1],
      content: form.content,
      status: form.status,
    }
    if (form.id) await updateReport(form.id, data)
    else await createReport(data)
    ElMessage.success('保存成功')
    editDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function onDelete(row: ReportItem) {
  await ElMessageBox.confirm('确定删除该报告吗？', '提示', { type: 'warning' })
  await deleteReport(row.id)
  ElMessage.success('已删除')
  await load()
}

function viewDetail(row: ReportItem) {
  current.value = row
  viewDialog.value = true
}

function openReview(row: ReportItem) {
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
    await reviewReport(current.value.id, {
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

onMounted(load)
</script>
