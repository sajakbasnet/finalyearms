/**
 * Roles assignable to a user inside a tenant.
 *
 * Two roles from the access model are deliberately absent:
 * - `platform_admin` is a control-plane identity and never appears in a tenant.
 * - `team_lead` is contextual, derived from group membership rather than
 *   `users.role_id`, so a team lead is still a `student` here.
 */
export type RoleSlug =
  | 'institution_admin'
  | 'coordinator'
  | 'supervisor'
  | 'student'
  | 'employer'

export interface Role {
  id: number
  name: string
  slug: RoleSlug
}

export interface Department {
  id: number
  name: string
  code: string
}

export interface TeacherProfile {
  id: number
  employee_id: string
  designation: string | null
  department: Department | null
}

export interface StudentProfile {
  id: number
  registration_number: string
  roll_number: string | null
  batch: string | null
  department: Department | null
  academic_session: { id: number; name: string } | null
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  role: Role | null
  teacher?: TeacherProfile | null
  student?: StudentProfile | null
}

export interface LoginResponse {
  message: string
  token: string
  token_type: string
  user: User
}

export interface DashboardResponse {
  data: Record<string, unknown>
}
