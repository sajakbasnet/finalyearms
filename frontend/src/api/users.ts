import api from './client'

export interface Role {
  id: number
  name: string
  slug: string
  description: string
}

export interface ManagedUser {
  id: number
  name: string
  email: string
  phone: string | null
  is_active: boolean
  role: Role | null
  department: {
    id: number
    name: string
    code: string
  } | null
  details: {
    employee_id?: string | null
    designation?: string | null
    registration_number?: string | null
    roll_number?: string | null
    batch?: string | null
  }
  created_at: string
}

export interface UserListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface UserFilters {
  role?: string
  department_id?: number
  search?: string
  is_active?: boolean
  page?: number
  per_page?: number
}

export async function fetchRoles(): Promise<Role[]> {
  const { data } = await api.get<{ data: Role[] }>('/admin/roles')
  return data.data
}

export async function fetchUsers(
  filters: UserFilters = {},
): Promise<{ users: ManagedUser[]; meta: UserListMeta }> {
  const { data } = await api.get<{ data: ManagedUser[]; meta: UserListMeta }>('/admin/users', {
    params: filters,
  })
  return {
    users: data.data,
    meta: data.meta,
  }
}

export interface CreateUserData {
  name: string
  email: string
  phone?: string | null
  password: string
  role_id: number
  is_active?: boolean
  department_id?: number | null
  employee_id?: string | null
  designation?: string | null
  registration_number?: string | null
  roll_number?: string | null
  batch_id?: number | null
}

export async function createUser(userData: CreateUserData): Promise<ManagedUser> {
  const { data } = await api.post<{ message: string; data: ManagedUser }>('/admin/users', userData)
  return data.data
}

export interface UpdateUserData {
  name: string
  email: string
  phone?: string | null
  password?: string | null
  role_id?: number
  is_active?: boolean
  department_id?: number | null
}

export async function updateUser(id: number, userData: UpdateUserData): Promise<ManagedUser> {
  const { data } = await api.put<{ message: string; data: ManagedUser }>(`/admin/users/${id}`, userData)
  return data.data
}

export async function deleteUser(id: number): Promise<void> {
  await api.delete(`/admin/users/${id}`)
}

export async function toggleUserStatus(id: number): Promise<ManagedUser> {
  const { data } = await api.patch<{ message: string; data: ManagedUser }>(`/admin/users/${id}/status`)
  return data.data
}
