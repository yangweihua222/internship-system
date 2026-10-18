<template>
  <el-card>
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px">
      <el-input
        v-model="keyword"
        placeholder="搜索科室名称"
        clearable
        style="width: 240px"
        @change="load"
      />
      <el-button type="primary" :icon="Plus" @click="openCreate(null)">新增一级科室</el-button>
    </div>

    <el-table :data="treeData" row-key="id" default-expand-all v-loading="loading">
      <el-table-column prop="name" label="科室名称" min-width="220" />
      <el-table-column prop="sort" label="排序" width="80" />
      <el-table-column label="操作" width="260">
        <template #default="{ row }">
          <el-button link type="primary" @click="openCreate(row.id)">新增子科室</el-button>
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="onDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑科室' : '新增科室'" width="420px">
      <el-form :model="form" label-width="80px">
        <el-form-item label="上级科室">
          <el-tree-select
            v-model="form.parent_id"
            :data="parentOptions"
            :props="{ label: 'name', children: 'children' }"
            node-key="id"
            check-strictly
            clearable
            placeholder="不选则为一级科室"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="名称" required>
          <el-input v-model="form.name" placeholder="科室名称" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" />
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
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  listDepartments,
  createDepartment,
  updateDepartment,
  deleteDepartment,
  type Department,
} from '@/api/system'

const loading = ref(false)
const saving = ref(false)
const keyword = ref('')
const treeData = ref<Department[]>([])
const dialogVisible = ref(false)

const form = reactive<{ id: number | null; name: string; parent_id: number | null; sort: number }>({
  id: null,
  name: '',
  parent_id: null,
  sort: 0,
})

// 上级科室可选范围：排除自己及自己的后代
const parentOptions = computed(() => {
  function filterTree(nodes: Department[]): Department[] {
    return nodes
      .filter((n) => n.id !== form.id)
      .map((n) => ({ ...n, children: n.children ? filterTree(n.children) : undefined }))
  }
  return filterTree(treeData.value)
})

async function load() {
  loading.value = true
  try {
    const res = await listDepartments({ keyword: keyword.value || undefined })
    treeData.value = res.list
  } finally {
    loading.value = false
  }
}

function openCreate(parentId: number | null) {
  form.id = null
  form.name = ''
  form.parent_id = parentId
  form.sort = 0
  dialogVisible.value = true
}

function openEdit(row: Department) {
  form.id = row.id
  form.name = row.name
  form.parent_id = row.parent_id
  form.sort = row.sort
  dialogVisible.value = true
}

async function onSave() {
  if (!form.name.trim()) {
    ElMessage.warning('请输入科室名称')
    return
  }
  saving.value = true
  try {
    const data = { name: form.name.trim(), parent_id: form.parent_id, sort: form.sort }
    if (form.id) {
      await updateDepartment(form.id, data)
    } else {
      await createDepartment(data)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function onDelete(row: Department) {
  await ElMessageBox.confirm(`确定删除科室「${row.name}」吗？`, '提示', { type: 'warning' })
  await deleteDepartment(row.id)
  ElMessage.success('删除成功')
  await load()
}

onMounted(load)
</script>
