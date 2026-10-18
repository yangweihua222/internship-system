<template>
  <el-card>
    <el-tabs v-model="tab">
      <el-tab-pane v-if="auth.isAdmin" label="轮转管理" name="plans">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px">
          <el-button type="primary" :icon="Plus" @click="openCreatePlan">新建轮转</el-button>
        </div>
        <el-table :data="plans" v-loading="loadingPlans">
          <el-table-column prop="name" label="轮转名称" min-width="160" />
          <el-table-column label="轮转周期" width="220">
            <template #default="{ row }">{{ row.start_date?.slice(0, 10) }} ~ {{ row.end_date?.slice(0, 10) }}</template>
          </el-table-column>
          <el-table-column prop="students_count" label="学生数" width="80" />
          <el-table-column prop="details_count" label="分段数" width="80" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="planStatusTag(row.status)">{{ planStatusText(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="300" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" @click="openManage(row)">学生/分段</el-button>
              <el-button link type="primary" @click="openEditPlan(row)">编辑</el-button>
              <el-button v-if="row.status === 'draft'" link type="success" @click="setPlanStatus(row, 'active')">启用</el-button>
              <el-button v-if="row.status === 'active'" link type="warning" @click="setPlanStatus(row, 'finished')">完成</el-button>
              <el-button v-if="row.status !== 'active'" link type="danger" @click="onDeletePlan(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="我的轮转" name="mine">
        <el-timeline v-loading="loadingMine" style="padding-left: 8px">
          <el-timeline-item
            v-for="d in myRotations"
            :key="d.id"
            :timestamp="`${d.start_date?.slice(0, 10)} ~ ${d.end_date?.slice(0, 10)}`"
            :type="d.is_current ? 'primary' : 'info'"
            :hollow="!d.is_current"
          >
            <b>{{ d.department?.name }}</b>
            <el-tag v-if="d.is_current" size="small" type="success" style="margin-left: 8px">当前科室</el-tag>
            <div style="color: #999; font-size: 12px; margin-top: 4px">{{ d.plan?.name }}</div>
          </el-timeline-item>
          <el-empty v-if="!loadingMine && !myRotations.length" description="暂无轮转安排" />
        </el-timeline>
      </el-tab-pane>

      <el-tab-pane v-if="!auth.hasRole('student')" label="科室在科学生" name="dept">
        <div style="display: flex; gap: 8px; margin-bottom: 12px">
          <el-tree-select
            v-model="deptQuery.department_id"
            :data="departments"
            :props="{ label: 'name', children: 'children' }"
            node-key="id"
            check-strictly
            placeholder="选择科室"
            style="width: 220px"
          />
          <el-date-picker v-model="deptQuery.date" type="date" value-format="YYYY-MM-DD" placeholder="日期" />
          <el-button type="primary" @click="loadDeptStudents">查询</el-button>
        </div>
        <el-table :data="deptStudents" v-loading="loadingDept">
          <el-table-column label="学生" width="140">
            <template #default="{ row }">{{ row.student?.name }}（{{ row.student?.username }}）</template>
          </el-table-column>
          <el-table-column label="入科日期" width="140">
            <template #default="{ row }">{{ row.start_date?.slice(0, 10) }}</template>
          </el-table-column>
          <el-table-column label="出科日期" width="140">
            <template #default="{ row }">{{ row.end_date?.slice(0, 10) }}</template>
          </el-table-column>
          <el-table-column label="所属轮转">
            <template #default="{ row }">{{ row.plan?.name }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 新建/编辑轮转 -->
    <el-dialog v-model="planDialog" :title="editingPlan ? '编辑轮转' : '新建轮转'" width="460px">
      <el-form :model="planForm" label-width="90px">
        <el-form-item label="名称" required><el-input v-model="planForm.name" placeholder="如：2026年秋季轮转" /></el-form-item>
        <el-form-item label="轮转周期" required>
          <el-date-picker v-model="planForm.range" type="daterange" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注"><el-input v-model="planForm.remark" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="planDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSavePlan">保存</el-button>
      </template>
    </el-dialog>

    <!-- 管理：参与学生 + 分段安排 -->
    <el-drawer v-model="manageDrawer" size="680px">
      <template #header>
        <span>{{ currentPlan?.name }}（{{ currentPlan?.start_date?.slice(0, 10) }} ~ {{ currentPlan?.end_date?.slice(0, 10) }}）</span>
      </template>

      <el-divider content-position="left">参与学生（{{ members.length }}）</el-divider>
      <div style="margin-bottom: 8px">
        <el-tag
          v-for="m in members"
          :key="m.id"
          closable
          style="margin: 0 8px 8px 0"
          @close="onRemoveMember(m)"
        >{{ m.name }}（{{ m.username }}）</el-tag>
        <el-empty v-if="!members.length" description="还没有学生，请先添加" :image-size="60" />
      </div>
      <div style="display: flex; gap: 8px; margin-bottom: 16px">
        <el-select v-model="memberToAdd" multiple filterable placeholder="选择学生（可多选）" style="flex: 1">
          <el-option v-for="s in addableStudents" :key="s.id" :label="`${s.name}（${s.username}）`" :value="s.id" />
        </el-select>
        <el-button type="primary" :disabled="!memberToAdd.length" @click="onAddMembers">添加</el-button>
      </div>

      <el-divider content-position="left">分段安排（学生 + 科室 + 起止日期，时间段不能重叠）</el-divider>
      <el-button type="primary" size="small" :icon="Plus" style="margin-bottom: 8px" :disabled="!members.length" @click="addAssignRow">
        添加分段
      </el-button>
      <el-table :data="assignRows" size="small" style="margin-bottom: 12px">
        <el-table-column label="学生" width="140">
          <template #default="{ row }">
            <el-select v-model="row.student_id" filterable size="small">
              <el-option v-for="s in members" :key="s.id" :label="s.name" :value="s.id" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="科室" width="150">
          <template #default="{ row }">
            <el-tree-select v-model="row.department_id" :data="departments" :props="{ label: 'name', children: 'children' }" node-key="id" check-strictly size="small" />
          </template>
        </el-table-column>
        <el-table-column label="起止日期" width="230">
          <template #default="{ row }">
            <el-date-picker v-model="row.range" type="daterange" value-format="YYYY-MM-DD" size="small" style="width: 220px" />
          </template>
        </el-table-column>
        <el-table-column width="60">
          <template #default="{ $index }">
            <el-button link type="danger" size="small" @click="assignRows.splice($index, 1)">移除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-button v-if="assignRows.length" type="primary" :loading="saving" style="margin-bottom: 20px" @click="onSubmitAssign">
        提交分段
      </el-button>

      <el-divider content-position="left">已有分段</el-divider>
      <el-table :data="planDetails" size="small" v-loading="loadingDetails">
        <el-table-column label="学生" width="110">
          <template #default="{ row }">{{ row.student?.name }}</template>
        </el-table-column>
        <el-table-column label="科室" width="110">
          <template #default="{ row }">{{ row.department?.name }}</template>
        </el-table-column>
        <el-table-column label="起止" min-width="170">
          <template #default="{ row }">{{ row.start_date?.slice(0, 10) }} ~ {{ row.end_date?.slice(0, 10) }}</template>
        </el-table-column>
        <el-table-column width="70">
          <template #default="{ row }">
            <el-button link type="danger" size="small" @click="onDeleteDetail(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-drawer>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import {
  listRotationPlans, createRotationPlan, updateRotationPlan, deleteRotationPlan,
  listRotationMembers, addRotationMembers, removeRotationMember,
  listRotationDetails, assignRotation, deleteRotationDetail,
  myRotationCalendar, departmentStudents,
  type RotationPlan, type RotationDetail,
} from '@/api/rotation'
import { listDepartments, listUsers, type Department, type UserItem } from '@/api/system'

const auth = useAuthStore()
const tab = ref(auth.isAdmin ? 'plans' : 'mine')

const plans = ref<RotationPlan[]>([])
const myRotations = ref<RotationDetail[]>([])
const departments = ref<Department[]>([])
const allStudents = ref<UserItem[]>([])

const loadingPlans = ref(false)
const loadingMine = ref(false)
const loadingDept = ref(false)
const loadingDetails = ref(false)
const saving = ref(false)

const planDialog = ref(false)
const editingPlan = ref<RotationPlan | null>(null)
const planForm = reactive({ name: '', range: null as [string, string] | null, remark: '' })

const manageDrawer = ref(false)
const currentPlan = ref<RotationPlan | null>(null)
const members = ref<{ id: number; name: string; username: string }[]>([])
const memberToAdd = ref<number[]>([])
const planDetails = ref<RotationDetail[]>([])
const assignRows = ref<{ student_id: number | null; department_id: number | null; range: [string, string] | null }[]>([])

const deptQuery = reactive({ department_id: null as number | null, date: '' })
const deptStudents = ref<RotationDetail[]>([])

const addableStudents = computed(() => {
  const ids = new Set(members.value.map((m) => m.id))
  return allStudents.value.filter((s) => !ids.has(s.id))
})

const planStatusText = (s: string) => ({ draft: '草稿', active: '启用中', finished: '已完成' }[s] ?? s)
const planStatusTag = (s: string) => ({ draft: 'info', active: 'success', finished: 'warning' }[s] ?? 'info')

async function loadPlans() {
  loadingPlans.value = true
  try {
    plans.value = (await listRotationPlans()).list
  } finally {
    loadingPlans.value = false
  }
}

async function loadMine() {
  loadingMine.value = true
  try {
    myRotations.value = (await myRotationCalendar()).list
  } finally {
    loadingMine.value = false
  }
}

async function loadRefs() {
  departments.value = (await listDepartments()).list
  if (auth.isAdmin) {
    try {
      allStudents.value = (await listUsers({ page: 1, page_size: 500, type: 'student' })).list
    } catch {
      allStudents.value = []
    }
  }
}

function openCreatePlan() {
  editingPlan.value = null
  planForm.name = ''
  planForm.range = null
  planForm.remark = ''
  planDialog.value = true
}

function openEditPlan(row: RotationPlan) {
  editingPlan.value = row
  planForm.name = row.name
  planForm.range = [row.start_date?.slice(0, 10), row.end_date?.slice(0, 10)]
  planForm.remark = row.remark || ''
  planDialog.value = true
}

async function onSavePlan() {
  if (!planForm.name || !planForm.range) {
    ElMessage.warning('请填写名称和轮转周期')
    return
  }
  saving.value = true
  try {
    const payload = { name: planForm.name, start_date: planForm.range[0], end_date: planForm.range[1], remark: planForm.remark || undefined }
    if (editingPlan.value) {
      await updateRotationPlan(editingPlan.value.id, payload)
    } else {
      await createRotationPlan(payload)
    }
    ElMessage.success('已保存')
    planDialog.value = false
    await loadPlans()
  } finally {
    saving.value = false
  }
}

async function setPlanStatus(row: RotationPlan, status: string) {
  await updateRotationPlan(row.id, {
    name: row.name,
    start_date: row.start_date?.slice(0, 10),
    end_date: row.end_date?.slice(0, 10),
    status,
  })
  ElMessage.success('已更新')
  await loadPlans()
}

async function onDeletePlan(row: RotationPlan) {
  await ElMessageBox.confirm(`确定删除轮转「${row.name}」及其全部分段吗？`, '提示', { type: 'warning' })
  await deleteRotationPlan(row.id)
  ElMessage.success('已删除')
  await loadPlans()
}

async function openManage(plan: RotationPlan) {
  currentPlan.value = plan
  assignRows.value = []
  memberToAdd.value = []
  manageDrawer.value = true
  loadingDetails.value = true
  try {
    const [detailRes, memberRes] = await Promise.all([listRotationDetails(plan.id), listRotationMembers(plan.id)])
    planDetails.value = detailRes.list
    members.value = memberRes.list
  } finally {
    loadingDetails.value = false
  }
}

async function onAddMembers() {
  if (!currentPlan.value || !memberToAdd.value.length) return
  await addRotationMembers(currentPlan.value.id, memberToAdd.value)
  ElMessage.success('已添加')
  memberToAdd.value = []
  members.value = (await listRotationMembers(currentPlan.value.id)).list
  await loadPlans()
}

async function onRemoveMember(m: { id: number; name: string }) {
  if (!currentPlan.value) return
  await ElMessageBox.confirm(`把学生「${m.name}」移出本轮转？其分段安排会一并删除。`, '提示', { type: 'warning' })
  await removeRotationMember(currentPlan.value.id, m.id)
  ElMessage.success('已移出')
  members.value = (await listRotationMembers(currentPlan.value.id)).list
  planDetails.value = (await listRotationDetails(currentPlan.value.id)).list
  await loadPlans()
}

function addAssignRow() {
  assignRows.value.push({ student_id: null, department_id: null, range: null })
}

async function onSubmitAssign() {
  if (!currentPlan.value) return
  const items = assignRows.value
    .filter((r) => r.student_id && r.department_id && r.range)
    .map((r) => ({ student_id: r.student_id!, department_id: r.department_id!, start_date: r.range![0], end_date: r.range![1] }))
  if (!items.length) {
    ElMessage.warning('请完整填写至少一条分段')
    return
  }
  saving.value = true
  try {
    await assignRotation(currentPlan.value.id, items)
    ElMessage.success('分配成功')
    assignRows.value = []
    planDetails.value = (await listRotationDetails(currentPlan.value.id)).list
    await loadPlans()
  } finally {
    saving.value = false
  }
}

async function onDeleteDetail(row: RotationDetail) {
  await deleteRotationDetail(row.id)
  ElMessage.success('已删除')
  if (currentPlan.value) {
    planDetails.value = (await listRotationDetails(currentPlan.value.id)).list
  }
}

async function loadDeptStudents() {
  if (!deptQuery.department_id) {
    ElMessage.warning('请选择科室')
    return
  }
  loadingDept.value = true
  try {
    deptStudents.value = (await departmentStudents(deptQuery.department_id, deptQuery.date || undefined)).list
  } finally {
    loadingDept.value = false
  }
}

onMounted(async () => {
  await loadRefs()
  await Promise.all([loadPlans(), loadMine()])
})
</script>
