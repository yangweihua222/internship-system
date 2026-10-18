import http from './http'

export interface LoginUser {
  id: number
  username: string
  name: string
  type: string
  roles: { id: number; name: string; title: string }[]
}

export function login(data: { username: string; password: string }) {
  return http.post<never, { token: string; user: LoginUser }>('/auth/login', data)
}

export function logout() {
  return http.post('/auth/logout')
}

export function me() {
  return http.get<never, { user: LoginUser }>('/auth/me')
}
