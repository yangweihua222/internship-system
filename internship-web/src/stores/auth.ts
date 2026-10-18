import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { LoginUser } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string>(localStorage.getItem('token') || '')
  const user = ref<LoginUser | null>(
    localStorage.getItem('user') ? JSON.parse(localStorage.getItem('user') as string) : null,
  )

  const roleNames = computed(() => user.value?.roles?.map((r) => r.name) || [])
  const isAdmin = computed(() => roleNames.value.includes('admin') || roleNames.value.includes('teaching-admin'))

  function hasRole(name: string): boolean {
    return roleNames.value.includes(name)
  }

  function hasAnyRole(names: string[]): boolean {
    return names.some((n) => roleNames.value.includes(n))
  }

  function setLogin(newToken: string, newUser: LoginUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('token', newToken)
    localStorage.setItem('user', JSON.stringify(newUser))
  }

  function clear() {
    token.value = ''
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  return { token, user, roleNames, isAdmin, hasRole, hasAnyRole, setLogin, clear }
})
