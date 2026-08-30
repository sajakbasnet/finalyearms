import api from './client'

/**
 * Team formation and supervisor matching, from the student's side.
 *
 * A project exists before it has a supervisor: the team forms first, then asks
 * someone to take it on.
 */

export interface TeamMember {
  student_id: number
  name: string | null
  registration_number: string | null
  is_leader: boolean
}

export interface PendingInvite {
  id: number
  name: string | null
  registration_number: string | null
  expires_at: string | null
}

export interface Team {
  id: number
  name: string
  is_individual: boolean
  member_count: number
  min_members: number
  max_members: number
  is_lead: boolean
  supervisor: { id: number; name: string | null } | null
  members: TeamMember[]
  pending_invitations: PendingInvite[]
}

export interface Invitation {
  id: number
  status: 'pending' | 'accepted' | 'declined' | 'cancelled' | 'expired'
  status_label: string
  is_actionable: boolean
  has_expired: boolean
  message: string | null
  expires_at: string | null
  invited_by: string | null
  team: { id: number | null; name: string | null; member_count: number }
}

export interface SupervisorListing {
  id: number
  name: string | null
  email: string | null
  designation: string | null
  department: string | null
  max_projects: number
  active_projects: number
  remaining_capacity: number
  supervisee_count: number
  is_available: boolean
}

export interface SupervisorRequestItem {
  id: number
  status: 'pending' | 'accepted' | 'declined' | 'withdrawn'
  status_label: string
  rationale: string
  response_note: string | null
  responded_at: string | null
  created_at: string | null
  supervisor: { id: number | null; name: string | null; designation: string | null }
  requested_by: string | null
}

// ------------------------------------------------------------- project

export async function createProject(payload: {
  title: string
  is_team: boolean
  team_name?: string
}): Promise<Team | null> {
  const { data } = await api.post<{ data: Team | null }>('/student/projects', payload)
  return data.data
}

// ---------------------------------------------------------------- team

/** Null when the student has no team — an individual project has none. */
export async function fetchTeam(): Promise<Team | null> {
  const { data } = await api.get<{ data: Team | null }>('/student/team')
  return data.data
}

export async function inviteMember(payload: {
  student_id: number
  message?: string
}): Promise<void> {
  await api.post('/student/team/invitations', payload)
}

/** Removing yourself is leaving; the API decides which applies. */
export async function removeMember(studentId: number): Promise<Team | null> {
  const { data } = await api.delete<{ data: Team | null }>(`/student/team/members/${studentId}`)
  return data.data
}

export async function transferLead(studentId: number): Promise<Team | null> {
  const { data } = await api.post<{ data: Team | null }>(`/student/team/lead/${studentId}`)
  return data.data
}

// --------------------------------------------------------- invitations

export async function fetchInvitations(): Promise<Invitation[]> {
  const { data } = await api.get<{ data: Invitation[] }>('/student/invitations')
  return data.data
}

export async function acceptInvitation(id: number): Promise<void> {
  await api.post(`/student/invitations/${id}/accept`)
}

export async function declineInvitation(id: number): Promise<void> {
  await api.post(`/student/invitations/${id}/decline`)
}

// ---------------------------------------------------------- supervisors

export async function fetchSupervisors(params?: {
  search?: string
  designation?: string
  available_only?: boolean
}): Promise<SupervisorListing[]> {
  const { data } = await api.get<{ data: SupervisorListing[] }>('/student/supervisors', { params })
  return data.data
}

export async function fetchSupervisorRequests(): Promise<SupervisorRequestItem[]> {
  const { data } = await api.get<{ data: SupervisorRequestItem[] }>('/student/supervisor-requests')
  return data.data
}

export async function requestSupervisor(payload: {
  teacher_id: number
  rationale: string
}): Promise<SupervisorRequestItem> {
  const { data } = await api.post<{ data: SupervisorRequestItem }>(
    '/student/supervisor-requests',
    payload,
  )
  return data.data
}

export async function withdrawSupervisorRequest(id: number): Promise<void> {
  await api.post(`/student/supervisor-requests/${id}/withdraw`)
}
