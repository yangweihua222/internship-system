import http from './http'
import type { UserItem, RoleItem, Department } from './system'

export interface RotationPlan {
  id: number
  name: string
  start_date: string
  end_date: string
  status: 'draft' | 'active' | 'finished'
  remark?: string
  details_count?: number
  students_count?: number
}

export interface RotationDetail {
  id: number
  rotation_plan_id: number
  student_id: number
  department_id: number
  start_date: string
  end_date: string
  status: string
  is_current?: boolean
  student?: { id: number; name: string; username: string }
  department?: { id: number; name: string }
  plan?: { id: number; name: string; status: string }
}

// 轮转
export const listRotationPlans = () =>
  http.get<never, { list: RotationPlan[] }>('/rotation-plans')
export const createRotationPlan = (data: {
  name: string
  start_date: string
  end_date: string
  remark?: string
}) => http.post('/rotation-plans', data)
export const updateRotationPlan = (
  id: number,
  data: { name: string; start_date: string; end_date: string; remark?: string; status?: string },
) => http.put(`/rotation-plans/${id}`, data)
export const deleteRotationPlan = (id: number) =>
  http.delete(`/rotation-plans/${id}`)

// 轮转成员（参与学生）
export const listRotationMembers = (planId: number) =>
  http.get<never, { list: { id: number; name: string; username: string }[] }>(`/rotation-plans/${planId}/members`)
export const addRotationMembers = (planId: number, student_ids: number[]) =>
  http.post(`/rotation-plans/${planId}/members`, { student_ids })
export const removeRotationMember = (planId: number, studentId: number) =>
  http.delete(`/rotation-plans/${planId}/members/${studentId}`)

// 分段安排
export const listRotationDetails = (planId: number) =>
  http.get<never, { list: RotationDetail[] }>(`/rotation-plans/${planId}/details`)
export const assignRotation = (
  planId: number,
  items: { student_id: number; department_id: number; start_date: string; end_date: string }[],
) => http.post(`/rotation-plans/${planId}/assign`, { items })
export const deleteRotationDetail = (id: number) =>
  http.delete(`/rotation-details/${id}`)
export const myRotationCalendar = (student_id?: number) =>
  http.get<never, { list: RotationDetail[] }>('/rotations/calendar', { params: { student_id } })
export const departmentStudents = (department_id: number, date?: string) =>
  http
    .get<never, { date: string; list: RotationDetail[] }>('/rotations/department-students', {
      params: { department_id, date },
    })
    

export type { UserItem, RoleItem, Department }
