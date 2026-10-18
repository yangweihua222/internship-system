<template>
  <div>
    <el-row :gutter="16">
      <el-col :span="6" v-for="card in cards" :key="card.label">
        <el-card shadow="hover">
          <div style="font-size: 13px; color: #999">{{ card.label }}</div>
          <div style="font-size: 28px; font-weight: 600; margin-top: 6px">{{ card.value }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="12">
        <el-card>
          <template #header>本月出勤</template>
          <el-progress type="dashboard" :percentage="data?.summary.month_attendance_rate ?? 0" :stroke-width="16">
            <template #default="{ percentage }">
              <div style="font-size: 24px; font-weight: 600">{{ percentage }}%</div>
              <div style="font-size: 12px; color: #999">出勤率</div>
            </template>
          </el-progress>
          <div style="margin-top: 12px; color: #666; font-size: 13px">
            应出勤 {{ data?.summary.month_scheduled_days ?? 0 }} 天 ·
            实出勤 {{ data?.summary.month_attended_days ?? 0 }} 天 ·
            请假 {{ data?.summary.month_leave_days ?? 0 }} 天
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card>
          <template #header>近 6 个月出勤趋势</template>
          <div v-for="t in data?.attendance_trend ?? []" :key="t.month" style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px">
            <span style="width: 64px; color: #999; font-size: 13px">{{ t.month }}</span>
            <el-progress :percentage="t.rate" :stroke-width="12" style="flex: 1" />
            <span style="width: 90px; font-size: 12px; color: #999">{{ t.attended }}/{{ t.scheduled }} 天</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 16px">
      <template #header>评价均分 TOP10</template>
      <el-table :data="data?.evaluation_top ?? []" size="small">
        <el-table-column prop="target" label="被评价人" width="140" />
        <el-table-column label="类型" width="90">
          <template #default="{ row }">{{ row.type === 'teacher' ? '老师' : '学生' }}</template>
        </el-table-column>
        <el-table-column prop="avg_score" label="均分" width="90" />
        <el-table-column prop="count" label="评价次数" />
      </el-table>
      <el-empty v-if="!data?.evaluation_top?.length" description="暂无评价数据" />
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { dashboardStats, type DashboardData } from '@/api/teaching'

const data = ref<DashboardData | null>(null)

const cards = computed(() => [
  { label: '在册学生', value: data.value?.summary.students ?? '-' },
  { label: '在册老师', value: data.value?.summary.teachers ?? '-' },
  { label: '今天在科轮转', value: data.value?.summary.active_rotations ?? '-' },
  { label: '待审批 OA', value: data.value?.summary.pending_oa ?? '-' },
])

onMounted(async () => {
  data.value = await dashboardStats()
})
</script>
