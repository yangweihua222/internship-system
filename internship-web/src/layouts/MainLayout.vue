<template>
  <el-container class="layout">
    <el-aside width="220px" class="aside">
      <div class="logo">实习管理系统</div>
      <el-menu
        :default-active="$route.path"
        router
        background-color="#001529"
        text-color="#a6adb4"
        active-text-color="#ffffff"
      >
        <el-menu-item index="/dashboard">
          <el-icon><Odometer /></el-icon>
          <span>工作台</span>
        </el-menu-item>
        <el-menu-item index="/rotation">
          <el-icon><Refresh /></el-icon>
          <span>轮转管理</span>
        </el-menu-item>
        <el-menu-item index="/attendance">
          <el-icon><AlarmClock /></el-icon>
          <span>考勤管理</span>
        </el-menu-item>
        <el-menu-item index="/duty">
          <el-icon><Calendar /></el-icon>
          <span>值班管理</span>
        </el-menu-item>
        <el-menu-item index="/oa">
          <el-icon><DocumentChecked /></el-icon>
          <span>OA 审批</span>
        </el-menu-item>
        <el-menu-item index="/tasks">
          <el-icon><EditPen /></el-icon>
          <span>实习任务</span>
        </el-menu-item>
        <el-menu-item index="/evaluation">
          <el-icon><Star /></el-icon>
          <span>考核评价</span>
        </el-menu-item>
        <el-menu-item index="/events">
          <el-icon><Flag /></el-icon>
          <span>教学活动</span>
        </el-menu-item>
        <el-menu-item index="/reports">
          <el-icon><Notebook /></el-icon>
          <span>实习报告</span>
        </el-menu-item>
        <el-menu-item index="/notices">
          <el-icon><Bell /></el-icon>
          <span>通知公告</span>
        </el-menu-item>
        <template v-if="auth.isAdmin">
          <el-sub-menu index="system">
            <template #title>
              <el-icon><Setting /></el-icon>
              <span>系统管理</span>
            </template>
            <el-menu-item index="/system/departments">
              <el-icon><OfficeBuilding /></el-icon>
              <span>科室管理</span>
            </el-menu-item>
            <el-menu-item index="/system/users">
              <el-icon><User /></el-icon>
              <span>用户管理</span>
            </el-menu-item>
          </el-sub-menu>
        </template>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">
        <div class="header-title">{{ $route.meta.title }}</div>
        <el-dropdown @command="onCommand">
          <span class="user-info">
            {{ auth.user?.name }}
            <el-tag size="small" style="margin-left: 6px">{{ roleTitle }}</el-tag>
            <el-icon><ArrowDown /></el-icon>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="logout">退出登录</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </el-header>
      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
    <AiChat />
  </el-container>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { logout } from '@/api/auth'
import AiChat from '@/components/AiChat.vue'

const auth = useAuthStore()
const router = useRouter()

const roleTitle = computed(() => auth.user?.roles?.[0]?.title || '未分配角色')

async function onCommand(command: string) {
  if (command === 'logout') {
    try {
      await logout()
    } catch {
      // 忽略登出接口异常，本地照常清理
    }
    auth.clear()
    router.push('/login')
  }
}
</script>

<style scoped>
.layout {
  height: 100%;
}
.aside {
  background-color: #001529;
}
.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  color: #fff;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 2px;
}
.aside :deep(.el-menu) {
  border-right: none;
}
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #e8e8e8;
  background: #fff;
}
.header-title {
  font-size: 16px;
  font-weight: 600;
}
.user-info {
  display: flex;
  align-items: center;
  cursor: pointer;
  color: #333;
}
.main {
  background: #f5f6f8;
}
</style>
