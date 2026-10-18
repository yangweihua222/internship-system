import http from './http'

// ---------- 科室 ----------
export interface Department {
  id: number
  name: string
  parent_id: number | null
  sort: number
  children?: Department[]
}

export function listDepartments(params?: { keyword?: string }) {
  return http.get<never, { list: Department[] }>('/departments', { params })
}

export function createDepartment(data: { name: string; parent_id: number | null; sort?: number }) {
  return http.post('/departments', data)
}

export function updateDepartment(id: number, data: { name: string; parent_id: number | null; sort?: number }) {
  return http.put(`/departments/${id}`, data)
}

export function deleteDepartment(id: number) {
  return http.delete(`/departments/${id}`)
}

// ---------- 用户 ----------
export interface UserItem {
  id: number
  username: string
  name: string
  type: string
  phone: string | null
  status: number
  department?: { id: number; name: string } | null
  roles: { id: number; name: string; title: string }[]
}

export function listUsers(params: { keyword?: string; type?: string; page?: number; page_size?: number }) {
  return http.get<never, { list: UserItem[]; total: number; page: number; page_size: number }>('/users', { params })
}

export function createUser(data: {
  username: string
  password: string
  name: string
  type: string
  phone?: string
  department_id?: number | null
  role_names?: string[]
}) {
  return http.post('/users', data)
}

export function updateUser(
  id: number,
  data: {
    name?: string
    password?: string
    type?: string
    phone?: string
    department_id?: number | null
    status?: number
    role_names?: string[]
  },
) {
  return http.put(`/users/${id}`, data)
}

export function deleteUser(id: number) {
  return http.delete(`/users/${id}`)
}

// ---------- 角色 ----------
export interface RoleItem {
  id: number
  name: string
  title: string
}

export function listRoles() {
  return http.get<never, { list: RoleItem[] }>('/roles')
}
