import http from './http'

// 评价
export interface EvaluationQuestion {
  id: number
  template_id: number
  content: string
  max_score: number
  sort: number
}

export interface EvaluationTemplate {
  id: number
  name: string
  target: 'student' | 'teacher' | 'discharge'
  description?: string
  is_active: boolean
  questions?: EvaluationQuestion[]
}

export interface EvaluationItem {
  id: number
  template_id: number
  total_score: number
  comment?: string
  period?: string
  created_at: string
  template?: { id: number; name: string; target: string }
  evaluator?: { id: number; name: string }
  target?: { id: number; name: string; type: string }
  department?: { id: number; name: string }
}

export const listEvalTemplates = (params?: { target?: string; active_only?: boolean }) =>
  http.get<never, { list: EvaluationTemplate[] }>('/evaluation-templates', { params })
export const createEvalTemplate = (data: {
  name: string
  target: string
  description?: string
  questions: { content: string; max_score: number }[]
}) => http.post('/evaluation-templates', data)
export const toggleEvalTemplate = (id: number) =>
  http.post(`/evaluation-templates/${id}/toggle`)
export const submitEvaluation = (data: {
  template_id: number
  target_user_id: number
  period?: string
  comment?: string
  answers: { question_id: number; score: number }[]
}) => http.post<never, { id: number; total_score: number }>('/evaluations', data)
export const listEvaluations = (params?: { scope?: string; target_user_id?: number; period?: string }) =>
  http.get<never, { list: EvaluationItem[] }>('/evaluations', { params })

// 教学活动
export interface TeachingEvent {
  id: number
  title: string
  content?: string
  start_at: string
  end_at: string
  location?: string
  status: 'published' | 'finished' | 'canceled'
  records_count?: number
  department?: { id: number; name: string }
  creator?: { id: number; name: string }
  my_record?: { id: number; sign_in_at?: string; sign_out_at?: string; score?: number } | null
}

export const listEvents = (status?: string) =>
  http.get<never, { list: TeachingEvent[] }>('/teaching-events', { params: { status } })
export const createEvent = (data: Record<string, unknown>) =>
  http.post('/teaching-events', data)
export const updateEventStatus = (id: number, status: string) =>
  http.put(`/teaching-events/${id}/status`, { status })
export const signEvent = (id: number, action: 'in' | 'out') =>
  http.post(`/teaching-events/${id}/sign`, { action })
export const eventRecords = (id: number) =>
  http
    .get<never, { list: { id: number; user_id: number; sign_in_at?: string; sign_out_at?: string; score?: number; comment?: string; user?: { id: number; name: string } }[] }>(
      `/teaching-events/${id}/records`,
    )
    
export const scoreEventRecord = (recordId: number, data: { score: number; comment?: string }) =>
  http.post(`/teaching-event-records/${recordId}/score`, data)

// 通知
export interface Notice {
  id: number
  title: string
  content: string
  published_at: string
  publisher?: { id: number; name: string }
}
export const listNotices = () => http.get<never, { list: Notice[] }>('/notices')
export const createNotice = (data: { title: string; content: string }) =>
  http.post('/notices', data)
export const deleteNotice = (id: number) => http.delete(`/notices/${id}`)

// 看板
export interface DashboardData {
  summary: {
    students: number
    teachers: number
    active_rotations: number
    pending_oa: number
    pending_tasks: number
    month_scheduled_days: number
    month_attended_days: number
    month_leave_days: number
    month_attendance_rate: number
  }
  attendance_trend: { month: string; scheduled: number; attended: number; rate: number }[]
  evaluation_top: { target: string; type: string; avg_score: number; count: number }[]
}
export const dashboardStats = () => http.get<never, DashboardData>('/stats/dashboard')
