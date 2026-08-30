import api from './client'

/**
 * Coordinator configuration: project types and the activity template builder.
 *
 * Published templates are immutable — `update` is refused on them, and a change
 * means opening a new version. The API enforces this; the UI mirrors it by
 * disabling edit controls when `is_editable` is false.
 */

export type ActivityItemTypeValue =
  | 'task'
  | 'approval_gate'
  | 'meeting_milestone'
  | 'recurring_log'

export type TemplateStatusValue = 'draft' | 'published' | 'archived'

export type CadenceValue = 'weekly' | 'fortnightly' | 'monthly'

export interface ProjectTypeItem {
  id: number
  name: string
  slug: string
  description: string | null
  requires_employer: boolean
  is_active: boolean
  is_default: boolean
  sort_order: number
  activity_templates_count: number
}

/** Per-type settings. Which keys are valid depends on the item's type. */
export interface ItemConfig {
  approver_role?: string
  minimum_meetings?: number
  duration_minutes?: number
  cadence?: CadenceValue
  occurrences?: number
}

export interface TemplateItem {
  id?: number
  type: ActivityItemTypeValue
  type_label?: string
  title: string
  description?: string | null
  due_offset_days: number | null
  /** Where a recurring log finishes, accounting for its cadence. */
  end_offset_days?: number | null
  config?: ItemConfig | null
  blocks_progression?: boolean
  sort_order?: number
}

export interface ActivityTemplate {
  id: number
  name: string
  description: string | null
  version: number
  status: TemplateStatusValue
  status_label: string
  published_at: string | null
  is_default: boolean
  is_active: boolean
  is_sequential: boolean
  is_editable: boolean
  is_usable: boolean
  parent_id: number | null
  span_days: number | null
  project_type: { id: number; name: string; slug: string } | null
  items: TemplateItem[]
}

export interface ItemTypeMeta {
  value: ActivityItemTypeValue
  label: string
  description: string
  repeats: boolean
  blocks_by_default: boolean
  config_keys: string[]
}

export interface CadenceMeta {
  value: CadenceValue
  label: string
  interval_days: number
}

export interface TemplatePayload {
  name: string
  description?: string | null
  project_type_id?: number | null
  is_sequential?: boolean
  items?: TemplateItem[]
}

// ---------------------------------------------------------------- types

export async function fetchProjectTypes(): Promise<ProjectTypeItem[]> {
  const { data } = await api.get<{ data: ProjectTypeItem[] }>('/coordinator/project-types')
  return data.data
}

export async function createProjectType(payload: {
  name: string
  description?: string
  requires_employer?: boolean
}): Promise<ProjectTypeItem> {
  const { data } = await api.post<{ data: ProjectTypeItem }>('/coordinator/project-types', payload)
  return data.data
}

export async function deleteProjectType(id: number): Promise<void> {
  await api.delete(`/coordinator/project-types/${id}`)
}

// ------------------------------------------------------------ templates

/** Item types and cadences, so the builder does not hard-code them. */
export async function fetchItemTypes(): Promise<{
  item_types: ItemTypeMeta[]
  cadences: CadenceMeta[]
}> {
  const { data } = await api.get<{
    data: { item_types: ItemTypeMeta[]; cadences: CadenceMeta[] }
  }>('/coordinator/activity-templates/item-types')
  return data.data
}

export async function fetchTemplates(params?: {
  status?: TemplateStatusValue
  project_type_id?: number
}): Promise<ActivityTemplate[]> {
  const { data } = await api.get<{ data: ActivityTemplate[] }>('/coordinator/activity-templates', {
    params,
  })
  return data.data
}

export async function fetchTemplate(id: number): Promise<ActivityTemplate> {
  const { data } = await api.get<{ data: ActivityTemplate }>(
    `/coordinator/activity-templates/${id}`,
  )
  return data.data
}

export async function createTemplate(payload: TemplatePayload): Promise<ActivityTemplate> {
  const { data } = await api.post<{ data: ActivityTemplate }>(
    '/coordinator/activity-templates',
    payload,
  )
  return data.data
}

/** Drafts only — the API refuses this on a published template. */
export async function updateTemplate(
  id: number,
  payload: Partial<TemplatePayload>,
): Promise<ActivityTemplate> {
  const { data } = await api.put<{ data: ActivityTemplate }>(
    `/coordinator/activity-templates/${id}`,
    payload,
  )
  return data.data
}

/** Validates coherence, then freezes the draft and makes it usable. */
export async function publishTemplate(id: number): Promise<ActivityTemplate> {
  const { data } = await api.post<{ data: ActivityTemplate }>(
    `/coordinator/activity-templates/${id}/publish`,
  )
  return data.data
}

/** Opens the next draft version; the published one is left serving. */
export async function createTemplateVersion(id: number): Promise<ActivityTemplate> {
  const { data } = await api.post<{ data: ActivityTemplate }>(
    `/coordinator/activity-templates/${id}/versions`,
  )
  return data.data
}

/** Copies into a new draft at v1 under a new name — a separate version line. */
export async function cloneTemplate(id: number, name: string): Promise<ActivityTemplate> {
  const { data } = await api.post<{ data: ActivityTemplate }>(
    `/coordinator/activity-templates/${id}/clone`,
    { name },
  )
  return data.data
}

/** Archives rather than deletes. */
export async function archiveTemplate(id: number): Promise<ActivityTemplate> {
  const { data } = await api.delete<{ data: ActivityTemplate }>(
    `/coordinator/activity-templates/${id}`,
  )
  return data.data
}
