import api from './client'

export interface DepartmentOption {
  id: number
  name: string
  code: string
  description?: string | null
  teachers_count?: number
  students_count?: number
}

export interface SessionOption {
  id: number
  name: string
  start_date: string
  end_date: string
  is_active: boolean
  dates?: SessionKeyDate[] | null
}

export interface SessionKeyDate {
  id: number
  label: string
  description?: string | null
  date: string
  is_deadline: boolean
}

export interface BatchOption {
  id: number
  name: string
  intake_year: number
  is_active: boolean
  students_count: number
  department: DepartmentOption | null
}

export interface BatchPayload {
  department_id: number
  name: string
  intake_year: number
  is_active?: boolean
}

export interface ProposalListItem {
  id: number
  title: string
  version_number: number
  status: string
  status_label: string
  submitted_at: string | null
  abstract: string | null
  project: {
    id: number
    title: string
    domain: string | null
    technology_stack: string | null
    status: string
  } | null
  student: {
    id: number
    name: string
    email: string
    registration_number: string
    department: DepartmentOption | null
  } | null
  supervisor: {
    id: number
    name: string
    email: string
  } | null
  academic_session: string | null
}

export interface StudentListItem {
  id: number
  name: string
  email: string
  phone: string | null
  registration_number: string
  roll_number: string | null
  batch: string | null
  batch_id: number | null
  department: DepartmentOption | null
  academic_session: { id: number; name: string } | null
  supervisor: {
    id: number
    name: string
    email: string
    employee_id: string
    designation: string | null
  } | null
}

export interface TeacherListItem {
  id: number
  employee_id: string
  designation: string | null
  max_projects: number
  active_students_count: number
  name: string
  email: string
  phone?: string | null
  department: DepartmentOption | null
}

export interface TeacherPayload {
  name: string
  email: string
  phone?: string
  password?: string
  employee_id: string
  designation?: string
  department_id: number
  max_projects?: number
}

export interface StudentPayload {
  name: string
  email: string
  phone?: string
  password?: string
  registration_number: string
  roll_number?: string
  batch_id?: number | null
  department_id: number
  academic_session_id: number
}

interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

function extractError(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    const firstFieldError = response?.data?.errors
      ? Object.values(response.data.errors)[0]?.[0]
      : undefined
    return firstFieldError ?? response?.data?.message ?? fallback
  }
  return fallback
}

export { extractError }

export async function fetchDepartments(): Promise<DepartmentOption[]> {
  const { data } = await api.get<{ data: DepartmentOption[] }>('/admin/departments')
  return data.data
}

export async function createDepartment(payload: {
  name: string
  code: string
  description?: string
}): Promise<DepartmentOption> {
  const { data } = await api.post<{ data: DepartmentOption }>('/admin/departments', payload)
  return data.data
}

export async function updateDepartment(
  id: number,
  payload: { name: string; code: string; description?: string },
): Promise<DepartmentOption> {
  const { data } = await api.put<{ data: DepartmentOption }>(`/admin/departments/${id}`, payload)
  return data.data
}

export async function deleteDepartment(id: number): Promise<void> {
  await api.delete(`/admin/departments/${id}`)
}

export async function fetchSessions(): Promise<SessionOption[]> {
  const { data } = await api.get<{ data: SessionOption[] }>('/admin/sessions')
  return data.data
}

export async function createSession(payload: {
  name: string
  start_date: string
  end_date: string
  is_active?: boolean
}): Promise<SessionOption> {
  const { data } = await api.post<{ data: SessionOption }>('/admin/sessions', payload)
  return data.data
}

export async function updateSession(
  id: number,
  payload: Partial<{ name: string; start_date: string; end_date: string }>,
): Promise<SessionOption> {
  const { data } = await api.put<{ data: SessionOption }>(`/admin/sessions/${id}`, payload)
  return data.data
}

