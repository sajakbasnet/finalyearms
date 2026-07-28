import { useEffect, useState, type FormEvent } from 'react'
import {
  createDepartment,
  deleteDepartment,
  extractError,
  fetchDepartments,
  updateDepartment,
  type DepartmentOption,
} from '../../api/admin'

const emptyForm = { name: '', code: '', description: '' }

export function AdminDepartmentsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function load() {
    setIsLoading(true)
    setError(null)
    try {
      setDepartments(await fetchDepartments())
    } catch (err) {
      setError(extractError(err, 'Could not load departments.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  function startEdit(department: DepartmentOption) {
    setEditingId(department.id)
    setForm({
      name: department.name,
      code: department.code,
      description: department.description ?? '',
    })
    setSuccess(null)
    setError(null)
  }

  function resetForm() {
    setEditingId(null)
    setForm(emptyForm)
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)

    try {
      if (editingId) {
        await updateDepartment(editingId, form)
        setSuccess('Department updated.')
      } else {
        await createDepartment(form)
        setSuccess('Department created.')
      }
      resetForm()
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not save department.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete(id: number) {
    if (!window.confirm('Delete this department?')) {
      return
    }

    setError(null)
    setSuccess(null)
    try {
      await deleteDepartment(id)
      setSuccess('Department deleted.')
      if (editingId === id) {
        resetForm()
      }
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not delete department.'))
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Departments
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Create departments and use them when adding teachers and students.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
        <form onSubmit={handleSubmit} className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            {editingId ? 'Edit department' : 'Add department'}
          </h2>

          <div className="mt-4 space-y-3">
            <label className="block space-y-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Name</span>
              <input
                required
                value={form.name}
                onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                placeholder="Computer Science"
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Code</span>
              <input
                required
                value={form.code}
                onChange={(e) => setForm((prev) => ({ ...prev, code: e.target.value }))}
                className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                placeholder="CS"
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Description</span>
              <textarea
                value={form.description}
                onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
                className="min-h-24 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            </label>
          </div>

          <div className="mt-4 flex gap-2">
            <button
              type="submit"
              disabled={isSaving}
              className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
            >
              {isSaving ? 'Saving…' : editingId ? 'Update' : 'Create'}
            </button>
            {editingId && (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-lg border border-[var(--color-paper-deep)] px-4 py-2.5 text-sm"
              >
                Cancel
              </button>
            )}
          </div>
        </form>

        <div className="overflow-hidden rounded-xl border border-[var(--color-paper-deep)] bg-white/80">
          {isLoading ? (
            <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading…</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Department</th>
                  <th className="px-4 py-3 font-medium">Teachers</th>
                  <th className="px-4 py-3 font-medium">Students</th>
                  <th className="px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {departments.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-6 text-[var(--color-ink-muted)]">
                      No departments yet. Create one to get started.
                    </td>
                  </tr>
                ) : (
                  departments.map((department) => (
                    <tr key={department.id} className="border-t border-[var(--color-paper-deep)]">
                      <td className="px-4 py-3">
                        <p className="font-semibold">{department.name}</p>
                        <p className="text-[var(--color-ink-muted)]">{department.code}</p>
                      </td>
                      <td className="px-4 py-3">{department.teachers_count ?? 0}</td>
                      <td className="px-4 py-3">{department.students_count ?? 0}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={() => startEdit(department)}
                            className="text-[var(--color-sea)] hover:underline"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => void handleDelete(department.id)}
                            className="text-[var(--color-danger)] hover:underline"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  )
}
