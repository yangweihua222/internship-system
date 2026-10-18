<template>
  <el-card>
    <el-tabs v-model="tab">
      <!-- 我的打卡 -->
      <el-tab-pane label="我的打卡" name="mine">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px">
          <el-date-picker v-model="month" type="month" value-format="YYYY-MM" :clearable="false" @change="loadMine" />
          <el-button type="primary" size="large" :loading="checking" @click="doCheckin('in')">上班打卡</el-button>
          <el-button type="warning" size="large" :loading="checking" @click="doCheckin('out')">下班打卡</el-button>
          <span style="color: #999; font-size: 13px">打卡时会获取浏览器定位（需授权）</span>
        </div>
        <el-table :data="days" v-loading="loadingMine">
          <el-table-column prop="work_date" label="日期" width="120" />
          <el-table-column label="班次" width="160">
            <template #default="{ row }">{{ row.shift?.name }}（{{ row.shift?.start_time }}-{{ row.shift?.end_time }}）</template>
          </el-table-column>
          <el-table-column label="考勤组" width="140">
            <template #default="{ row }">{{ row.group?.name }}</template>
          </el-table-column>
          <el-table-column label="状态" width="110">
            <template #default="{ row }">
              <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
              <span v-if="row.late_minutes" style="color: #e6a23c; font-size: 12px"> 迟到{{ row.late_minutes }}分</span>
            </template>
          </el-table-column>
          <el-table-column label="上班打卡" width="180">
            <template #default="{ row }">{{ formatTime(row.check_in_at) }}</template>
          </el-table-column>
          <el-table-column label="下班打卡" width="180">
            <template #default="{ row }">{{ formatTime(row.check_out_at) }}</template>
          </el-table-column>
          <el-table-column label="备注">
            <template #default="{ row }">{{ row.remark || '-' }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 班次 -->
      <el-tab-pane v-if="auth.isAdmin" label="班次管理" name="shifts">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px">
          <el-button type="primary" :icon="Plus" @click="openShiftDialog()">新增班次</el-button>
        </div>
        <el-table :data="shifts" v-loading="loadingShifts">
          <el-table-column prop="name" label="班次" width="140" />
          <el-table-column prop="start_time" label="上班时间" width="120" />
          <el-table-column prop="end_time" label="下班时间" width="120" />
          <el-table-column prop="late_threshold_minutes" label="迟到宽限（分）" width="130" />
          <el-table-column label="操作" width="140">
            <template #default="{ row }">
              <el-button link type="primary" @click="openShiftDialog(row)">编辑</el-button>
              <el-button link type="danger" @click="onDeleteShift(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 考勤组 -->
      <el-tab-pane v-if="auth.isAdmin" label="考勤组" name="groups">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px">
          <el-button type="primary" :icon="Plus" @click="openGroupDialog()">新增考勤组</el-button>
        </div>
        <el-table :data="groups" v-loading="loadingGroups">
          <el-table-column prop="name" label="名称" min-width="140" />
          <el-table-column label="科室" width="120">
            <template #default="{ row }">{{ row.department?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="围栏" min-width="180">
            <template #default="{ row }">
              <span v-if="row.lat && row.lng">{{ row.lat }}, {{ row.lng }}（半径 {{ row.radius_m }}m）</span>
              <el-tag v-else size="small" type="info">未配置</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="成员" min-width="200">
            <template #default="{ row }">
              <el-tag v-for="m in row.members" :key="m.id" size="small" style="margin-right: 4px">{{ m.name }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" @click="openMembers(row)">成员</el-button>
              <el-button link type="primary" @click="openGroupDialog(row)">编辑</el-button>
              <el-button link type="danger" @click="onDeleteGroup(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 排班 -->
      <el-tab-pane v-if="auth.isAdmin" label="排班管理" name="schedules">
        <el-form inline @submit.prevent>
          <el-form-item label="考勤组">
            <el-select v-model="scheduleQuery.group_id" placeholder="全部" clearable style="width: 160px" @change="loadSchedules">
              <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="月份">
            <el-date-picker v-model="scheduleQuery.month" type="month" value-format="YYYY-MM" :clearable="false" @change="loadSchedules" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Plus" @click="openScheduleDialog">批量排班</el-button>
          </el-form-item>
        </el-form>
        <el-table :data="schedules" v-loading="loadingSchedules" max-height="480">
          <el-table-column prop="work_date" label="日期" width="120" />
          <el-table-column label="人员" width="140">
            <template #default="{ row }">{{ row.user?.name }}（{{ row.user?.username }}）</template>
          </el-table-column>
          <el-table-column label="班次" width="160">
            <template #default="{ row }">{{ row.shift?.name }}</template>
          </el-table-column>
          <el-table-column label="考勤组">
            <template #default="{ row }">{{ row.group?.name }}</template>
          </el-table-column>
          <el-table-column label="操作" width="80">
            <template #default="{ row }">
              <el-button link type="danger" @click="onDeleteSchedule(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 统计 -->
      <el-tab-pane v-if="!auth.hasRole('student')" label="月度统计" name="stats">
        <el-form inline @submit.prevent>
          <el-form-item label="月份">
            <el-date-picker v-model="statsQuery.month" type="month" value-format="YYYY-MM" :clearable="false" @change="loadStats" />
          </el-form-item>
          <el-form-item label="考勤组">
            <el-select v-model="statsQuery.group_id" placeholder="全部" clearable style="width: 160px" @change="loadStats">
              <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
            </el-select>
          </el-form-item>
        </el-form>
        <el-table :data="stats" v-loading="loadingStats">
          <el-table-column label="姓名" width="140">
            <template #default="{ row }">{{ row.name }}（{{ row.username }}）</template>
          </el-table-column>
          <el-table-column prop="scheduled_days" label="应出勤" width="90" />
          <el-table-column prop="normal" label="正常" width="80" />
          <el-table-column prop="late" label="迟到" width="80" />
          <el-table-column prop="early" label="早退" width="80" />
          <el-table-column prop="absent" label="缺勤" width="80" />
          <el-table-column prop="leave" label="请假" width="80" />
          <el-table-column label="出勤率">
            <template #default="{ row }">
              <el-progress
                :percentage="row.scheduled_days ? Math.round(((row.normal + row.late + row.early) / row.scheduled_days) * 100) : 0"
                :stroke-width="14"
              />
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 班次弹窗 -->
    <el-dialog v-model="shiftDialog" :title="shiftForm.id ? '编辑班次' : '新增班次'" width="420px">
      <el-form :model="shiftForm" label-width="100px">
        <el-form-item label="名称" required><el-input v-model="shiftForm.name" /></el-form-item>
        <el-form-item label="上班时间" required>
          <el-time-picker v-model="shiftForm.start_time" value-format="HH:mm" format="HH:mm" />
        </el-form-item>
        <el-form-item label="下班时间" required>
          <el-time-picker v-model="shiftForm.end_time" value-format="HH:mm" format="HH:mm" />
        </el-form-item>
        <el-form-item label="迟到宽限">
          <el-input-number v-model="shiftForm.late_threshold_minutes" :min="0" :max="120" /> 分钟
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shiftDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveShift">保存</el-button>
      </template>
    </el-dialog>

    <!-- 考勤组弹窗 -->
    <el-dialog v-model="groupDialog" :title="groupForm.id ? '编辑考勤组' : '新增考勤组'" width="480px">
      <el-form :model="groupForm" label-width="100px">
        <el-form-item label="名称" required><el-input v-model="groupForm.name" /></el-form-item>
        <el-form-item label="科室">
          <el-tree-select v-model="groupForm.department_id" :data="departments" :props="{ label: 'name', children: 'children' }" node-key="id" check-strictly clearable style="width: 100%" />
        </el-form-item>
        <el-form-item label="围栏纬度"><el-input v-model="groupForm.lat" placeholder="如 39.9042" /></el-form-item>
        <el-form-item label="围栏经度"><el-input v-model="groupForm.lng" placeholder="如 116.4074" /></el-form-item>
        <el-form-item label="围栏半径"><el-input-number v-model="groupForm.radius_m" :min="50" :max="5000" /> 米</el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="groupDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveGroup">保存</el-button>
      </template>
    </el-dialog>

    <!-- 成员弹窗 -->
    <el-dialog v-model="memberDialog" :title="`成员管理 - ${currentGroup?.name || ''}`" width="420px">
      <el-select v-model="memberIds" multiple filterable style="width: 100%" placeholder="选择成员">
        <el-option v-for="u in allUsers" :key="u.id" :label="`${u.name}（${u.username}）`" :value="u.id" />
      </el-select>
      <template #footer>
        <el-button @click="memberDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveMembers">保存</el-button>
      </template>
    </el-dialog>

    <!-- 批量排班弹窗 -->
    <el-dialog v-model="scheduleDialog" title="批量排班" width="520px">
      <el-form :model="scheduleForm" label-width="100px">
        <el-form-item label="考勤组" required>
          <el-select v-model="scheduleForm.group_id" style="width: 100%" @change="onScheduleGroupChange">
            <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="人员" required>
          <el-select v-model="scheduleForm.user_ids" multiple filterable style="width: 100%">
            <el-option v-for="u in scheduleUserOptions" :key="u.id" :label="`${u.name}（${u.username}）`" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="班次" required>
          <el-select v-model="scheduleForm.shift_id" style="width: 100%">
            <el-option v-for="s in shifts" :key="s.id" :label="`${s.name}（${s.start_time}-${s.end_time}）`" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围" required>
          <el-date-picker v-model="scheduleForm.range" type="daterange" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="仅工作日">
          <el-checkbox-group v-model="scheduleForm.weekdays">
            <el-checkbox :value="1">一</el-checkbox>
            <el-checkbox :value="2">二</el-checkbox>
            <el-checkbox :value="3">三</el-checkbox>
            <el-checkbox :value="4">四</el-checkbox>
            <el-checkbox :value="5">五</el-checkbox>
            <el-checkbox :value="6">六</el-checkbox>
            <el-checkbox :value="7">日</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="scheduleDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSaveSchedule">提交</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import {
  listShifts, createShift, updateShift, deleteShift,
  listAttendanceGroups, createAttendanceGroup, updateAttendanceGroup, deleteAttendanceGroup, assignGroupMembers,
  listSchedules, bulkSchedule, deleteSchedule,
  checkin, myCheckinRecords, attendanceStatistics,
  type Shift, type AttendanceGroup, type AttendanceSchedule, type CheckinDay, type MonthlyStat,
} from '@/api/attendance'
import { listDepartments, listUsers, type Department, type UserItem } from '@/api/system'

const auth = useAuthStore()
const tab = ref('mine')

const month = ref(new Date().toISOString().slice(0, 7))
const days = ref<CheckinDay[]>([])
const shifts = ref<Shift[]>([])
const groups = ref<AttendanceGroup[]>([])
const schedules = ref<AttendanceSchedule[]>([])
const stats = ref<MonthlyStat[]>([])
const departments = ref<Department[]>([])
const allUsers = ref<UserItem[]>([])

const loadingMine = ref(false)
const loadingShifts = ref(false)
const loadingGroups = ref(false)
const loadingSchedules = ref(false)
const loadingStats = ref(false)
const checking = ref(false)
const saving = ref(false)

const shiftDialog = ref(false)
const groupDialog = ref(false)
const memberDialog = ref(false)
const scheduleDialog = ref(false)

const shiftForm = reactive({ id: null as number | null, name: '', start_time: '', end_time: '', late_threshold_minutes: 10 })
const groupForm = reactive({ id: null as number | null, name: '', department_id: null as number | null, lat: '', lng: '', radius_m: 300 })
const currentGroup = ref<AttendanceGroup | null>(null)
const memberIds = ref<number[]>([])
const scheduleQuery = reactive({ month: month.value, group_id: null as number | null })
const statsQuery = reactive({ month: month.value, group_id: null as number | null })
const scheduleForm = reactive({
  group_id: null as number | null,
  user_ids: [] as number[],
  shift_id: null as number | null,
  range: null as [string, string] | null,
  weekdays: [] as number[],
})

const scheduleUserOptions = computed(() => {
  if (!scheduleForm.group_id) return allUsers.value
  const g = groups.value.find((x) => x.id === scheduleForm.group_id)
  return g?.members?.length ? (g.members as unknown as UserItem[]) : allUsers.value
})

const statusText = (s: string) => ({ normal: '正常', late: '迟到', early: '早退', absent: '缺勤', leave: '请假', unknown: '未完成' }[s] ?? s)
const statusTag = (s: string) => ({ normal: 'success', late: 'warning', early: 'warning', absent: 'danger', leave: 'info', unknown: 'info' }[s] ?? 'info')
const formatTime = (t?: string | null) => (t ? new Date(t).toLocaleString('zh-CN', { hour12: false }) : '-')

async function loadMine() {
  loadingMine.value = true
  try {
    days.value = (await myCheckinRecords(month.value)).days
  } finally {
    loadingMine.value = false
  }
}

async function loadShifts() {
  loadingShifts.value = true
  try {
    shifts.value = (await listShifts()).list
  } finally {
    loadingShifts.value = false
  }
}

async function loadGroups() {
  loadingGroups.value = true
  try {
    groups.value = (await listAttendanceGroups()).list
  } finally {
    loadingGroups.value = false
  }
}

async function loadSchedules() {
  loadingSchedules.value = true
  try {
    schedules.value = (await listSchedules({ month: scheduleQuery.month, group_id: scheduleQuery.group_id ?? undefined })).list
  } finally {
    loadingSchedules.value = false
  }
}

async function loadStats() {
  loadingStats.value = true
  try {
    stats.value = (await attendanceStatistics({ month: statsQuery.month, group_id: statsQuery.group_id ?? undefined })).list
  } finally {
    loadingStats.value = false
  }
}

function getPosition(): Promise<{ lat?: number; lng?: number }> {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve({})
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => resolve({}),
      { timeout: 5000 },
    )
  })
}

