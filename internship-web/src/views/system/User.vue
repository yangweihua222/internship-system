<template>
  <el-card>
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px">
      <div style="display: flex; gap: 8px">
        <el-input
          v-model="query.keyword"
          placeholder="用户名 / 姓名 / 手机号"
          clearable
          style="width: 220px"
          @change="load(1)"
        />
        <el-select v-model="query.type" placeholder="用户类型" clearable style="width: 140px" @change="load(1)">
          <el-option label="管理员" value="admin" />
          <el-option label="老师" value="teacher" />
          <el-option label="学生" value="student" />
        </el-select>
      </div>
      <el-button type="primary" :icon="Plus" @click="openCreate">新增用户</el-button>
    </div>

    <el-table :data="list" v-loading="loading">
      <el-table-column prop="username" label="用户名" width="140" />
      <el-table-column prop="name" label="姓名" width="120" />
      <el-table-column label="类型" width="90">
        <template #default="{ row }">
          <el-tag :type="typeTag[row.type]">{{ typeText[row.type] }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="department?.name" label="科室" width="160">
        <template #default="{ row }">{{ row.department?.name || '-' }}</template>
      </el-table-column>
      <el-table-column prop="phone" label="手机号" width="140">
        <template #default="{ row }">{{ row.phone || '-' }}</template>
      </el-table-column>
      <el-table-column label="角色" min-width="180">
        <template #default="{ row }">
          <el-tag v-for="r in row.roles" :key="r.id" size="small" style="margin-right: 4px">
            {{ r.title }}
          </el-tag>
          <span v-if="!row.roles?.length">-</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" :disabled="row.id === auth.user?.id" @click="onDelete(row)">
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-model:current-page="query.page"
      :page-size="query.page_size"
      :total="total"
      layout="total, prev, pager, next"
      style="margin-top: 12px; justify-content: flex-end"
      @current-change="load()"
    />

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑用户' : '新增用户'" width="480px">
      <el-form :model="form" label-width="80px">
        <el-form-item label="用户名" required>
          <el-input v-model="form.username" :disabled="!!form.id" placeholder="登录用户名" />
        </el-form-item>
        <el-form-item label="姓名" required>
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="密码" :required="!form.id">
          <el-input
            v-model="form.password"
            type="password"
            show-password
            :placeholder="form.id ? '留空则不修改密码' : '初始密码'"
          />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.type" style="width: 100%">
            <el-option label="管理员" value="admin" />
            <el-option label="老师" value="teacher" />
            <el-option label="学生" value="student" />
          </el-select>
        </el-form-item>
        <el-form-item label="科室">
          <el-tree-select
            v-model="form.department_id"
            :data="departmentTree"
            :props="{ label: 'name', children: 'children' }"
            node-key="id"
            check-strictly
            clearable
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model="form.phone" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role_names" multiple style="width: 100%">
            <el-option v-for="r in roles" :key="r.name" :label="r.title" :value="r.name" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.id" label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  listUsers,
  createUser,
  updateUser,
  deleteUser,
  listDepartments,
  listRoles,
  type UserItem,
  type Department,
  type RoleItem,
} from '@/api/system'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const loading = ref(false)
const saving = ref(false)
const list = ref<UserItem[]>([])
const total = ref(0)
const roles = ref<RoleItem[]>([])
const departmentTree = ref<Department[]>([])
const dialogVisible = ref(false)

const query = reactive({ keyword: '', type: '', page: 1, page_size: 10 })

const form = reactive<{
  id: number | null
  username: string
  name: string
  password: string
  type: string
  phone: string
  department_id: number | null
  status: number
  role_names: string[]
}>({
  id: null,
  username: '',
  name: '',
  password: '',
  type: 'student',
  phone: '',
  department_id: null,
  status: 1,
  role_names: [],
})

const typeText: Record<string, string> = { admin: '管理员', teacher: '老师', student: '学生' }
const typeTag: Record<string, string> = { admin: 'danger', teacher: 'warning', student: 'info' }

async function load(page?: number) {
  if (page) query.page = page
  loading.value = true
  try {
    const res = await listUsers({
      keyword: query.keyword || undefined,
      type: query.type || undefined,
      page: query.page,
      page_size: query.page_size,
    })
    list.value = res.list
    total.value = res.total
  } finally {
    loading.value = false
  }
}

async function loadRefs() {
  const [deptRes, roleRes] = await Promise.all([listDepartments(), listRoles()])
  departmentTree.value = deptRes.list
  roles.value = roleRes.list
}

function openCreate() {
  form.id = null
  form.username = ''
  form.name = ''
  form.password = ''
  form.type = 'student'
  form.phone = ''
  form.department_id = null
  form.status = 1
  form.role_names = []
  dialogVisible.value = true
}

function openEdit(row: UserItem) {
  form.id = row.id
  form.username = row.username
  form.name = row.name
  form.password = ''
  form.type = row.type
  form.phone = row.phone || ''
  form.department_id = row.department?.id ?? null
  form.status = row.status
  form.role_names = row.roles?.map((r) => r.name) || []
  dialogVisible.value = true
}

async function onSave() {
  if (!form.id && (!form.username.trim() || !form.name.trim() || !form.password)) {
    ElMessage.warning('请填写用户名、姓名和初始密码')
    return
  }
  saving.value = true
  try {
    if (form.id) {
      await updateUser(form.id, {
        name: form.name.trim(),
        password: form.password || undefined,
        type: form.type,
        phone: form.phone || undefined,
        department_id: form.department_id,
        status: form.status,
        role_names: form.role_names,
      })
    } else {
      await createUser({
        username: form.username.trim(),
        password: form.password,
        name: form.name.trim(),
        type: form.type,
        phone: form.phone || undefined,
        department_id: form.department_id,
        role_names: form.role_names,
      })
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function onDelete(row: UserItem) {
  await ElMessageBox.confirm(`确定删除用户「${row.name}」吗？`, '提示', { type: 'warning' })
  await deleteUser(row.id)
  ElMessage.success('删除成功')
  await load()
}

onMounted(async () => {
  await loadRefs()
  await load()
})
</script>
