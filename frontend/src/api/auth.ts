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

/**
 * Always resolves for a valid address shape, whether or not it is registered —
 * the API deliberately gives the same answer either way so the endpoint cannot
 * be used to discover which accounts exist.
 */
export async function forgotPassword(email: string): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email })
  return data.message
}

export async function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/reset-password', payload)
  return data.message
}

/** Revokes every token, so the user signs in again afterwards. */
export async function changePassword(payload: {
  current_password: string
  password: string
  password_confirmation: string
}): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/change-password', payload)
  return data.message
}