async function doCheckin(type: 'in' | 'out') {
  checking.value = true
  try {
    const pos = await getPosition()
    const res = await checkin({ check_type: type, ...pos })
    if (res.result === 'outside') {
      ElMessage.warning(res.message)
    } else {
      ElMessage.success(res.message)
    }
    await loadMine()
  } finally {
    checking.value = false
  }
}

function openShiftDialog(row?: Shift) {
  shiftForm.id = row?.id ?? null
  shiftForm.name = row?.name ?? ''
  shiftForm.start_time = row?.start_time ?? ''
  shiftForm.end_time = row?.end_time ?? ''
  shiftForm.late_threshold_minutes = row?.late_threshold_minutes ?? 10
  shiftDialog.value = true
}

async function onSaveShift() {
  saving.value = true
  try {
    const data = { name: shiftForm.name, start_time: shiftForm.start_time, end_time: shiftForm.end_time, late_threshold_minutes: shiftForm.late_threshold_minutes }
    if (shiftForm.id) await updateShift(shiftForm.id, data)
    else await createShift(data)
    ElMessage.success('保存成功')
    shiftDialog.value = false
    await loadShifts()
  } finally {
    saving.value = false
  }
}

async function onDeleteShift(row: Shift) {
  await ElMessageBox.confirm(`确定删除班次「${row.name}」吗？`, '提示', { type: 'warning' })
  await deleteShift(row.id)
  ElMessage.success('已删除')
  await loadShifts()
}

