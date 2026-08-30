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
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            Activity templates
          </h1>
          <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
            Reusable project timelines. Publishing freezes a version so projects
            that adopt it keep a plan that cannot change underneath them — revise
            by opening a new version.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setIsCreating(true)}
          className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)]"
        >
          New template
        </button>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <select
        value={statusFilter}
        onChange={(e) => setStatusFilter(e.target.value)}
        className="rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2.5"
      >
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="archived">Archived</option>
      </select>

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading templates…</p>
      ) : templates.length === 0 ? (
        <p className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-4 py-10 text-center text-[var(--color-ink-muted)]">
          No templates yet. Create one, or clone a shipped default.
        </p>
      ) : (
        <div className="grid gap-3">
          {templates.map((template) => (
            <article
              key={template.id}
              className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 p-5"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-semibold">{template.name}</h2>
                    <span className="font-[family-name:var(--font-display)] text-sm text-[var(--color-ink-muted)] tabular-nums">
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
                      <span className="inline-flex rounded-md bg-[var(--color-sea-soft)] px-2 py-1 text-xs font-semibold text-[var(--color-sea-deep)]">
                        Sequential
                      </span>
                    )}
                    {template.is_default && (
                      <span className="inline-flex rounded-md bg-[var(--tint-amber)] px-2 py-1 text-xs font-semibold text-[var(--color-amber)]">
                        Shipped default
                      </span>
                    )}
                  </div>

                  <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
                    {template.project_type?.name ?? 'No project type'} ·{' '}
                    {template.items.length} item{template.items.length === 1 ? '' : 's'}
                    {template.span_days !== null && ` · spans ${template.span_days} days`}
                  </p>
                </div>

                <div className="flex shrink-0 flex-wrap gap-3 text-sm">
                  {template.is_editable ? (
                    <button
                      type="button"
                      onClick={() => setEditing(template)}
                      className="text-[var(--color-sea)] hover:underline"
                    >
                      Edit &amp; publish
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setEditing(template)}
                      className="text-[var(--color-sea)] hover:underline"
                    >
                      View
                    </button>
                  )}

                  {template.status === 'published' && (
                    <button
                      type="button"
                      onClick={() => void handleVersion(template)}
                      className="text-[var(--color-sea)] hover:underline"
                    >
                      New version
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => void handleClone(template)}
                    className="text-[var(--color-sea)] hover:underline"
                  >
                    Clone
                  </button>

                  {template.status !== 'archived' && (
                    <button
                      type="button"
                      onClick={() => void handleArchive(template)}
                      className="text-[var(--color-danger)] hover:underline"
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
