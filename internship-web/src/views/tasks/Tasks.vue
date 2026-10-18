<template>
  <el-card>
    <el-tabs v-model="tab">
      <el-tab-pane label="病历" name="patient-cases">
        <TaskTable kind="patient-cases" title="病历" :columns="caseColumns" :form-fields="caseFields" />
      </el-tab-pane>
      <el-tab-pane label="病程记录" name="medical-progresses">
        <TaskTable kind="medical-progresses" title="病程记录" :columns="progressColumns" :form-fields="progressFields" />
      </el-tab-pane>
      <el-tab-pane label="操作/手术记录" name="operation-records">
        <TaskTable kind="operation-records" title="操作记录" :columns="operationColumns" :form-fields="operationFields" />
      </el-tab-pane>
    </el-tabs>
  </el-card>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import TaskTable from './TaskTable.vue'
import type { TaskColumn, TaskField } from './TaskTable.vue'

const tab = ref('patient-cases')

const caseColumns: TaskColumn[] = [
  { key: 'patient_name', label: '患者', width: 100 },
  { key: 'admission_no', label: '住院号', width: 120 },
  { key: 'title', label: '标题', minWidth: 200 },
]
const caseFields: TaskField[] = [
  { key: 'patient_name', label: '患者姓名', type: 'input', required: true },
  { key: 'admission_no', label: '住院号', type: 'input' },
  { key: 'title', label: '标题', type: 'input', required: true, placeholder: '如：入院记录' },
  { key: 'content', label: '内容', type: 'textarea', required: true, rows: 8 },
]

const progressColumns: TaskColumn[] = [
  { key: 'title', label: '标题', minWidth: 220 },
]
const progressFields: TaskField[] = [
  { key: 'title', label: '标题', type: 'input', required: true, placeholder: '如：病程记录-术后第一天' },
  { key: 'content', label: '内容', type: 'textarea', required: true, rows: 8 },
]

const operationColumns: TaskColumn[] = [
  { key: 'project_name', label: '项目', minWidth: 160 },
  { key: 'type', label: '类型', width: 90, format: (v) => (v === 'surgery' ? '手术' : '操作') },
  { key: 'operation_date', label: '日期', width: 110 },
  { key: 'role', label: '角色', width: 80 },
]
const operationFields: TaskField[] = [
  { key: 'type', label: '类型', type: 'select', required: true, options: [
    { label: '临床操作', value: 'operation' },
    { label: '手术', value: 'surgery' },
  ] },
  { key: 'project_name', label: '项目名称', type: 'input', required: true, placeholder: '如：阑尾切除术' },
  { key: 'patient_name', label: '患者姓名', type: 'input' },
  { key: 'operation_date', label: '日期', type: 'date', required: true },
  { key: 'role', label: '参与角色', type: 'select', options: [
    { label: '主刀', value: '主刀' },
    { label: '助手', value: '助手' },
    { label: '观摩', value: '观摩' },
  ] },
  { key: 'content', label: '记录内容', type: 'textarea', required: true, rows: 8 },
]
</script>