function openGroupDialog(row?: AttendanceGroup) {
  groupForm.id = row?.id ?? null
  groupForm.name = row?.name ?? ''
  groupForm.department_id = row?.department_id ?? null
  groupForm.lat = row?.lat ?? ''
  groupForm.lng = row?.lng ?? ''
  groupForm.radius_m = row?.radius_m ?? 300
  groupDialog.value = true
}

async function onSaveGroup() {
  saving.value = true
  try {
    const data = {
      name: groupForm.name,
      department_id: groupForm.department_id,
      lat: groupForm.lat || undefined,
      lng: groupForm.lng || undefined,
      radius_m: groupForm.radius_m,
    }
    if (groupForm.id) await updateAttendanceGroup(groupForm.id, data)
    else await createAttendanceGroup(data)
    ElMessage.success('保存成功')
    groupDialog.value = false
    await loadGroups()
  } finally {
    saving.value = false
  }
}

async function onDeleteGroup(row: AttendanceGroup) {
  await ElMessageBox.confirm(`确定删除考勤组「${row.name}」吗？`, '提示', { type: 'warning' })
  await deleteAttendanceGroup(row.id)
  ElMessage.success('已删除')
  await loadGroups()
}

function openMembers(row: AttendanceGroup) {
  currentGroup.value = row
  memberIds.value = row.members?.map((m) => m.id) ?? []
  memberDialog.value = true
}

