import api from './client'
import type { DashboardResponse, LoginResponse, User } from '../types/auth'

export async function login(email: string, password: string): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/auth/login', {
    email,
    password,
    device_name: 'web',
  })
  return data
}

export async function fetchMe(): Promise<User> {
  const { data } = await api.get<{ user: User }>('/auth/me')
  return data.user
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}

export async function fetchDashboard(): Promise<DashboardResponse['data']> {
  const { data } = await api.get<DashboardResponse>('/dashboard')
  return data.data
}
