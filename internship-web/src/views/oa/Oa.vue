<template>
  <el-card>
    <el-tabs v-model="tab">
      <el-tab-pane label="我的申请" name="mine">
        <div style="display: flex; gap: 8px; margin-bottom: 12px">
          <el-button type="primary" @click="openSubmit('leave')">请假</el-button>
          <el-button type="primary" @click="openSubmit('leave_cancel')">销假</el-button>
          <el-button type="primary" @click="openSubmit('shift_change')">换班</el-button>
          <el-button type="primary" @click="openSubmit('append_checkin')">补卡</el-button>
        </div>
        <el-table :data="myList" v-loading="loadingMine">
          <el-table-column prop="title" label="申请" min-width="260" />
          <el-table-column label="类型" width="90">
            <template #default="{ row }">{{ typeText(row.type) }}</template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="审批进度" min-width="200">
            <template #default="{ row }">
              <el-steps :active="activeNode(row)" style="max-width: 100%">
                <el-step
                  v-for="n in row.nodes"
                  :key="n.id"
                  :title="n.approver?.name"
                  :status="nodeStatus(n.status)"
                />
              </el-steps>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="提交时间" width="170">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="90">
            <template #default="{ row }">
              <el-button v-if="row.status === 'pending'" link type="danger" @click="onCancel(row)">撤销</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="待我审批" name="pending">
        <el-table :data="pendingList" v-loading="loadingPending">
          <el-table-column label="申请" min-width="260">
            <template #default="{ row }">{{ row.process?.title }}</template>
          </el-table-column>
          <el-table-column label="申请人" width="120">
            <template #default="{ row }">{{ row.process?.applicant?.name }}</template>
          </el-table-column>
          <el-table-column label="详情" min-width="240">
            <template #default="{ row }">{{ detailText(row.process) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="150" fixed="right">
            <template #default="{ row }">
              <el-button link type="success" @click="onApprove(row)">通过</el-button>
              <el-button link type="danger" @click="onReject(row)">驳回</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!loadingPending && !pendingList.length" description="暂无待审批" />
      </el-tab-pane>

      <el-tab-pane label="审批历史" name="acted">
        <el-table :data="actedList" v-loading="loadingActed">
          <el-table-column label="申请" min-width="240">
            <template #default="{ row }">{{ row.process?.title }}</template>
          </el-table-column>
          <el-table-column label="申请人" width="120">
            <template #default="{ row }">{{ row.process?.applicant?.name }}</template>
          </el-table-column>
          <el-table-column label="我的处理" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'approved' ? 'success' : 'danger'">
                {{ row.status === 'approved' ? '已通过' : '已驳回' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="comment" label="意见" min-width="160" />
          <el-table-column label="时间" width="170">
            <template #default="{ row }">{{ formatTime(row.acted_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 发起申请弹窗 -->
    <el-dialog v-model="submitDialog" :title="submitTitle" width="520px">
      <el-form label-width="90px">
        <template v-if="submitType === 'leave'">
          <el-form-item label="请假类型" required>
            <el-radio-group v-model="leaveForm.leave_type">
              <el-radio value="事假">事假</el-radio>
              <el-radio value="病假">病假</el-radio>
              <el-radio value="其他">其他</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="请假日期" required>
            <el-date-picker v-model="leaveForm.range" type="daterange" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
          <el-form-item label="事由" required>
            <el-input v-model="leaveForm.reason" type="textarea" :rows="3" />
          </el-form-item>
        </template>

        <template v-else-if="submitType === 'leave_cancel'">
          <el-form-item label="原请假单" required>
            <el-select v-model="leaveCancelForm.leave_process_id" style="width: 100%" placeholder="选择已通过的请假单">
              <el-option
                v-for="p in approvedLeaves"
                :key="p.id"
                :label="`#${p.id} ${p.leave_detail?.leave_type} ${p.leave_detail?.start_date} ~ ${p.leave_detail?.end_date}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="销假事由" required>
            <el-input v-model="leaveCancelForm.reason" type="textarea" :rows="3" />
          </el-form-item>
        </template>

        <template v-else-if="submitType === 'shift_change'">
          <el-form-item label="我的值班" required>
            <el-select v-model="shiftChangeForm.my_schedule_id" style="width: 100%" placeholder="选择我的值班">
              <el-option
                v-for="s in myDutyList"
                :key="s.id"
                :label="`${s.duty_date} ${s.shift_label}`"
                :value="s.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="换到" required>
            <el-select v-model="shiftChangeForm.target_schedule_id" style="width: 100%" placeholder="选择对方的值班">
              <el-option
                v-for="s in otherDutyList"
                :key="s.id"
                :label="`${s.duty_date} ${s.shift_label} - ${s.user?.name}`"
                :value="s.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="换班事由" required>
            <el-input v-model="shiftChangeForm.reason" type="textarea" :rows="3" />
          </el-form-item>
        </template>

        <template v-else-if="submitType === 'append_checkin'">
          <el-form-item label="补卡日期" required>
            <el-date-picker v-model="appendForm.work_date" type="date" value-format="YYYY-MM-DD" :disabled-date="(d: Date) => d.getTime() > Date.now()" style="width: 100%" />
          </el-form-item>
          <el-form-item label="类型" required>
            <el-radio-group v-model="appendForm.check_type">
              <el-radio value="in">上班卡</el-radio>
              <el-radio value="out">下班卡</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="补卡时间" required>
            <el-time-picker v-model="appendForm.check_time" value-format="HH:mm" format="HH:mm" style="width: 100%" />
          </el-form-item>
          <el-form-item label="事由" required>
            <el-input v-model="appendForm.reason" type="textarea" :rows="3" />
          </el-form-item>
        </template>

        <el-form-item label="审批人" required>
          <el-select v-model="approverIds" multiple filterable style="width: 100%" placeholder="按顺序逐级审批">
            <el-option v-for="a in approvers" :key="a.id" :label="`${a.name}（${a.type === 'admin' ? '管理员' : '老师'}）`" :value="a.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="submitDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSubmit">提交申请</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useAuthStore } from '@/stores/auth'
import {
  listApprovers, myOaProcesses, pendingOa, actedOa,
  submitLeave, submitLeaveCancel, submitShiftChange, submitAppendCheckin,
  approveNode, rejectNode, cancelProcess,
  type OaProcess, type OaNode, type ApproverOption,
} from '@/api/oa'
import { myDutySchedules, listDutySchedules, type DutySchedule } from '@/api/duty'

const auth = useAuthStore()
const tab = ref('mine')

const myList = ref<OaProcess[]>([])
const pendingList = ref<OaNode[]>([])
const actedList = ref<OaNode[]>([])
const approvers = ref<ApproverOption[]>([])
const myDutyList = ref<DutySchedule[]>([])
const otherDutyList = ref<DutySchedule[]>([])

const loadingMine = ref(false)
const loadingPending = ref(false)
const loadingActed = ref(false)
const saving = ref(false)

const submitDialog = ref(false)
const submitType = ref<'leave' | 'leave_cancel' | 'shift_change' | 'append_checkin'>('leave')
const approverIds = ref<number[]>([])

const leaveForm = reactive({ leave_type: '事假', range: null as [string, string] | null, reason: '' })
const leaveCancelForm = reactive({ leave_process_id: null as number | null, reason: '' })
const shiftChangeForm = reactive({ my_schedule_id: null as number | null, target_schedule_id: null as number | null, reason: '' })
const appendForm = reactive({ work_date: '', check_type: 'in' as 'in' | 'out', check_time: '', reason: '' })

const submitTitle = computed(() => ({ leave: '请假申请', leave_cancel: '销假申请', shift_change: '换班申请', append_checkin: '补卡申请' }[submitType.value]))
const approvedLeaves = computed(() => myList.value.filter((p) => p.type === 'leave' && p.status === 'approved'))

const typeText = (t: string) => ({ leave: '请假', leave_cancel: '销假', shift_change: '换班', append_checkin: '补卡' }[t] ?? t)
const statusText = (s: string) => ({ pending: '审批中', approved: '已通过', rejected: '已驳回', canceled: '已撤销' }[s] ?? s)
const statusTag = (s: string) => ({ pending: 'warning', approved: 'success', rejected: 'danger', canceled: 'info' }[s] ?? 'info')
const nodeStatus = (s: string) => ({ pending: 'wait', approved: 'finish', rejected: 'error', skipped: 'wait' }[s] as never ?? 'wait')
const activeNode = (row: OaProcess) => (row.status === 'approved' ? (row.nodes?.length ?? 0) : row.current_node - 1)
const formatTime = (t?: string | null) => (t ? new Date(t).toLocaleString('zh-CN', { hour12: false }) : '-')

function detailText(p?: OaProcess): string {
  if (!p) return ''
  if (p.type === 'leave' && p.leave_detail) {
    return `${p.leave_detail.leave_type} ${p.leave_detail.start_date} ~ ${p.leave_detail.end_date}：${p.leave_detail.reason}`
  }
  if (p.type === 'leave_cancel' && p.leave_cancel_detail) {
    return `撤销请假单 #${p.leave_cancel_detail.leave_process_id}：${p.leave_cancel_detail.reason}`
  }
  if (p.type === 'shift_change' && p.shift_change_detail) {
    const d = p.shift_change_detail
    return `${d.my_schedule?.duty_date}(${d.my_schedule?.shift_label}) ⇄ ${d.target_schedule?.user?.name ?? ''} ${d.target_schedule?.duty_date}(${d.target_schedule?.shift_label})：${d.reason}`
  }
  if (p.type === 'append_checkin' && p.append_checkin_detail) {
    const d = p.append_checkin_detail
    return `${d.work_date} ${d.check_type === 'in' ? '上班' : '下班'} ${d.check_time}：${d.reason}`
  }
  return ''
}

async function loadMine() {
  loadingMine.value = true
  try {
    myList.value = (await myOaProcesses()).list
  } finally {
    loadingMine.value = false
  }
}

async function loadPending() {
  loadingPending.value = true
  try {
    pendingList.value = (await pendingOa()).list
  } finally {
    loadingPending.value = false
  }
}

async function loadActed() {
  loadingActed.value = true
  try {
    actedList.value = (await actedOa()).list
  } finally {
    loadingActed.value = false
  }
}

async function openSubmit(type: typeof submitType.value) {
  submitType.value = type
  approverIds.value = []
  leaveForm.range = null; leaveForm.reason = ''
  leaveCancelForm.leave_process_id = null; leaveCancelForm.reason = ''
  shiftChangeForm.my_schedule_id = null; shiftChangeForm.target_schedule_id = null; shiftChangeForm.reason = ''
  appendForm.work_date = ''; appendForm.check_time = ''; appendForm.reason = ''

  const month = new Date().toISOString().slice(0, 7)
  const jobs: Promise<unknown>[] = [myOaProcesses().then((r) => (myList.value = r.list))]
  if (!approvers.value.length) jobs.push(listApprovers().then((r) => (approvers.value = r.list)))
  if (type === 'shift_change') {
    jobs.push(myDutySchedules(month).then((r) => (myDutyList.value = r.list)))
    jobs.push(
      listDutySchedules({ month }).then(
        (r) => (otherDutyList.value = r.list.filter((s) => s.user_id !== auth.user?.id)),
      ),
    )
  }
  await Promise.all(jobs)
  submitDialog.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    if (submitType.value === 'leave') {
      if (!leaveForm.range) return ElMessage.warning('请选择请假日期') as never
      await submitLeave({ leave_type: leaveForm.leave_type, start_date: leaveForm.range[0], end_date: leaveForm.range[1], reason: leaveForm.reason, approver_ids: approverIds.value })
    } else if (submitType.value === 'leave_cancel') {
      if (!leaveCancelForm.leave_process_id) return ElMessage.warning('请选择原请假单') as never
      await submitLeaveCancel({ leave_process_id: leaveCancelForm.leave_process_id, reason: leaveCancelForm.reason, approver_ids: approverIds.value })
    } else if (submitType.value === 'shift_change') {
      if (!shiftChangeForm.my_schedule_id || !shiftChangeForm.target_schedule_id) return ElMessage.warning('请选择两边值班') as never
      await submitShiftChange({ my_schedule_id: shiftChangeForm.my_schedule_id, target_schedule_id: shiftChangeForm.target_schedule_id, reason: shiftChangeForm.reason, approver_ids: approverIds.value })
    } else {
      if (!appendForm.work_date || !appendForm.check_time) return ElMessage.warning('请填写补卡日期和时间') as never
      await submitAppendCheckin({ work_date: appendForm.work_date, check_type: appendForm.check_type, check_time: appendForm.check_time, reason: appendForm.reason, approver_ids: approverIds.value })
    }
    ElMessage.success('已提交，等待审批')
    submitDialog.value = false
    await loadMine()
  } finally {
    saving.value = false
  }
}

async function onApprove(node: OaNode) {
  const { value } = await ElMessageBox.prompt('审批意见（可空）', '通过申请', { inputPlaceholder: '同意', confirmButtonText: '通过', cancelButtonText: '取消' })
  await approveNode(node.id, value || undefined)
  ElMessage.success('已通过')
  await Promise.all([loadPending(), loadActed()])
}

async function onReject(node: OaNode) {
  const { value } = await ElMessageBox.prompt('驳回原因（必填）', '驳回申请', { inputPlaceholder: '请输入驳回原因', confirmButtonText: '驳回', cancelButtonText: '取消', inputValidator: (v) => !!v?.trim() || '请填写驳回原因' })
  await rejectNode(node.id, value)
  ElMessage.success('已驳回')
  await Promise.all([loadPending(), loadActed()])
}

async function onCancel(row: OaProcess) {
  await ElMessageBox.confirm('确定撤销该申请吗？', '提示', { type: 'warning' })
  await cancelProcess(row.id)
  ElMessage.success('已撤销')
  await loadMine()
}

onMounted(() => {
  loadMine()
  loadPending()
  loadActed()
})
</script>