async function onSaveMembers() {
  if (!currentGroup.value) return
  saving.value = true
  try {
    await assignGroupMembers(currentGroup.value.id, memberIds.value)
    ElMessage.success('已保存')
    memberDialog.value = false
    await loadGroups()
  } finally {
    saving.value = false
  }
}

function openScheduleDialog() {
  scheduleForm.group_id = null
  scheduleForm.user_ids = []
  scheduleForm.shift_id = null
  scheduleForm.range = null
  scheduleForm.weekdays = []
  scheduleDialog.value = true
}

function onScheduleGroupChange() {
  scheduleForm.user_ids = []
}

async function onSaveSchedule() {
  if (!scheduleForm.group_id || !scheduleForm.shift_id || !scheduleForm.range || !scheduleForm.user_ids.length) {
    ElMessage.warning('请完整填写考勤组、人员、班次和日期范围')
    return
  }
  saving.value = true
  try {
    const res = await bulkSchedule({
      user_ids: scheduleForm.user_ids,
      group_id: scheduleForm.group_id,
      shift_id: scheduleForm.shift_id,
      start_date: scheduleForm.range[0],
      end_date: scheduleForm.range[1],
      weekdays: scheduleForm.weekdays.length ? scheduleForm.weekdays : undefined,
    })
    ElMessage.success(`排班完成：新增 ${res.created} 条，跳过 ${res.skipped} 条（已有排班）`)
    scheduleDialog.value = false
    await loadSchedules()
  } finally {
    saving.value = false
  }
}

async function onDeleteSchedule(row: AttendanceSchedule) {
  await deleteSchedule(row.id)
  ElMessage.success('已删除')
  await loadSchedules()
}

onMounted(async () => {
  const deptRes = await listDepartments()
  departments.value = deptRes.list
  if (auth.isAdmin) {
    const userRes = await listUsers({ page: 1, page_size: 200 })
    allUsers.value = userRes.list
  }
  await Promise.all([loadMine(), loadShifts(), loadGroups()])
  await loadSchedules()
  await loadStats()
})
</script>
