<template>
  <el-card>
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px">
      <el-radio-group v-model="statusFilter" @change="load">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="published">进行中</el-radio-button>
        <el-radio-button value="finished">已结束</el-radio-button>
        <el-radio-button value="canceled">已取消</el-radio-button>
      </el-radio-group>
      <el-button v-if="canManage" type="primary" :icon="Plus" @click="openCreate">发布活动</el-button>
    </div>

    <el-table :data="list" v-loading="loading">
      <el-table-column prop="title" label="活动" min-width="160" />
      <el-table-column label="时间" width="260">
        <template #default="{ row }">
          {{ formatTime(row.start_at) }} ~ {{ new Date(row.end_at).toLocaleTimeString('zh-CN', { hour12: false }) }}
        </template>
      </el-table-column>
      <el-table-column prop="location" label="地点" width="140" />
      <el-table-column label="科室" width="110">
        <template #default="{ row }">{{ row.department?.name || '-' }}</template>
      </el-table-column>
      <el-table-column prop="records_count" label="签到人数" width="90" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="statusTagMap[row.status] as never">
            {{ statusTextMap[row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="我的签到" width="140">
        <template #default="{ row }">
          <span v-if="row.my_record">
            {{ row.my_record.sign_in_at ? '已签到' : '' }}{{ row.my_record.sign_out_at ? ' / 已签退' : '' }}
            <el-tag v-if="row.my_record.score != null" type="success" size="small" style="margin-left: 4px">{{ row.my_record.score }}分</el-tag>
          </span>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <template v-if="row.status === 'published'">
            <el-button v-if="!row.my_record?.sign_in_at" link type="primary" @click="onSign(row, 'in')">签到</el-button>
            <el-button v-else-if="!row.my_record?.sign_out_at" link type="warning" @click="onSign(row, 'out')">签退</el-button>
          </template>
          <el-button v-if="canManage" link type="primary" @click="openRecords(row)">签到记录</el-button>
          <template v-if="canManage && row.status === 'published'">
            <el-button link type="info" @click="onSetStatus(row, 'finished')">结束</el-button>
            <el-button link type="danger" @click="onSetStatus(row, 'canceled')">取消</el-button>
          </template>
        </template>
      </el-table-column>
    </el-table>

    <!-- 发布活动 -->
    <el-dialog v-model="createDialog" title="发布教学活动" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="内容"><el-input v-model="form.content" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="时间" required>
          <el-date-picker v-model="form.range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-form-item label="地点"><el-input v-model="form.location" /></el-form-item>
        <el-form-item label="科室">
          <el-tree-select v-model="form.department_id" :data="departments" :props="{ label: 'name', children: 'children' }" node-key="id" check-strictly clearable style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">发布</el-button>
      </template>
    </el-dialog>

    <!-- 签到记录 + 打分 -->
    <el-drawer v-model="recordsDrawer" :title="`签到记录 - ${currentEvent?.title || ''}`" size="480px">
      <el-table :data="records" v-loading="loadingRecords" size="small">
        <el-table-column label="学生" width="90">
          <template #default="{ row }">{{ row.user?.name }}</template>
        </el-table-column>
        <el-table-column label="签到" width="100">
          <template #default="{ row }">{{ row.sign_in_at ? new Date(row.sign_in_at).toLocaleTimeString('zh-CN', { hour12: false }) : '-' }}</template>
        </el-table-column>
        <el-table-column label="签退" width="100">
          <template #default="{ row }">{{ row.sign_out_at ? new Date(row.sign_out_at).toLocaleTimeString('zh-CN', { hour12: false }) : '-' }}</template>
        </el-table-column>
        <el-table-column label="评分" width="150">
          <template #default="{ row }">
            <template v-if="row.score == null">
              <el-input-number v-model="scoreInputs[row.id]" :min="0" :max="100" size="small" style="width: 100px" />
              <el-button link type="primary" size="small" @click="onScore(row)">给分</el-button>
            </template>
            <el-tag v-else type="success" size="small">{{ row.score }} 分</el-tag>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loadingRecords && !records.length" description="暂无签到记录" />
    </el-drawer>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import {
  listEvents, createEvent, updateEventStatus, signEvent, eventRecords, scoreEventRecord,
  type TeachingEvent,
} from '@/api/teaching'
import { listDepartments, type Department } from '@/api/system'

const auth = useAuthStore()
const canManage = computed(() => auth.hasAnyRole(['teacher', 'admin', 'teaching-admin']))

const list = ref<TeachingEvent[]>([])
const departments = ref<Department[]>([])
const loading = ref(false)
const saving = ref(false)
const statusFilter = ref('')

const createDialog = ref(false)
const form = reactive({ title: '', content: '', range: null as [string, string] | null, location: '', department_id: null as number | null })

const recordsDrawer = ref(false)
const loadingRecords = ref(false)
const currentEvent = ref<TeachingEvent | null>(null)
const records = ref<any[]>([])
const scoreInputs = ref<Record<number, number>>({})

const formatTime = (t: string) => new Date(t).toLocaleString('zh-CN', { hour12: false })

const statusTagMap: Record<string, string> = { published: 'success', finished: 'info', canceled: 'danger' }
const statusTextMap: Record<string, string> = { published: '进行中', finished: '已结束', canceled: '已取消' }

async function load() {
  loading.value = true
  try {
    list.value = (await listEvents(statusFilter.value || undefined)).list
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.title = ''
  form.content = ''
  form.range = null
  form.location = ''
  form.department_id = null
  createDialog.value = true
}

async function onSave() {
  if (!form.title || !form.range) {
    ElMessage.warning('请填写标题和时间')
    return
  }
  saving.value = true
  try {
    await createEvent({
      title: form.title,
      content: form.content || undefined,
      start_at: form.range[0],
      end_at: form.range[1],
      location: form.location || undefined,
      department_id: form.department_id ?? undefined,
    })
    ElMessage.success('发布成功')
    createDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function onSign(row: TeachingEvent, action: 'in' | 'out') {
  await signEvent(row.id, action)
  ElMessage.success(action === 'in' ? '签到成功' : '签退成功')
  await load()
}

async function onSetStatus(row: TeachingEvent, status: string) {
  await ElMessageBox.confirm(`确定${status === 'finished' ? '结束' : '取消'}活动「${row.title}」吗？`, '提示', { type: 'warning' })
  await updateEventStatus(row.id, status)
  await load()
}

async function openRecords(row: TeachingEvent) {
  currentEvent.value = row
  recordsDrawer.value = true
  loadingRecords.value = true
  try {
    records.value = (await eventRecords(row.id)).list
    scoreInputs.value = {}
  } finally {
    loadingRecords.value = false
  }
}

async function onScore(row: any) {
  const score = scoreInputs.value[row.id]
  if (score === undefined || score === null) {
    ElMessage.warning('请输入分数')
    return
  }
  await scoreEventRecord(row.id, { score })
  ElMessage.success('已评分')
  if (currentEvent.value) {
    records.value = (await eventRecords(currentEvent.value.id)).list
  }
  await load()
}

onMounted(async () => {
  departments.value = (await listDepartments()).list
  await load()
})
</script>
