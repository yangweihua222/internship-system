import http from './http'

export interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  late_threshold_minutes: number
}

export interface AttendanceGroup {
  id: number
  name: string
  department_id?: number | null
  lat?: string | null
  lng?: string | null
  radius_m: number
  remark?: string
  department?: { id: number; name: string }
  members?: { id: number; name: string; username: string }[]
}

export interface AttendanceSchedule {
  id: number
  user_id: number
  work_date: string
  shift_id: number
  group_id: number
  user?: { id: number; name: string; username: string }
  shift?: Shift
  group?: { id: number; name: string }
}

export interface CheckinDay {
  work_date: string
  shift?: Shift
  group?: { id: number; name: string }
  status: 'normal' | 'late' | 'early' | 'absent' | 'leave' | 'unknown'
  check_in_at?: string | null
  check_out_at?: string | null
  late_minutes: number
  remark?: string | null
  logs: CheckinLog[]
}

export interface CheckinLog {
  id: number
  check_type: 'in' | 'out'
  checked_at: string
  result: string
  distance_m?: number | null
  source: string
}

export interface MonthlyStat {
  user_id: number
  username: string
  name: string
  scheduled_days: number
  normal: number
  late: number
  early: number
  absent: number
  leave: number
}

// 班次
export const listShifts = () => http.get<never, { list: Shift[] }>('/shifts')
export const createShift = (data: {
  name: string
  start_time: string
  end_time: string
  late_threshold_minutes?: number
}) => http.post('/shifts', data)
export const updateShift = (id: number, data: Partial<Shift>) =>
  http.put(`/shifts/${id}`, data)
export const deleteShift = (id: number) => http.delete(`/shifts/${id}`)

// 考勤组
export const listAttendanceGroups = () =>
  http.get<never, { list: AttendanceGroup[] }>('/attendance-groups')
export const createAttendanceGroup = (data: Partial<AttendanceGroup>) =>
  http.post('/attendance-groups', data)
export const updateAttendanceGroup = (id: number, data: Partial<AttendanceGroup>) =>
  http.put(`/attendance-groups/${id}`, data)
export const deleteAttendanceGroup = (id: number) =>
  http.delete(`/attendance-groups/${id}`)
export const assignGroupMembers = (id: number, user_ids: number[]) =>
  http.post(`/attendance-groups/${id}/members`, { user_ids })

// 排班
export const listSchedules = (params: { month: string; group_id?: number; user_id?: number }) =>
  http.get<never, { list: AttendanceSchedule[] }>('/attendance/schedules', { params })
export const bulkSchedule = (data: {
  user_ids: number[]
  group_id: number
  shift_id: number
  start_date: string
  end_date: string
  weekdays?: number[]
}) => http.post<never, { created: number; skipped: number }>('/attendance/schedules', data)
export const deleteSchedule = (id: number) =>
  http.delete(`/attendance/schedules/${id}`)

// 打卡
export const checkin = (data: { check_type: 'in' | 'out'; lat?: number; lng?: number }) =>
  http
    .post<never, { message: string; result: string; distance_m?: number }>('/attendance/checkin', data)
    
export const myCheckinRecords = (month: string) =>
  http.get<never, { month: string; days: CheckinDay[] }>('/attendance/my-records', { params: { month } })
export const attendanceStatistics = (params: { month: string; group_id?: number }) =>
  http.get<never, { month: string; list: MonthlyStat[] }>('/attendance/statistics', { params })
