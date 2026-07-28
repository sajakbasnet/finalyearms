import api from './client'

export interface TeacherStudentRow {
  assignment_id: number
  assigned_at: string | null
  student: {
    id: number
    name: string
    email: string
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
  } | null
}

export interface TeacherProposalSummary {
  id: number
  title: string
  version_number: number
  status: string
  status_label: string
  submitted_at: string | null
  project_id: number
  student: {
    name: string
    registration_number: string
    department: string | null
  }
  has_pdf: boolean
}

export interface TeacherComment {
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

export interface TeacherProposalDetail extends TeacherProposalSummary {
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
  pdf_path: string | null
  comments: TeacherComment[]
}

export interface ProgressReportRow {
  id: number
  title: string
  description: string | null
  percentage_completed: number
  demo_link: string | null
  git_repository: string | null
  status: string
  submitted_at: string | null
  project: { id: number; title: string }
  student: { name: string }
  comments: Array<{
    id: number
    comment: string
    action: string | null
    user: string
    created_at: string | null
  }>
}

export interface MilestoneRow {
  id: number
  title: string
  description: string | null
  due_date: string | null
  status: string
  status_label: string
  remarks: string | null
}

export interface ProjectWorkspace {
  id: number
  title: string
  description: string | null
  status: string
  status_label: string
  student: {
    id: number
    name: string
    registration_number: string
    department: string | null
  }
  milestones: MilestoneRow[]
  progress_reports: ProgressReportRow[]
  evaluation: {
    id: number
    innovation: number
    implementation: number
    documentation: number
    presentation: number
    testing: number
    overall_score: string | number
    remarks: string | null
  } | null
  files: Array<{ key: string; label: string; path: string; type: string }>
  final_submission: {
    github_repository: string | null
    submitted_at: string | null
  } | null
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

export async function fetchTeacherStudents(): Promise<TeacherStudentRow[]> {
  const { data } = await api.get<{ data: TeacherStudentRow[] }>('/teacher/students')
  return data.data
}

export async function fetchTeacherProposals(status?: string): Promise<TeacherProposalSummary[]> {
  const { data } = await api.get<{ data: TeacherProposalSummary[] }>('/teacher/proposals', {
    params: status ? { status } : undefined,
  })
  return data.data
}

export async function fetchTeacherProposal(id: number): Promise<TeacherProposalDetail> {
  const { data } = await api.get<{ data: TeacherProposalDetail }>(`/teacher/proposals/${id}`)
  return data.data
}

export async function reviewProposal(
  id: number,
  payload: { action: 'approve' | 'reject' | 'request_revision'; comment?: string; section?: string },
): Promise<TeacherProposalDetail> {
  const { data } = await api.post<{ data: TeacherProposalDetail }>(`/teacher/proposals/${id}/review`, payload)
  return data.data
}

export async function commentOnProposal(
  id: number,
  payload: { comment: string; section?: string },
): Promise<TeacherComment> {
  const { data } = await api.post<{ data: TeacherComment }>(`/teacher/proposals/${id}/comments`, payload)
  return data.data
}

export async function replyToComment(commentId: number, reply: string): Promise<void> {
  await api.post(`/teacher/comments/${commentId}/replies`, { reply })
}

export async function fetchProgressReports(): Promise<ProgressReportRow[]> {
  const { data } = await api.get<{ data: ProgressReportRow[] }>('/teacher/progress-reports')
  return data.data
}

export async function reviewProgressReport(
  id: number,
  payload: { action: 'approve' | 'reject'; comment?: string },
): Promise<ProgressReportRow> {
  const { data } = await api.post<{ data: ProgressReportRow }>(`/teacher/progress-reports/${id}/review`, payload)
  return data.data
}

export async function fetchProjectWorkspace(projectId: number): Promise<ProjectWorkspace> {
  const { data } = await api.get<{ data: ProjectWorkspace }>(`/teacher/projects/${projectId}`)
  return data.data
}

export async function updateMilestone(
  id: number,
  payload: { status: string; remarks?: string },
): Promise<MilestoneRow> {
  const { data } = await api.patch<{ data: MilestoneRow }>(`/teacher/milestones/${id}`, payload)
  return data.data
}

export async function saveEvaluation(
  projectId: number,
  payload: {
    innovation: number
    implementation: number
    documentation: number
    presentation: number
    testing: number
    remarks?: string
  },
): Promise<ProjectWorkspace['evaluation']> {
  const { data } = await api.post<{ data: ProjectWorkspace['evaluation'] }>(
    `/teacher/projects/${projectId}/evaluation`,
    payload,
  )
  return data.data
}

export async function downloadProjectFile(projectId: number, path: string, fileName: string): Promise<void> {
  const response = await api.get(`/teacher/projects/${projectId}/download`, {
    params: { path },
    responseType: 'blob',
  })

  const url = window.URL.createObjectURL(new Blob([response.data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', fileName)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
