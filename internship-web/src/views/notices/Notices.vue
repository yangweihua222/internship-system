<template>
  <el-card>
    <div style="display: flex; justify-content: flex-end; margin-bottom: 12px">
      <el-button v-if="auth.isAdmin" type="primary" :icon="Plus" @click="openCreate">发布公告</el-button>
    </div>
    <el-table :data="list" v-loading="loading">
      <el-table-column prop="title" label="标题" min-width="220" />
      <el-table-column label="发布人" width="120">
        <template #default="{ row }">{{ row.publisher?.name }}</template>
      </el-table-column>
      <el-table-column label="发布时间" width="180">
        <template #default="{ row }">{{ new Date(row.published_at).toLocaleString('zh-CN', { hour12: false }) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="140">
        <template #default="{ row }">
          <el-button link type="primary" @click="view(row)">查看</el-button>
          <el-button v-if="auth.isAdmin" link type="danger" @click="onDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="createDialog" title="发布公告" width="560px">
      <el-form label-width="70px">
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="内容" required><el-input v-model="form.content" type="textarea" :rows="8" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">发布</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="viewDialog" :title="current?.title" width="560px">
      <div style="color: #999; font-size: 13px; margin-bottom: 8px">
        {{ current?.publisher?.name }} · {{ current ? new Date(current.published_at).toLocaleString('zh-CN', { hour12: false }) : '' }}
      </div>
      <div style="white-space: pre-wrap">{{ current?.content }}</div>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import { listNotices, createNotice, deleteNotice, type Notice } from '@/api/teaching'

const auth = useAuthStore()
const list = ref<Notice[]>([])
const loading = ref(false)
const saving = ref(false)
const createDialog = ref(false)
const viewDialog = ref(false)
const current = ref<Notice | null>(null)
const form = reactive({ title: '', content: '' })

async function load() {
  loading.value = true
  try {
    list.value = (await listNotices()).list
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.title = ''
  form.content = ''
  createDialog.value = true
}

async function onSave() {
  if (!form.title || !form.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  saving.value = true
  try {
    await createNotice({ title: form.title, content: form.content })
    ElMessage.success('发布成功')
    createDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function onDelete(row: Notice) {
  await ElMessageBox.confirm(`确定删除公告「${row.title}」吗？`, '提示', { type: 'warning' })
  await deleteNotice(row.id)
  ElMessage.success('已删除')
  await load()
}

function view(row: Notice) {
  current.value = row
  viewDialog.value = true
}

onMounted(load)
</script>
