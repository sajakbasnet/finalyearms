import api from './client'

export interface StudentOverview {
  supervisor: {
    name: string
    email: string
    phone: string | null
    designation: string | null
    department: string | null
    employee_id: string
  } | null
  student: {
    registration_number: string
    batch: string | null
    department: string | null
    session: string | null
  }
  project: {
    id: number
    title: string
    status: string
    status_label: string
    proposal_status: string | null
    proposal_status_label: string | null
  } | null
}

export interface ProposalComment {
  id: number
  section: string | null
  comment: string
  action: string | null
  user: { id: number; name: string }
  created_at: string | null
  replies: Array<{
    id: number
    reply: string
    user: { id: number; name: string }
    created_at: string | null
  }>
}

export interface ProposalData {
  id: number
  project_id: number
  version_number: number
  title: string
  abstract: string | null
  background: string | null
  problem_statement: string | null
  objectives: string | null
  scope: string | null
  methodology: string | null
  literature_review: string | null
  timeline: string | null
  expected_outcome: string | null
  technologies: string | null
  references: string | null
  has_pdf: boolean
  status: string
  status_label: string
  submitted_at: string | null
  can_edit: boolean
  comments: ProposalComment[]
}

export interface StudentWorkspace {
  project: {
    id: number
    title: string
    status: string
    status_label: string
    supervisor: { name: string; email: string } | null
  }
  latest_proposal: ProposalData | null
  versions: Array<{
    id: number
    version_number: number
    status: string
    status_label: string
    submitted_at: string | null
    has_pdf: boolean
  }>
  progress_reports: Array<{
    id: number
    title: string
    description: string | null
    percentage_completed: number
    demo_link: string | null
    git_repository: string | null
    status: string
    submitted_at: string | null
    comments: Array<{ id: number; comment: string; action: string | null; user: string }>
  }>
  final_submission: {
    has_thesis: boolean
    has_presentation: boolean
    github_repository: string | null
    submitted_at: string | null
  } | null
}

export interface TimelineItem {
  id: number
  title: string
  description: string | null
  due_date: string | null
  status: string
  status_label: string
  remarks: string | null
}

export interface NotificationItem {
  id: string
  data: {
    title?: string
    message?: string
    type?: string
  }
  read_at: string | null
  created_at: string | null
}

function extractError(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as {
      response?: { data?: { message?: string; errors?: Record<string, string[]> } }
    }).response
    const first = response?.data?.errors ? Object.values(response.data.errors)[0]?.[0] : undefined
    return first ?? response?.data?.message ?? fallback
  }
  return fallback
}

export { extractError }

export async function fetchOverview(): Promise<StudentOverview> {
  const { data } = await api.get<{ data: StudentOverview }>('/student/overview')
  return data.data
}

export async function fetchWorkspace(): Promise<StudentWorkspace | null> {
  const { data } = await api.get<{ data: StudentWorkspace | null }>('/student/workspace')
  return data.data
}

export async function saveProposal(form: FormData): Promise<ProposalData> {
  const { data } = await api.post<{ data: ProposalData }>('/student/proposals', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function submitProposal(proposalId?: number): Promise<ProposalData> {
  const { data } = await api.post<{ data: ProposalData }>('/student/proposals/submit', {
    proposal_id: proposalId,
  })
  return data.data
}

export async function replyToComment(commentId: number, reply: string): Promise<void> {
  await api.post(`/student/comments/${commentId}/replies`, { reply })
}

export async function submitProgress(payload: {
  title: string
  description?: string
  percentage_completed: number
  demo_link?: string
  git_repository?: string
}): Promise<void> {
  await api.post('/student/progress-reports', payload)
}

export async function uploadFinal(form: FormData): Promise<void> {
  await api.post('/student/final-submission', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export async function fetchTimeline(): Promise<TimelineItem[]> {
  const { data } = await api.get<{ data: TimelineItem[] }>('/student/timeline')
  return data.data
}

export async function fetchNotifications(): Promise<NotificationItem[]> {
  const { data } = await api.get<{ data: NotificationItem[] }>('/student/notifications')
  return data.data
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.post(`/student/notifications/${id}/read`)
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/student/notifications/read-all')
}
