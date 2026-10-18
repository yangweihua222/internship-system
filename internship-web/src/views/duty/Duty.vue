<template>
  <el-card>
    <el-tabs v-model="tab">
      <el-tab-pane label="值班表" name="schedule">
        <el-form inline @submit.prevent>
          <el-form-item label="月份">
            <el-date-picker v-model="month" type="month" value-format="YYYY-MM" :clearable="false" @change="loadSchedules" />
          </el-form-item>
          <el-form-item label="类型">
            <el-select v-model="typeFilter" clearable placeholder="全部" style="width: 120px" @change="loadSchedules">
              <el-option label="老师" value="teacher" />
              <el-option label="学生" value="student" />
            </el-select>
          </el-form-item>
          <el-form-item v-if="auth.isAdmin">
            <el-button type="primary" :icon="Plus" @click="openArrange">批量排值班</el-button>
          </el-form-item>
        </el-form>
        <el-table :data="schedules" v-loading="loading" max-height="520">
          <el-table-column prop="duty_date" label="日期" width="120" />
          <el-table-column label="值班人" width="160">
            <template #default="{ row }">
              {{ row.user?.name }}
              <el-tag size="small" :type="row.type === 'teacher' ? 'warning' : 'info'" style="margin-left: 4px">
                {{ row.type === 'teacher' ? '老师' : '学生' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="shift_label" label="时段" width="100" />
          <el-table-column label="科室">
            <template #default="{ row }">{{ row.department?.name || '-' }}</template>
          </el-table-column>
          <el-table-column v-if="auth.isAdmin" label="操作" width="80">
            <template #default="{ row }">
              <el-button link type="danger" @click="onDeleteSchedule(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="我的值班" name="mine">
        <el-date-picker v-model="month" type="month" value-format="YYYY-MM" :clearable="false" @change="loadMine" style="margin-bottom: 12px" />
        <el-table :data="mine" v-loading="loadingMine">
          <el-table-column prop="duty_date" label="日期" width="140" />
          <el-table-column prop="shift_label" label="时段" width="120" />
          <el-table-column label="科室">
            <template #default="{ row }">{{ row.department?.name || '-' }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="值班日志" name="logs">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px">
          <el-button type="primary" :icon="Plus" @click="openLogDialog()">写日志</el-button>
        </div>
        <el-table :data="logs" v-loading="loadingLogs">
          <el-table-column prop="duty_date" label="值班日期" width="120" />
          <el-table-column prop="title" label="标题" min-width="160" />
          <el-table-column label="作者" width="120">
            <template #default="{ row }">{{ row.author?.name }}</template>
          </el-table-column>
          <el-table-column label="可查阅人" min-width="160">
            <template #default="{ row }">
              <el-tag v-for="v in row.viewers" :key="v.id" size="small" style="margin-right: 4px">{{ v.name }}</el-tag>
              <span v-if="!row.viewers?.length">-</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="180">
            <template #default="{ row }">
              <el-button link type="primary" @click="viewLog(row)">查看</el-button>
              <template v-if="row.author_id === auth.user?.id">
                <el-button link type="primary" @click="openLogDialog(row)">编辑</el-button>
                <el-button link type="danger" @click="onDeleteLog(row)">删除</el-button>
              </template>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 批量排值班 -->
    <el-dialog v-model="arrangeDialog" title="批量排值班" width="520px">
      <el-form :model="arrangeForm" label-width="90px">
        <el-form-item label="值班人" required>
          <el-select v-model="arrangeForm.user_ids" multiple filterable style="width: 100%">
            <el-option v-for="u in allUsers" :key="u.id" :label="`${u.name}（${u.username}）`" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="时段" required>
          <el-select v-model="arrangeForm.shift_label" style="width: 100%">
            <el-option label="白班" value="白班" />
            <el-option label="夜班" value="夜班" />
          </el-select>
        </el-form-item>
        <el-form-item label="科室">
          <el-tree-select v-model="arrangeForm.department_id" :data="departments" :props="{ label: 'name', children: 'children' }" node-key="id" check-strictly clearable style="width: 100%" />
        </el-form-item>
        <el-form-item label="日期范围" required>
          <el-date-picker v-model="arrangeForm.range" type="daterange" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="arrangeDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveArrange">提交</el-button>
      </template>
    </el-dialog>

    <!-- 写日志 -->
    <el-dialog v-model="logDialog" :title="logForm.id ? '编辑日志' : '写值班日志'" width="560px">
      <el-form :model="logForm" label-width="90px">
        <el-form-item label="值班日期" required>
          <el-date-picker v-model="logForm.duty_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="标题" required><el-input v-model="logForm.title" /></el-form-item>
        <el-form-item label="内容" required><el-input v-model="logForm.content" type="textarea" :rows="6" /></el-form-item>
        <el-form-item label="可查阅人">
          <el-select v-model="logForm.viewer_ids" multiple filterable style="width: 100%" placeholder="不选则仅自己和管理员可见">
            <el-option v-for="u in allUsers" :key="u.id" :label="`${u.name}（${u.username}）`" :value="u.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="logDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveLog">保存</el-button>
      </template>
    </el-dialog>

    <!-- 查看日志 -->
    <el-dialog v-model="viewDialog" :title="viewLogData?.title" width="560px">
      <div style="color: #999; margin-bottom: 8px">{{ viewLogData?.duty_date }} · {{ viewLogData?.author?.name }}</div>
      <div style="white-space: pre-wrap">{{ viewLogData?.content }}</div>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import {
  listDutySchedules, myDutySchedules, bulkDutySchedule, deleteDutySchedule,
  listDutyLogs, createDutyLog, updateDutyLog, deleteDutyLog,
  type DutySchedule, type DutyLog,
} from '@/api/duty'
import { listDepartments, listUsers, type Department, type UserItem } from '@/api/system'

const auth = useAuthStore()
const tab = ref('schedule')

const month = ref(new Date().toISOString().slice(0, 7))
const typeFilter = ref('')
const schedules = ref<DutySchedule[]>([])
const mine = ref<DutySchedule[]>([])
const logs = ref<DutyLog[]>([])
const departments = ref<Department[]>([])
const allUsers = ref<UserItem[]>([])

const loading = ref(false)
const loadingMine = ref(false)
const loadingLogs = ref(false)
const saving = ref(false)

const arrangeDialog = ref(false)
const logDialog = ref(false)
const viewDialog = ref(false)
const viewLogData = ref<DutyLog | null>(null)

const arrangeForm = reactive({ user_ids: [] as number[], shift_label: '白班', department_id: null as number | null, range: null as [string, string] | null })
const logForm = reactive({ id: null as number | null, duty_date: '', title: '', content: '', viewer_ids: [] as number[] })

async function loadSchedules() {
  loading.value = true
  try {
    schedules.value = (await listDutySchedules({ month: month.value, type: typeFilter.value || undefined })).list
  } finally {
    loading.value = false
  }
}

async function loadMine() {
  loadingMine.value = true
  try {
    mine.value = (await myDutySchedules(month.value)).list
  } finally {
    loadingMine.value = false
  }
}

async function loadLogs() {
  loadingLogs.value = true
  try {
    logs.value = (await listDutyLogs()).list
  } finally {
    loadingLogs.value = false
  }
}

function openArrange() {
  arrangeForm.user_ids = []
  arrangeForm.range = null
  arrangeDialog.value = true
}

async function onSaveArrange() {
  if (!arrangeForm.user_ids.length || !arrangeForm.range) {
    ElMessage.warning('请选择值班人和日期范围')
    return
  }
  saving.value = true
  try {
    const res = await bulkDutySchedule({
      user_ids: arrangeForm.user_ids,
      department_id: arrangeForm.department_id,
      shift_label: arrangeForm.shift_label,
      start_date: arrangeForm.range[0],
      end_date: arrangeForm.range[1],
    })
    ElMessage.success(`排班完成：新增 ${res.created} 条，跳过 ${res.skipped} 条`)
    arrangeDialog.value = false
    await loadSchedules()
  } finally {
    saving.value = false
  }
}

async function onDeleteSchedule(row: DutySchedule) {
  await ElMessageBox.confirm('确定删除这条值班安排吗？', '提示', { type: 'warning' })
  await deleteDutySchedule(row.id)
  ElMessage.success('已删除')
  await loadSchedules()
}

function openLogDialog(row?: DutyLog) {
  logForm.id = row?.id ?? null
  logForm.duty_date = row?.duty_date ?? new Date().toISOString().slice(0, 10)
  logForm.title = row?.title ?? ''
  logForm.content = row?.content ?? ''
  logForm.viewer_ids = row?.viewers?.map((v) => v.id) ?? []
  logDialog.value = true
}

async function onSaveLog() {
  if (!logForm.duty_date || !logForm.title || !logForm.content) {
    ElMessage.warning('请填写完整')
    return
  }
  saving.value = true
  try {
    if (logForm.id) {
      await updateDutyLog(logForm.id, { duty_date: logForm.duty_date, title: logForm.title, content: logForm.content, viewer_ids: logForm.viewer_ids })
    } else {
      await createDutyLog({ duty_date: logForm.duty_date, title: logForm.title, content: logForm.content, viewer_ids: logForm.viewer_ids })
    }
    ElMessage.success('保存成功')
    logDialog.value = false
    await loadLogs()
  } finally {
    saving.value = false
  }
}

function viewLog(row: DutyLog) {
  viewLogData.value = row
  viewDialog.value = true
}

async function onDeleteLog(row: DutyLog) {
  await ElMessageBox.confirm(`确定删除日志「${row.title}」吗？`, '提示', { type: 'warning' })
  await deleteDutyLog(row.id)
  ElMessage.success('已删除')
  await loadLogs()
}

onMounted(async () => {
  const deptRes = await listDepartments()
  departments.value = deptRes.list
  try {
    const userRes = await listUsers({ page: 1, page_size: 200 })
    allUsers.value = userRes.list
  } catch {
    allUsers.value = [] // 非管理员没有用户列表权限
  }
  await Promise.all([loadSchedules(), loadMine(), loadLogs()])
})
</script>
