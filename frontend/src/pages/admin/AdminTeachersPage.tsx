import { useEffect, useState, type FormEvent } from 'react'
import {
  createTeacher,
  deleteTeacher,
  extractError,
  fetchDepartments,
  fetchTeachers,
  updateTeacher,
  type DepartmentOption,
  type TeacherListItem,
} from '../../api/admin'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  password: '',
  employee_id: '',
  designation: '',
  department_id: '',
  maximum_students: '5',
}

export function AdminTeachersPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [teachers, setTeachers] = useState<TeacherListItem[]>([])
  const [departmentFilter, setDepartmentFilter] = useState('')
  const [search, setSearch] = useState('')
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function loadTeachers() {
    setIsLoading(true)
    setError(null)
    try {
      setTeachers(
        await fetchTeachers({
          department_id: departmentFilter || undefined,
          search: search || undefined,
        }),
      )
    } catch (err) {
      setError(extractError(err, 'Could not load teachers.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void fetchDepartments()
      .then(setDepartments)
      .catch((err) => setError(extractError(err, 'Could not load departments.')))
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadTeachers()
    }, 200)
    return () => window.clearTimeout(timer)
  }, [departmentFilter, search])

  function startEdit(teacher: TeacherListItem) {
    setEditingId(teacher.id)
    setForm({
      name: teacher.name,
      email: teacher.email,
      phone: teacher.phone ?? '',
      password: '',
      employee_id: teacher.employee_id,
      designation: teacher.designation ?? '',
      department_id: teacher.department ? String(teacher.department.id) : '',
      maximum_students: String(teacher.maximum_students),
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

    const payload = {
      name: form.name,
      email: form.email,
      phone: form.phone || undefined,
      password: form.password || undefined,
      employee_id: form.employee_id,
      designation: form.designation || undefined,
      department_id: Number(form.department_id),
      maximum_students: Number(form.maximum_students || 5),
    }

    try {
      if (editingId) {
        await updateTeacher(editingId, payload)
        setSuccess('Teacher updated.')
      } else {
        if (!payload.password) {
          setError('Password is required for new teachers.')
          setIsSaving(false)
          return
        }
        await createTeacher({ ...payload, password: payload.password })
        setSuccess('Teacher created and assigned to department.')
      }
      resetForm()
      await loadTeachers()
    } catch (err) {
      setError(extractError(err, 'Could not save teacher.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete(id: number) {
    if (!window.confirm('Delete this teacher account?')) {
      return
    }

    try {
      await deleteTeacher(id)
      setSuccess('Teacher deleted.')
      if (editingId === id) {
        resetForm()
      }
      await loadTeachers()
    } catch (err) {
      setError(extractError(err, 'Could not delete teacher.'))
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Teachers
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Insert teachers and assign each one to a final-year department.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-3 md:grid-cols-2">
        <select
          value={departmentFilter}
          onChange={(e) => setDepartmentFilter(e.target.value)}
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5"
        >
          <option value="">All departments</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </select>
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search name, email, employee ID…"
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5"
        />
      </div>

      <div className="grid gap-6 xl:grid-cols-[1fr_1.35fr]">
        <form onSubmit={handleSubmit} className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            {editingId ? 'Edit teacher' : 'Add teacher'}
          </h2>

          <div className="mt-4 grid gap-3">
            <input required placeholder="Full name" value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input required type="email" placeholder="Email" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input placeholder="Phone" value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input required={!editingId} type="password" placeholder={editingId ? 'New password (optional)' : 'Password'} value={form.password} onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input required placeholder="Employee ID" value={form.employee_id} onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input placeholder="Designation" value={form.designation} onChange={(e) => setForm((p) => ({ ...p, designation: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <select required value={form.department_id} onChange={(e) => setForm((p) => ({ ...p, department_id: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5">
              <option value="">Assign department</option>
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name} ({department.code})
                </option>
              ))}
            </select>
            <input type="number" min={1} max={50} placeholder="Max students" value={form.maximum_students} onChange={(e) => setForm((p) => ({ ...p, maximum_students: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
          </div>

          <div className="mt-4 flex gap-2">
            <button type="submit" disabled={isSaving} className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70">
              {isSaving ? 'Saving…' : editingId ? 'Update' : 'Create teacher'}
            </button>
            {editingId && (
              <button type="button" onClick={resetForm} className="rounded-lg border border-[var(--color-paper-deep)] px-4 py-2.5 text-sm">
                Cancel
              </button>
            )}
          </div>
        </form>

        <div className="overflow-x-auto rounded-xl border border-[var(--color-paper-deep)] bg-white/80">
          {isLoading ? (
            <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading…</p>
          ) : (
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Teacher</th>
                  <th className="px-4 py-3 font-medium">Department</th>
                  <th className="px-4 py-3 font-medium">Load</th>
                  <th className="px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {teachers.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-6 text-[var(--color-ink-muted)]">No teachers found.</td>
                  </tr>
                ) : (
                  teachers.map((teacher) => (
                    <tr key={teacher.id} className="border-t border-[var(--color-paper-deep)]">
                      <td className="px-4 py-3">
                        <p className="font-semibold">{teacher.name}</p>
                        <p className="text-[var(--color-ink-muted)]">{teacher.employee_id} · {teacher.email}</p>
                      </td>
                      <td className="px-4 py-3">{teacher.department?.name ?? '—'}</td>
                      <td className="px-4 py-3">{teacher.active_students_count}/{teacher.maximum_students}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button type="button" onClick={() => startEdit(teacher)} className="text-[var(--color-sea)] hover:underline">Edit</button>
                          <button type="button" onClick={() => void handleDelete(teacher.id)} className="text-[var(--color-danger)] hover:underline">Delete</button>
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
