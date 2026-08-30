import { useEffect, useState, type FormEvent } from 'react'
import { extractError } from '../../api/admin'
import {
  createProjectType,
  deleteProjectType,
  fetchProjectTypes,
  type ProjectTypeItem,
} from '../../api/coordinator'

const emptyForm = { name: '', description: '', requires_employer: false }

export function CoordinatorProjectTypesPage() {
  const [types, setTypes] = useState<ProjectTypeItem[]>([])
  const [form, setForm] = useState(emptyForm)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function load() {
    setIsLoading(true)
    try {
      setTypes(await fetchProjectTypes())
    } catch (err) {
      setError(extractError(err, 'Could not load project types.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)

    try {
      await createProjectType({
        name: form.name,
        description: form.description || undefined,
        requires_employer: form.requires_employer,
      })
      setSuccess('Project type created.')
      setForm(emptyForm)
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not create the project type.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete(type: ProjectTypeItem) {
    // A seeded default returns at the next provisioning run, so the API
    // deactivates it instead of deleting. Say which will happen.
    const message = type.is_default
      ? `${type.name} ships with the platform, so it will be deactivated rather than deleted. Continue?`
      : `Delete ${type.name}?`

    if (!window.confirm(message)) {
      return
    }

    try {
      await deleteProjectType(type.id)
      setSuccess(type.is_default ? 'Project type deactivated.' : 'Project type deleted.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not remove the project type.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Project types
        </h1>
        <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
          The kinds of work a project can be. Activity templates hang off these,
          so a type is the first thing to set up.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
        <form
          onSubmit={handleSubmit}
          className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-5"
        >
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            Add a type
          </h2>

          <div className="mt-4 grid gap-3">
            <input
              required
              placeholder="Name (e.g. Design Studio)"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <textarea
              rows={3}
              placeholder="What this kind of project involves"
              value={form.description}
              onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <label className="flex items-start gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.requires_employer}
                onChange={(e) => setForm((p) => ({ ...p, requires_employer: e.target.checked }))}
                className="mt-1"
              />
              <span>
                <span className="font-medium">Requires an employer</span>
                <span className="block text-[var(--color-ink-muted)]">
                  For placements evaluated jointly with a host organisation.
                </span>
              </span>
            </label>
          </div>

          <button
            type="submit"
            disabled={isSaving}
            className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-70"
          >
            {isSaving ? 'Saving…' : 'Create type'}
          </button>
        </form>

        <div className="overflow-x-auto rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70">
          {isLoading ? (
            <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading…</p>
          ) : types.length === 0 ? (
            <p className="px-4 py-8 text-center text-[var(--color-ink-muted)]">
              No project types yet.
            </p>
          ) : (
            <table className="w-full min-w-[620px] text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Type</th>
                  <th className="px-4 py-3 font-medium">Templates</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium" />
                </tr>
              </thead>
              <tbody>
                {types.map((type) => (
                  <tr key={type.id} className="border-t border-[var(--color-paper-deep)] align-top">
                    <td className="px-4 py-3">
                      <span className="font-medium">{type.name}</span>
                      {type.is_default && (
                        <span className="ml-2 inline-flex rounded-md bg-[var(--tint-amber)] px-2 py-0.5 text-xs font-semibold text-[var(--color-amber)]">
                          Shipped
                        </span>
                      )}
                      {type.requires_employer && (
                        <span className="ml-2 inline-flex rounded-md bg-[var(--color-sea-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--color-sea-deep)]">
                          Employer
                        </span>
                      )}
                      <span className="mt-1 block text-[var(--color-ink-muted)]">
                        {type.description ?? '—'}
                      </span>
                    </td>
                    <td className="px-4 py-3 tabular-nums">{type.activity_templates_count}</td>
                    <td className="px-4 py-3">
                      <span
                        className={
                          type.is_active
                            ? 'inline-flex rounded-md bg-[var(--tint-success)] px-2 py-1 text-xs font-semibold text-[var(--color-success)]'
                            : 'inline-flex rounded-md bg-[var(--color-paper-deep)] px-2 py-1 text-xs font-semibold text-[var(--color-ink-muted)]'
                        }
                      >
                        {type.is_active ? 'Active' : 'Retired'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      {type.is_active && (
                        <button
                          type="button"
                          onClick={() => void handleDelete(type)}
                          className="text-[var(--color-danger)] hover:underline"
                        >
                          {type.is_default ? 'Retire' : 'Delete'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  )
}
