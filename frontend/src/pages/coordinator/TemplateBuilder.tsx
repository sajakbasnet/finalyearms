import { useState, type FormEvent } from 'react'
import {
  createTemplate,
  publishTemplate,
  updateTemplate,
  type ActivityItemTypeValue,
  type ActivityTemplate,
  type CadenceMeta,
  type ItemTypeMeta,
  type ProjectTypeItem,
  type TemplateItem,
} from '../../api/coordinator'
import { extractError } from '../../api/admin'

interface Props {
  /** Null when creating; a template when editing or viewing one. */
  template: ActivityTemplate | null
  projectTypes: ProjectTypeItem[]
  itemTypes: ItemTypeMeta[]
  cadences: CadenceMeta[]
  onClose: () => void
  onSaved: (message: string) => void
}

function blankItem(type: ActivityItemTypeValue, meta?: ItemTypeMeta): TemplateItem {
  return {
    type,
    title: '',
    due_offset_days: null,
    config: {},
    blocks_progression: meta?.blocks_by_default ?? false,
  }
}

/**
 * Builds and publishes an activity template.
 *
 * A published template is immutable, so when one is opened the whole form is
 * read-only and the only route to a change is opening a new version — done from
 * the library, not here.
 */
export function TemplateBuilder({
  template,
  projectTypes,
  itemTypes,
  cadences,
  onClose,
  onSaved,
}: Props) {
  const readOnly = template !== null && !template.is_editable

  const [name, setName] = useState(template?.name ?? '')
  const [description, setDescription] = useState(template?.description ?? '')
  const [projectTypeId, setProjectTypeId] = useState(
    template?.project_type ? String(template.project_type.id) : '',
  )
  const [isSequential, setIsSequential] = useState(template?.is_sequential ?? false)
  const [items, setItems] = useState<TemplateItem[]>(template?.items ?? [])
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})

  function metaFor(type: ActivityItemTypeValue): ItemTypeMeta | undefined {
    return itemTypes.find((meta) => meta.value === type)
  }

  function patchItem(index: number, patch: Partial<TemplateItem>) {
    setItems((current) =>
      current.map((item, i) => (i === index ? { ...item, ...patch } : item)),
    )
  }

  function patchConfig(index: number, patch: Record<string, unknown>) {
    setItems((current) =>
      current.map((item, i) =>
        i === index ? { ...item, config: { ...(item.config ?? {}), ...patch } } : item,
      ),
    )
  }

  /**
   * Changing type discards the old config: an approval gate's approver role is
   * meaningless on a recurring log, and the API rejects unknown keys.
   */
  function changeType(index: number, type: ActivityItemTypeValue) {
    const meta = metaFor(type)
    patchItem(index, { type, config: {}, blocks_progression: meta?.blocks_by_default ?? false })
  }

  function move(index: number, direction: -1 | 1) {
    const target = index + direction

    if (target < 0 || target >= items.length) {
      return
    }

    setItems((current) => {
      const next = [...current]
      ;[next[index], next[target]] = [next[target], next[index]]
      return next
    })
  }

  function payload() {
    return {
      name,
      description: description || null,
      project_type_id: projectTypeId ? Number(projectTypeId) : null,
      is_sequential: isSequential,
      items: items.map((item) => ({
        ...item,
        due_offset_days:
          item.due_offset_days === null || item.due_offset_days === undefined
            ? null
            : Number(item.due_offset_days),
      })),
    }
  }

  function handleFailure(err: unknown, fallback: string) {
    setError(extractError(err, fallback))

    // Surface per-item validation next to the field that caused it.
    const errors = (err as { response?: { data?: { errors?: Record<string, string[]> } } })
      ?.response?.data?.errors
    setFieldErrors(errors ?? {})
  }

  async function save(): Promise<ActivityTemplate | null> {
    setIsSaving(true)
    setError(null)
    setFieldErrors({})

    try {
      const saved = template
        ? await updateTemplate(template.id, payload())
        : await createTemplate(payload())
      return saved
    } catch (err) {
      handleFailure(err, 'Could not save the template.')
      return null
    } finally {
      setIsSaving(false)
    }
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault()
    const saved = await save()

    if (saved) {
      onSaved('Draft saved.')
      onClose()
    }
  }

  /** Saves first, so publish validates exactly what is on screen. */
  async function handlePublish() {
    const saved = await save()

    if (!saved) {
      return
    }

    setIsSaving(true)

    try {
      const published = await publishTemplate(saved.id)
      onSaved(`Version ${published.version} published and ready to use.`)
      onClose()
    } catch (err) {
      handleFailure(err, 'Could not publish. Fix the problems below and try again.')
    } finally {
      setIsSaving(false)
    }
  }

  const itemErrorKeys = Object.keys(fieldErrors).filter((key) => key.startsWith('items.'))

  return (
    <form onSubmit={handleSave} className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            {template ? `${template.name} · v${template.version}` : 'New template'}
          </h1>
          <p className="mt-2 text-[var(--color-ink-muted)]">
            {readOnly
              ? 'Published templates are read-only. Open a new version from the library to make changes.'
              : 'Build the timeline, then publish to make it usable.'}
          </p>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg border border-[var(--color-paper-deep)] px-4 py-2.5"
        >
          Back to library
        </button>
      </header>

      {error && (
        <div className="rounded-lg bg-[var(--tint-danger)] px-4 py-3 text-sm text-[var(--color-danger)]">
          <p className="font-semibold">{error}</p>
          {Object.entries(fieldErrors)
            .filter(([key]) => !key.startsWith('items.'))
            .map(([key, messages]) => (
              <p key={key} className="mt-1">
                {messages[0]}
              </p>
            ))}
          {itemErrorKeys.map((key) => (
            <p key={key} className="mt-1">
              {fieldErrors[key][0]}
            </p>
          ))}
        </div>
      )}

      <fieldset
        disabled={readOnly}
        className="grid gap-3 rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-5 disabled:opacity-80 md:grid-cols-2"
      >
        <label className="grid gap-1 text-sm">
          <span className="font-medium">Name</span>
          <input
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Development — standard timeline"
            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
          />
        </label>

        <label className="grid gap-1 text-sm">
          <span className="font-medium">Project type</span>
          <select
            value={projectTypeId}
            onChange={(e) => setProjectTypeId(e.target.value)}
            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
          >
            <option value="">General purpose</option>
            {projectTypes.map((type) => (
              <option key={type.id} value={type.id}>
                {type.name}
              </option>
            ))}
          </select>
        </label>

        <label className="grid gap-1 text-sm md:col-span-2">
          <span className="font-medium">Description</span>
          <textarea
            rows={2}
            value={description ?? ''}
            onChange={(e) => setDescription(e.target.value)}
            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
          />
        </label>

        <label className="flex items-start gap-2 text-sm md:col-span-2">
          <input
            type="checkbox"
            checked={isSequential}
            onChange={(e) => setIsSequential(e.target.checked)}
            className="mt-1"
          />
          <span>
            <span className="font-medium">Sequential</span>
            <span className="block text-[var(--color-ink-muted)]">
              Items must be completed in order. Publishing checks that offsets do
              not run backwards and that a blocking item is never last.
            </span>
          </span>
        </label>
      </fieldset>

      <section className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="font-semibold">
            Items{' '}
            <span className="font-normal text-[var(--color-ink-muted)]">
              ({items.length})
            </span>
          </h2>

          {!readOnly && (
            <div className="flex flex-wrap gap-2">
              {itemTypes.map((meta) => (
                <button
                  key={meta.value}
                  type="button"
                  title={meta.description}
                  onClick={() => setItems((c) => [...c, blankItem(meta.value, meta)])}
                  className="rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2 text-sm hover:border-[var(--color-sea)]"
                >
                  + {meta.label}
                </button>
              ))}
            </div>
          )}
        </div>

        {items.length === 0 ? (
          <p className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-4 py-8 text-center text-[var(--color-ink-muted)]">
            No items yet. A template needs at least one before it can be published.
          </p>
        ) : (
          <ol className="grid gap-3">
            {items.map((item, index) => {
              const meta = metaFor(item.type)
              const keys = meta?.config_keys ?? []

              return (
                <li
                  key={index}
                  className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-4"
                >
                  <fieldset disabled={readOnly} className="grid gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-[family-name:var(--font-display)] text-sm text-[var(--color-ink-muted)] tabular-nums">
                        {String(index + 1).padStart(2, '0')}
                      </span>

                      <select
                        value={item.type}
                        onChange={(e) =>
                          changeType(index, e.target.value as ActivityItemTypeValue)
                        }
                        className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                      >
                        {itemTypes.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </select>

                      <input
                        required
                        value={item.title}
                        onChange={(e) => patchItem(index, { title: e.target.value })}
                        placeholder="Title"
                        className="min-w-0 flex-1 rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                      />

                      {!readOnly && (
                        <span className="flex gap-1">
                          <button
                            type="button"
                            onClick={() => move(index, -1)}
                            aria-label="Move up"
                            className="rounded-md border border-[var(--color-paper-deep)] px-2 py-1 text-sm"
                          >
                            ↑
                          </button>
                          <button
                            type="button"
                            onClick={() => move(index, 1)}
                            aria-label="Move down"
                            className="rounded-md border border-[var(--color-paper-deep)] px-2 py-1 text-sm"
                          >
                            ↓
                          </button>
                          <button
                            type="button"
                            onClick={() => setItems((c) => c.filter((_, i) => i !== index))}
                            aria-label="Remove item"
                            className="rounded-md border border-[var(--color-paper-deep)] px-2 py-1 text-sm text-[var(--color-danger)]"
                          >
                            ✕
                          </button>
                        </span>
                      )}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                      <label className="grid gap-1 text-xs">
                        <span className="text-[var(--color-ink-muted)]">
                          Due (days from start)
                        </span>
                        <input
                          type="number"
                          min={0}
                          max={1095}
                          value={item.due_offset_days ?? ''}
                          onChange={(e) =>
                            patchItem(index, {
                              due_offset_days: e.target.value === '' ? null : Number(e.target.value),
                            })
                          }
                          className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                        />
                      </label>

                      {keys.includes('approver_role') && (
                        <label className="grid gap-1 text-xs">
                          <span className="text-[var(--color-ink-muted)]">Approved by</span>
                          <select
                            value={item.config?.approver_role ?? ''}
                            onChange={(e) => patchConfig(index, { approver_role: e.target.value })}
                            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                          >
                            <option value="">Choose…</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="institution_admin">Institution Admin</option>
                          </select>
                        </label>
                      )}

                      {keys.includes('cadence') && (
                        <label className="grid gap-1 text-xs">
                          <span className="text-[var(--color-ink-muted)]">Repeats</span>
                          <select
                            value={item.config?.cadence ?? ''}
                            onChange={(e) =>
                              patchConfig(index, {
                                cadence: e.target.value as CadenceMeta['value'],
                              })
                            }
                            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                          >
                            <option value="">Choose…</option>
                            {cadences.map((cadence) => (
                              <option key={cadence.value} value={cadence.value}>
                                {cadence.label}
                              </option>
                            ))}
                          </select>
                        </label>
                      )}

                      {keys.includes('occurrences') && (
                        <label className="grid gap-1 text-xs">
                          <span className="text-[var(--color-ink-muted)]">Occurrences</span>
                          <input
                            type="number"
                            min={1}
                            max={60}
                            value={item.config?.occurrences ?? ''}
                            onChange={(e) =>
                              patchConfig(index, { occurrences: Number(e.target.value) })
                            }
                            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                          />
                        </label>
                      )}

                      {keys.includes('minimum_meetings') && (
                        <label className="grid gap-1 text-xs">
                          <span className="text-[var(--color-ink-muted)]">Minimum meetings</span>
                          <input
                            type="number"
                            min={1}
                            max={20}
                            value={item.config?.minimum_meetings ?? ''}
                            onChange={(e) =>
                              patchConfig(index, { minimum_meetings: Number(e.target.value) })
                            }
                            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                          />
                        </label>
                      )}

                      {keys.includes('duration_minutes') && (
                        <label className="grid gap-1 text-xs">
                          <span className="text-[var(--color-ink-muted)]">Duration (minutes)</span>
                          <input
                            type="number"
                            min={5}
                            max={480}
                            value={item.config?.duration_minutes ?? ''}
                            onChange={(e) =>
                              patchConfig(index, { duration_minutes: Number(e.target.value) })
                            }
                            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                          />
                        </label>
                      )}
                    </div>

                    <label className="flex items-center gap-2 text-xs">
                      <input
                        type="checkbox"
                        checked={item.blocks_progression ?? false}
                        onChange={(e) =>
                          patchItem(index, { blocks_progression: e.target.checked })
                        }
                      />
                      <span className="text-[var(--color-ink-muted)]">
                        Blocks progression until complete
                        {meta?.blocks_by_default && ' (default for this type)'}
                      </span>
                    </label>
                  </fieldset>
                </li>
              )
            })}
          </ol>
        )}
      </section>

      {!readOnly && (
        <div className="flex flex-wrap gap-3">
          <button
            type="submit"
            disabled={isSaving}
            className="rounded-lg border border-[var(--color-paper-deep)] px-4 py-2.5 disabled:opacity-70"
          >
            {isSaving ? 'Saving…' : 'Save draft'}
          </button>
          <button
            type="button"
            onClick={() => void handlePublish()}
            disabled={isSaving || items.length === 0}
            className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-70"
          >
            Save &amp; publish
          </button>
        </div>
      )}
    </form>
  )
}
