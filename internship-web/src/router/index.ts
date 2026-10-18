import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/Login.vue'),
      meta: { title: '登录' },
    },
    {
      path: '/',
      component: () => import('@/layouts/MainLayout.vue'),
      redirect: '/dashboard',
      children: [
        { path: 'dashboard', name: 'dashboard', component: () => import('@/views/Dashboard.vue'), meta: { title: '工作台' } },
        { path: 'rotation', name: 'rotation', component: () => import('@/views/rotation/Rotation.vue'), meta: { title: '轮转管理' } },
        { path: 'attendance', name: 'attendance', component: () => import('@/views/attendance/Attendance.vue'), meta: { title: '考勤管理' } },
        { path: 'duty', name: 'duty', component: () => import('@/views/duty/Duty.vue'), meta: { title: '值班管理' } },
        { path: 'oa', name: 'oa', component: () => import('@/views/oa/Oa.vue'), meta: { title: 'OA 审批' } },
        { path: 'tasks', name: 'tasks', component: () => import('@/views/tasks/Tasks.vue'), meta: { title: '实习任务' } },
        { path: 'evaluation', name: 'evaluation', component: () => import('@/views/evaluation/Evaluation.vue'), meta: { title: '考核评价' } },
        { path: 'events', name: 'events', component: () => import('@/views/events/Events.vue'), meta: { title: '教学活动' } },
        { path: 'reports', name: 'reports', component: () => import('@/views/reports/Reports.vue'), meta: { title: '实习报告' } },
        { path: 'notices', name: 'notices', component: () => import('@/views/notices/Notices.vue'), meta: { title: '通知公告' } },
        {
          path: 'system/departments',
          name: 'system-departments',
          component: () => import('@/views/system/Department.vue'),
          meta: { title: '科室管理', adminOnly: true },
        },
        {
          path: 'system/users',
          name: 'system-users',
          component: () => import('@/views/system/User.vue'),
          meta: { title: '用户管理', adminOnly: true },
        },
      ],
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.name !== 'login' && !auth.token) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.token) {
    return { path: '/' }
  }
  if (to.meta.adminOnly && !auth.isAdmin) {
    return { path: '/dashboard' }
  }
  document.title = to.meta.title ? `${to.meta.title} - 实习管理系统` : '实习管理系统'
})

export default router
