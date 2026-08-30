import { useEffect, useState, type FormEvent } from 'react'
import {
  createBatch,
  deleteBatch,
  extractError,
  fetchBatches,
  fetchDepartments,
  updateBatch,
  type BatchOption,
  type DepartmentOption,
} from '../../api/admin'

const emptyForm = {
  department_id: '',
  name: '',
  intake_year: String(new Date().getFullYear()),
}

export function AdminBatchesPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [batches, setBatches] = useState<BatchOption[]>([])
  const [departmentFilter, setDepartmentFilter] = useState('')
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function loadBatches() {
    setIsLoading(true)
    try {
      setBatches(
        await fetchBatches(
          departmentFilter ? { department_id: Number(departmentFilter) } : undefined,
        ),
      )
    } catch (err) {
      setError(extractError(err, 'Could not load batches.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void fetchDepartments()
      .then(setDepartments)
      .catch(() => setError('Could not load departments.'))
  }, [])

  useEffect(() => {
    void loadBatches()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [departmentFilter])

  function resetForm() {
    setForm(emptyForm)
    setEditingId(null)
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)

    const payload = {
      department_id: Number(form.department_id),
      name: form.name,
      intake_year: Number(form.intake_year),
    }

    try {
      if (editingId) {
        await updateBatch(editingId, payload)
        setSuccess('Batch updated.')
      } else {
        await createBatch(payload)
        setSuccess('Batch created.')
      }
      resetForm()
      await loadBatches()
    } catch (err) {
      setError(extractError(err, 'Could not save the batch.'))
    } finally {
      setIsSaving(false)
    }
  }

  function startEditing(batch: BatchOption) {
    setEditingId(batch.id)
    setForm({
      department_id: String(batch.department?.id ?? ''),
      name: batch.name,
      intake_year: String(batch.intake_year),
    })
  }

  async function handleDelete(batch: BatchOption) {
    // The API deactivates rather than deletes when students still belong to a
    // batch, so say which will happen before asking.
    const message =
      batch.students_count > 0
        ? `${batch.name} still has ${batch.students_count} student(s), so it will be deactivated rather than deleted. Continue?`
        : `Delete ${batch.name}?`

    if (!window.confirm(message)) {
      return
    }

    try {
      await deleteBatch(batch.id)
      setSuccess(batch.students_count > 0 ? 'Batch deactivated.' : 'Batch deleted.')
      await loadBatches()
    } catch (err) {
      setError(extractError(err, 'Could not remove the batch.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Batches
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Intake cohorts, scoped to a department. The same name can exist in two
          departments — they are different cohorts.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
        <form
          onSubmit={handleSubmit}
          className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-5"
        >
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            {editingId ? 'Edit batch' : 'Add batch'}
          </h2>

          <div className="mt-4 grid gap-3">
            <select
              required
              value={form.department_id}
              onChange={(e) => setForm((p) => ({ ...p, department_id: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            >
              <option value="">Department</option>
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name} ({department.code})
                </option>
              ))}
            </select>

            <input
              required
              placeholder="Name (e.g. 2079 Intake)"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />

            <input
              required
              type="number"
              min={1980}
              max={2100}
              placeholder="Intake year"
              value={form.intake_year}
              onChange={(e) => setForm((p) => ({ ...p, intake_year: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
          </div>

          <div className="mt-4 flex gap-2">
            <button
              type="submit"
              disabled={isSaving}
              className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-70"
            >
              {isSaving ? 'Saving…' : editingId ? 'Save changes' : 'Create batch'}
            </button>
            {editingId && (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-lg border border-[var(--color-paper-deep)] px-4 py-2.5"
              >
                Cancel
              </button>
            )}
          </div>
        </form>

        <div className="space-y-3">
          <select
            value={departmentFilter}
            onChange={(e) => setDepartmentFilter(e.target.value)}
            className="rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2.5"
          >
            <option value="">All departments</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>

          <div className="overflow-x-auto rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70">
            {isLoading ? (
              <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading batches…</p>
            ) : batches.length === 0 ? (
              <p className="px-4 py-8 text-center text-[var(--color-ink-muted)]">
                No batches yet. Create one to enrol students into a cohort.
              </p>
            ) : (
              <table className="w-full min-w-[620px] text-left text-sm">
                <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                  <tr>
                    <th className="px-4 py-3 font-medium">Batch</th>
                    <th className="px-4 py-3 font-medium">Department</th>
                    <th className="px-4 py-3 font-medium">Intake</th>
                    <th className="px-4 py-3 font-medium">Students</th>
                    <th className="px-4 py-3 font-medium">Status</th>
                    <th className="px-4 py-3 font-medium" />
                  </tr>
                </thead>
                <tbody>
                  {batches.map((batch) => (
                    <tr
                      key={batch.id}
                      className="border-t border-[var(--color-paper-deep)] align-middle"
                    >
                      <td className="px-4 py-3 font-medium">{batch.name}</td>
                      <td className="px-4 py-3">{batch.department?.code ?? '—'}</td>
                      <td className="px-4 py-3 tabular-nums">{batch.intake_year}</td>
                      <td className="px-4 py-3 tabular-nums">{batch.students_count}</td>
                      <td className="px-4 py-3">
                        <span
                          className={
                            batch.is_active
                              ? 'inline-flex rounded-md bg-[var(--tint-success)] px-2 py-1 text-xs font-semibold text-[var(--color-success)]'
                              : 'inline-flex rounded-md bg-[var(--color-paper-deep)] px-2 py-1 text-xs font-semibold text-[var(--color-ink-muted)]'
                          }
                        >
                          {batch.is_active ? 'Active' : 'Retired'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <button
                          type="button"
                          onClick={() => startEditing(batch)}
                          className="mr-3 text-[var(--color-sea)] hover:underline"
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          onClick={() => void handleDelete(batch)}
                          className="text-[var(--color-danger)] hover:underline"
                        >
                          {batch.students_count > 0 ? 'Retire' : 'Delete'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
