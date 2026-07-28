import { useEffect, useState, type FormEvent } from 'react'
import {
  createStudent,
  deleteStudent,
  extractError,
  fetchDepartments,
  fetchSessions,
  fetchStudents,
  updateStudent,
  type DepartmentOption,
  type SessionOption,
  type StudentListItem,
} from '../../api/admin'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  password: '',
  registration_number: '',
  roll_number: '',
  batch: '',
  department_id: '',
  academic_session_id: '',
}

export function AdminStudentsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [sessions, setSessions] = useState<SessionOption[]>([])
  const [students, setStudents] = useState<StudentListItem[]>([])
  const [departmentFilter, setDepartmentFilter] = useState('')
  const [search, setSearch] = useState('')
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function loadStudents() {
    setIsLoading(true)
    setError(null)
    try {
      const response = await fetchStudents({
        department_id: departmentFilter || undefined,
        search: search || undefined,
      })
      setStudents(response.data)
    } catch (err) {
      setError(extractError(err, 'Could not load students.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void Promise.all([fetchDepartments(), fetchSessions()])
      .then(([departmentList, sessionList]) => {
        setDepartments(departmentList)
        setSessions(sessionList)
        const active = sessionList.find((session) => session.is_active)
        if (active) {
          setForm((prev) => ({
            ...prev,
            academic_session_id: prev.academic_session_id || String(active.id),
          }))
        }
      })
      .catch((err) => setError(extractError(err, 'Could not load form options.')))
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadStudents()
    }, 200)
    return () => window.clearTimeout(timer)
  }, [departmentFilter, search])

  function startEdit(student: StudentListItem) {
    setEditingId(student.id)
    setForm({
      name: student.name,
      email: student.email,
      phone: student.phone ?? '',
      password: '',
      registration_number: student.registration_number,
      roll_number: student.roll_number ?? '',
      batch: student.batch ?? '',
      department_id: student.department ? String(student.department.id) : '',
      academic_session_id: student.academic_session ? String(student.academic_session.id) : '',
    })
    setSuccess(null)
    setError(null)
  }

  function resetForm() {
    const active = sessions.find((session) => session.is_active)
    setEditingId(null)
    setForm({
      ...emptyForm,
      academic_session_id: active ? String(active.id) : '',
    })
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
      registration_number: form.registration_number,
      roll_number: form.roll_number || undefined,
      batch: form.batch || undefined,
      department_id: Number(form.department_id),
      academic_session_id: Number(form.academic_session_id),
    }

    try {
      if (editingId) {
        await updateStudent(editingId, payload)
        setSuccess('Student updated.')
      } else {
        if (!payload.password) {
          setError('Password is required for new students.')
          setIsSaving(false)
          return
        }
        await createStudent({ ...payload, password: payload.password })
        setSuccess('Student created and assigned to department.')
      }
      resetForm()
      await loadStudents()
    } catch (err) {
      setError(extractError(err, 'Could not save student.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete(id: number) {
    if (!window.confirm('Delete this student account?')) {
      return
    }

    try {
      await deleteStudent(id)
      setSuccess('Student deleted.')
      if (editingId === id) {
        resetForm()
      }
      await loadStudents()
    } catch (err) {
      setError(extractError(err, 'Could not delete student.'))
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Students
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Insert students and assign them to a department, batch, and academic session.
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
          placeholder="Search name, registration, batch…"
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5"
        />
      </div>

      <div className="grid gap-6 xl:grid-cols-[1fr_1.35fr]">
        <form onSubmit={handleSubmit} className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            {editingId ? 'Edit student' : 'Add student'}
          </h2>

          <div className="mt-4 grid gap-3">
            <input required placeholder="Full name" value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input required type="email" placeholder="Email" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input placeholder="Phone" value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input required={!editingId} type="password" placeholder={editingId ? 'New password (optional)' : 'Password'} value={form.password} onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input required placeholder="Registration number" value={form.registration_number} onChange={(e) => setForm((p) => ({ ...p, registration_number: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input placeholder="Roll number" value={form.roll_number} onChange={(e) => setForm((p) => ({ ...p, roll_number: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <input placeholder="Batch (e.g. 2022)" value={form.batch} onChange={(e) => setForm((p) => ({ ...p, batch: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5" />
            <select required value={form.department_id} onChange={(e) => setForm((p) => ({ ...p, department_id: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5">
              <option value="">Assign department</option>
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name} ({department.code})
                </option>
              ))}
            </select>
            <select required value={form.academic_session_id} onChange={(e) => setForm((p) => ({ ...p, academic_session_id: e.target.value }))} className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5">
              <option value="">Academic session</option>
              {sessions.map((session) => (
                <option key={session.id} value={session.id}>
                  {session.name}{session.is_active ? ' (active)' : ''}
                </option>
              ))}
            </select>
          </div>

          <div className="mt-4 flex gap-2">
            <button type="submit" disabled={isSaving} className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70">
              {isSaving ? 'Saving…' : editingId ? 'Update' : 'Create student'}
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
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Student</th>
                  <th className="px-4 py-3 font-medium">Department</th>
                  <th className="px-4 py-3 font-medium">Batch / Session</th>
                  <th className="px-4 py-3 font-medium">Supervisor</th>
                  <th className="px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {students.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-6 text-[var(--color-ink-muted)]">No students found.</td>
                  </tr>
                ) : (
                  students.map((student) => (
                    <tr key={student.id} className="border-t border-[var(--color-paper-deep)]">
                      <td className="px-4 py-3">
                        <p className="font-semibold">{student.name}</p>
                        <p className="text-[var(--color-ink-muted)]">{student.registration_number}</p>
                      </td>
                      <td className="px-4 py-3">{student.department?.name ?? '—'}</td>
                      <td className="px-4 py-3">
                        {student.batch ?? '—'}
                        <div className="text-[var(--color-ink-muted)]">{student.academic_session?.name ?? '—'}</div>
                      </td>
                      <td className="px-4 py-3">
                        {student.supervisor?.name ?? <span className="text-[var(--color-amber)]">Unassigned</span>}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button type="button" onClick={() => startEdit(student)} className="text-[var(--color-sea)] hover:underline">Edit</button>
                          <button type="button" onClick={() => void handleDelete(student.id)} className="text-[var(--color-danger)] hover:underline">Delete</button>
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
