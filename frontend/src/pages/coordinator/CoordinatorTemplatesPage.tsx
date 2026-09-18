import { useEffect, useState } from 'react'
import { StatusChip } from '../../components/StatusChip'
import { TemplateBuilder } from './TemplateBuilder'
import {
  archiveTemplate,
  cloneTemplate,
  createTemplateVersion,
  fetchItemTypes,
  fetchProjectTypes,
  fetchTemplates,
  type ActivityTemplate,
  type CadenceMeta,
  type ItemTypeMeta,
  type ProjectTypeItem,
} from '../../api/coordinator'
import { extractError } from '../../api/admin'

/**
 * The Coordinator's template library.
 *
 * Published templates are immutable, so the actions offered depend on status:
 * a draft can be edited and published; a published one can only be versioned or
 * cloned. The API enforces this — the UI just avoids offering what will fail.
 */
export function CoordinatorTemplatesPage() {
  const [templates, setTemplates] = useState<ActivityTemplate[]>([])
  const [projectTypes, setProjectTypes] = useState<ProjectTypeItem[]>([])
  const [itemTypes, setItemTypes] = useState<ItemTypeMeta[]>([])
  const [cadences, setCadences] = useState<CadenceMeta[]>([])
  const [statusFilter, setStatusFilter] = useState('')
  const [editing, setEditing] = useState<ActivityTemplate | null>(null)
  const [isCreating, setIsCreating] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function loadTemplates() {
    setIsLoading(true)
    try {
      setTemplates(
        await fetchTemplates(
          statusFilter ? { status: statusFilter as ActivityTemplate['status'] } : undefined,
        ),
      )
    } catch (err) {
      setError(extractError(err, 'Could not load templates.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void Promise.all([fetchProjectTypes(), fetchItemTypes()])
      .then(([types, meta]) => {
        setProjectTypes(types)
        setItemTypes(meta.item_types)
        setCadences(meta.cadences)
      })
      .catch(() => setError('Could not load the builder configuration.'))
  }, [])

  useEffect(() => {
    void loadTemplates()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter])

  function announce(message: string) {
    setSuccess(message)
    setError(null)
  }

  async function handleVersion(template: ActivityTemplate) {
    try {
      const draft = await createTemplateVersion(template.id)
      announce(`Draft version ${draft.version} opened. Version ${template.version} is untouched.`)
      await loadTemplates()
      setEditing(draft)
    } catch (err) {
      setError(extractError(err, 'Could not create a new version.'))
    }
  }

  async function handleClone(template: ActivityTemplate) {
    const name = window.prompt('Name for the copy', `${template.name} (copy)`)

    if (!name) {
      return
    }

    try {
      const clone = await cloneTemplate(template.id, name)
      announce('Cloned as a new draft.')
      await loadTemplates()
      setEditing(clone)
    } catch (err) {
      setError(extractError(err, 'Could not clone the template.'))
    }
  }

  async function handleArchive(template: ActivityTemplate) {
    if (!window.confirm(`Archive ${template.name} v${template.version}?`)) {
      return
    }

    try {
      await archiveTemplate(template.id)
      announce('Template archived.')
      await loadTemplates()
    } catch (err) {
      setError(extractError(err, 'Could not archive the template.'))
    }
  }

  if (isCreating || editing) {
    return (
      <TemplateBuilder
        template={editing}
        projectTypes={projectTypes}
        itemTypes={itemTypes}
        cadences={cadences}
        onClose={() => {
          setEditing(null)
          setIsCreating(false)
          void loadTemplates()
        }}
        onSaved={announce}
      />
    )
  }  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">
            Activity Templates
          </h1>
          <p className="mt-1 text-slate-500">
            Reusable project timelines and milestones for student project tracks.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setIsCreating(true)}
          className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 active:bg-blue-800"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
          </svg>
          New Template
        </button>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}
      {success && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
          {success}
        </div>
      )}

      <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label htmlFor="template-status-filter" className="text-sm font-medium text-slate-600">
          Filter by Status:
        </label>
        <select
          id="template-status-filter"
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
        >
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-400 shadow-sm">
          Loading templates…
        </div>
      ) : templates.length === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-500 shadow-sm">
          No templates found. Create one, or clone a shipped default.
        </div>
      ) : (
        <div className="grid gap-4">
          {templates.map((template) => (
            <article
              key={template.id}
              className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md"
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2.5">
                    <h2 className="text-xl font-bold text-slate-900">{template.name}</h2>
                    <span className="font-mono text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                      v{template.version}
                    </span>
                    <StatusChip
                      status={
                        template.status === 'published'
                          ? 'approved'
                          : template.status === 'archived'
                            ? 'cancelled'
                            : 'in_progress'
                      }
                      label={template.status_label}
                    />
                    {template.is_sequential && (
                      <span className="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 border border-blue-200">
                        Sequential Milestones
                      </span>
                    )}
                    {template.is_default && (
                      <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                        Platform Default
                      </span>
                    )}
                  </div>

                  <p className="mt-2 text-sm text-slate-500">
                    <span className="font-semibold text-slate-700">{template.project_type?.name ?? 'General'}</span> ·{' '}
                    {template.items.length} item{template.items.length === 1 ? '' : 's'}
                    {template.span_days !== null && ` · spans ${template.span_days} days`}
                  </p>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2">
                  {template.is_editable ? (
                    <button
                      type="button"
                      onClick={() => setEditing(template)}
                      className="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition shadow-sm"
                    >
                      Edit &amp; Publish
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setEditing(template)}
                      className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                    >
                      View Template
                    </button>
                  )}

                  {template.status === 'published' && (
                    <button
                      type="button"
                      onClick={() => void handleVersion(template)}
                      className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition"
                    >
                      New Version
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => void handleClone(template)}
                    className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                  >
                    Clone
                  </button>

                  {template.status !== 'archived' && (
                    <button
                      type="button"
                      onClick={() => void handleArchive(template)}
                      className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                    >
                      Archive
                    </button>
                  )}
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  )
}
