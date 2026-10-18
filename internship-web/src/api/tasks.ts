import http from './http'

export interface TaskItem {
  id: number
  student_id: number
  department_id?: number | null
  title: string
  content: string
  status: 'draft' | 'submitted' | 'approved' | 'rejected'
  score?: number | null
  comment?: string | null
  reviewer_id?: number | null
  created_at: string
  student?: { id: number; name: string }
  department?: { id: number; name: string }
  reviewer?: { id: number; name: string }
  // patient case
  patient_name?: string
  admission_no?: string
  // operation record
  type?: string
  project_name?: string
  operation_date?: string
  role?: string
  // medical progress
  patient_case_id?: number | null
}

export type TaskKind = 'patient-cases' | 'medical-progresses' | 'operation-records'

export const listTasks = (kind: TaskKind, params?: { status?: string; student_id?: number }) =>
  http.get<never, { list: TaskItem[] }>(`/${kind}`, { params })
export const createTask = (kind: TaskKind, data: Record<string, unknown>) =>
  http.post(`/${kind}`, data)
export const updateTask = (kind: TaskKind, id: number, data: Record<string, unknown>) =>
  http.put(`/${kind}/${id}`, data)
export const deleteTask = (kind: TaskKind, id: number) =>
  http.delete(`/${kind}/${id}`)
export const reviewTask = (kind: TaskKind, id: number, data: { action: 'approve' | 'reject'; score?: number; comment?: string }) =>
  http.post(`/${kind}/${id}/review`, data)

// 实习报告
export interface ReportItem extends TaskItem {
  type?: 'summary' | 'report'
  period_start?: string | null
  period_end?: string | null
}
export const listReports = (params?: { status?: string }) =>
  http.get<never, { list: ReportItem[] }>('/internship-reports', { params })
export const createReport = (data: Record<string, unknown>) =>
  http.post('/internship-reports', data)
export const updateReport = (id: number, data: Record<string, unknown>) =>
  http.put(`/internship-reports/${id}`, data)
export const deleteReport = (id: number) =>
  http.delete(`/internship-reports/${id}`)
export const reviewReport = (id: number, data: { action: 'approve' | 'reject'; score?: number; comment?: string }) =>
  http.post(`/internship-reports/${id}/review`, data)
