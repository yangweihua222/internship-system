import http from './http'

export interface DutySchedule {
  id: number
  user_id: number
  department_id?: number | null
  duty_date: string
  shift_label: string
  type: 'teacher' | 'student'
  remark?: string
  user?: { id: number; name: string; username: string; type: string }
  department?: { id: number; name: string }
}

export interface DutyLog {
  id: number
  duty_schedule_id?: number | null
  author_id: number
  duty_date: string
  title: string
  content: string
  author?: { id: number; name: string; type: string }
  viewers?: { id: number; name: string }[]
}

export const listDutySchedules = (params: { month: string; type?: string; department_id?: number }) =>
  http.get<never, { list: DutySchedule[] }>('/duty-schedules', { params })
export const myDutySchedules = (month: string) =>
  http.get<never, { list: DutySchedule[] }>('/duty-schedules/mine', { params: { month } })
export const bulkDutySchedule = (data: {
  user_ids: number[]
  department_id?: number | null
  shift_label: string
  start_date: string
  end_date: string
  weekdays?: number[]
}) => http.post<never, { created: number; skipped: number }>('/duty-schedules', data)
export const deleteDutySchedule = (id: number) =>
  http.delete(`/duty-schedules/${id}`)

export const listDutyLogs = () => http.get<never, { list: DutyLog[] }>('/duty-logs')
export const createDutyLog = (data: {
  duty_schedule_id?: number | null
  duty_date: string
  title: string
  content: string
  viewer_ids?: number[]
}) => http.post('/duty-logs', data)
export const updateDutyLog = (id: number, data: Partial<DutyLog> & { viewer_ids?: number[] }) =>
  http.put(`/duty-logs/${id}`, data)
export const deleteDutyLog = (id: number) => http.delete(`/duty-logs/${id}`)