export async function deleteSession(id: number): Promise<void> {
  await api.delete(`/admin/sessions/${id}`)
}

/** Makes one session current; the API stands every other one down. */
export async function activateSession(id: number): Promise<SessionOption> {
  const { data } = await api.post<{ data: SessionOption }>(`/admin/sessions/${id}/activate`)
  return data.data
}

export async function fetchSessionDates(sessionId: number): Promise<SessionKeyDate[]> {
  const { data } = await api.get<{ data: SessionKeyDate[] }>(`/admin/sessions/${sessionId}/dates`)
  return data.data
}

export async function createSessionDate(
  sessionId: number,
  payload: { label: string; date: string; description?: string; is_deadline?: boolean },
): Promise<SessionKeyDate> {
  const { data } = await api.post<{ data: SessionKeyDate }>(
    `/admin/sessions/${sessionId}/dates`,
    payload,
  )
  return data.data
}

export async function deleteSessionDate(sessionId: number, dateId: number): Promise<void> {
  await api.delete(`/admin/sessions/${sessionId}/dates/${dateId}`)
}

export async function fetchBatches(params?: {
  department_id?: number
  is_active?: boolean
}): Promise<BatchOption[]> {
  const { data } = await api.get<{ data: BatchOption[] }>('/admin/batches', { params })
  return data.data
}

export async function createBatch(payload: BatchPayload): Promise<BatchOption> {
  const { data } = await api.post<{ data: BatchOption }>('/admin/batches', payload)
  return data.data
}

export async function updateBatch(
  id: number,
  payload: Partial<BatchPayload>,
): Promise<BatchOption> {
  const { data } = await api.put<{ data: BatchOption }>(`/admin/batches/${id}`, payload)
  return data.data
}

/** Deletes an empty batch; the API deactivates one that still has students. */
export async function deleteBatch(id: number): Promise<void> {
  await api.delete(`/admin/batches/${id}`)
}

export async function fetchProposals(params: {
  department_id?: string
  status?: string
  search?: string
  page?: number
}): Promise<Paginated<ProposalListItem>> {
  const { data } = await api.get<Paginated<ProposalListItem>>('/admin/proposals', { params })
  return data
}

export async function fetchStudents(params: {
  department_id?: string
  search?: string
  unassigned?: boolean
  page?: number
}): Promise<Paginated<StudentListItem>> {
  const { data } = await api.get<Paginated<StudentListItem>>('/admin/students', { params })
  return data
}

export async function createStudent(payload: StudentPayload): Promise<StudentListItem> {
  const { data } = await api.post<{ data: StudentListItem }>('/admin/students', payload)
  return data.data
}

export async function updateStudent(id: number, payload: StudentPayload): Promise<StudentListItem> {
  const { data } = await api.put<{ data: StudentListItem }>(`/admin/students/${id}`, payload)
  return data.data
}

export async function deleteStudent(id: number): Promise<void> {
  await api.delete(`/admin/students/${id}`)
}

export async function fetchTeachers(params?: {
  department_id?: string
  search?: string
}): Promise<TeacherListItem[]> {
  const { data } = await api.get<{ data: TeacherListItem[] }>('/admin/teachers', { params })
  return data.data
}

export async function createTeacher(payload: TeacherPayload): Promise<TeacherListItem> {
  const { data } = await api.post<{ data: TeacherListItem }>('/admin/teachers', payload)
  return data.data
}

export async function updateTeacher(id: number, payload: TeacherPayload): Promise<TeacherListItem> {
  const { data } = await api.put<{ data: TeacherListItem }>(`/admin/teachers/${id}`, payload)
  return data.data
}

export async function deleteTeacher(id: number): Promise<void> {
  await api.delete(`/admin/teachers/${id}`)
}

export async function assignSupervisor(studentId: number, teacherId: number): Promise<void> {
  await api.post(`/admin/students/${studentId}/assign-supervisor`, {
    teacher_id: teacherId,
  })
}
