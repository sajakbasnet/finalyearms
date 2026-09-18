import { useEffect, useState, type FormEvent } from 'react'
import {
  createStudent,
  deleteStudent,
  extractError,
  fetchBatches,
  fetchDepartments,
  fetchSessions,
  fetchStudents,
  updateStudent,
  type BatchOption,
  type DepartmentOption,
  type SessionOption,
  type StudentListItem,
} from '../../api/admin'
import { Modal } from '../../components/Modal'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  password: '',
  registration_number: '',
  roll_number: '',
  batch_id: '',
  department_id: '',
  academic_session_id: '',
}

export function AdminStudentsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [batches, setBatches] = useState<BatchOption[]>([])
  const [sessions, setSessions] = useState<SessionOption[]>([])
  const [students, setStudents] = useState<StudentListItem[]>([])
  const [departmentFilter, setDepartmentFilter] = useState('')
  const [search, setSearch] = useState('')
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
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
    void Promise.all([fetchDepartments(), fetchSessions(), fetchBatches()])
      .then(([departmentList, sessionList, batchList]) => {
        setDepartments(departmentList)
        setSessions(sessionList)
        setBatches(batchList)
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

  function openCreate() {
    const active = sessions.find((session) => session.is_active)
    setEditingId(null)
    setForm({
      ...emptyForm,
      academic_session_id: active ? String(active.id) : '',
    })
    setError(null)
    setSuccess(null)
    setIsModalOpen(true)
  }

  function startEdit(student: StudentListItem) {
    setEditingId(student.id)
    setForm({
      name: student.name,
      email: student.email,
      phone: student.phone ?? '',
      password: '',
      registration_number: student.registration_number,
      roll_number: student.roll_number ?? '',
      batch_id: student.batch_id ? String(student.batch_id) : '',
      department_id: student.department ? String(student.department.id) : '',
      academic_session_id: student.academic_session ? String(student.academic_session.id) : '',
    })
    setSuccess(null)
    setError(null)
    setIsModalOpen(true)
  }

  function closeModal() {
    setIsModalOpen(false)
    setEditingId(null)
    const active = sessions.find((session) => session.is_active)
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
      batch_id: form.batch_id ? Number(form.batch_id) : null,
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
      closeModal()
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
        closeModal()
      }
      await loadStudents()
    } catch (err) {
      setError(extractError(err, 'Could not delete student.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Students</h1>
          <p className="mt-1 text-slate-500">
            Register students and track their cohorts, departments, and academic sessions.
          </p>
        </div>
        <button
          type="button"
          onClick={openCreate}
          className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 active:bg-blue-800"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
          </svg>
          Add Student
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

      {/* Filter toolbar */}
      <div className="grid gap-4 sm:grid-cols-[240px_1fr] rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <select
          value={departmentFilter}
          onChange={(e) => setDepartmentFilter(e.target.value)}
          className="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
        >
          <option value="">All Departments</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name} ({department.code})
            </option>
          ))}
        </select>
        <div className="relative">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by name, registration number, roll number…"
            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 pl-10 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
          />
          <svg
            className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>

      {/* Details Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading students…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Student</th>
                  <th className="px-6 py-4">Registration No.</th>
                  <th className="px-6 py-4">Department</th>
                  <th className="px-6 py-4">Batch</th>
                  <th className="px-6 py-4">Session</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {students.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      No students found. Click &quot;Add Student&quot; above to enroll one.
                    </td>
                  </tr>
                ) : (
                  students.map((student) => (
                    <tr key={student.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4">
                        <div className="font-semibold text-slate-900">{student.name}</div>
                        <div className="text-xs text-slate-500">{student.email}</div>
                        {student.phone && <div className="text-xs text-slate-400">{student.phone}</div>}
                      </td>
                      <td className="px-6 py-4 font-mono text-xs text-slate-700">
                        {student.registration_number}
                        {student.roll_number && (
                          <div className="text-slate-400">Roll: {student.roll_number}</div>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                          {student.department?.code ?? '—'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-slate-700">
                        {student.batch ? (
                          <span className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                            {student.batch}
                          </span>
                        ) : (
                          <span className="text-slate-400 italic">No batch</span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-slate-700 text-xs font-medium">
                        {student.academic_session?.name ?? '—'}
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEdit(student)}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:border-blue-200 transition"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => void handleDelete(student.id)}
                            className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 hover:border-red-300 transition"
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
          </div>
        )}
      </div>

      {/* Modal Form */}
      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        maxWidth="xl"
        title={editingId ? 'Edit Student' : 'Add New Student'}
        subtitle={
          editingId
            ? 'Update student records, academic batch and department.'
            : 'Enroll a new student and assign them to an academic department and session.'
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Full Name</span>
              <input
                required
                placeholder="John Smith"
                value={form.name}
                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>

            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Email Address</span>
              <input
                required
                type="email"
                placeholder="john.smith@student.univ.edu"
                value={form.email}
                onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Registration Number</span>
              <input
                required
                placeholder="REG-2026-001"
                value={form.registration_number}
                onChange={(e) => setForm((p) => ({ ...p, registration_number: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>

            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Roll Number</span>
              <input
                placeholder="CS-042"
                value={form.roll_number}
                onChange={(e) => setForm((p) => ({ ...p, roll_number: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Department</span>
              <select
                required
                value={form.department_id}
                onChange={(e) => setForm((p) => ({ ...p, department_id: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">Assign Department</option>
                {departments.map((dept) => (
                  <option key={dept.id} value={dept.id}>
                    {dept.name} ({dept.code})
                  </option>
                ))}
              </select>
            </label>

            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Batch Cohort</span>
              <select
                value={form.batch_id}
                onChange={(e) => setForm((p) => ({ ...p, batch_id: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">No Batch</option>
                {batches
                  .filter((b) => !form.department_id || b.department?.id === Number(form.department_id))
                  .map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name} ({b.intake_year})
                    </option>
                  ))}
              </select>
            </label>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Academic Session</span>
              <select
                required
                value={form.academic_session_id}
                onChange={(e) => setForm((p) => ({ ...p, academic_session_id: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">Select Session</option>
                {sessions.map((session) => (
                  <option key={session.id} value={session.id}>
                    {session.name} {session.is_active ? '(Active)' : ''}
                  </option>
                ))}
              </select>
            </label>

            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Phone Number (Optional)</span>
              <input
                placeholder="+1 555-0144"
                value={form.phone}
                onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          </div>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>{editingId ? 'New Password (leave blank to keep current)' : 'Account Password'}</span>
            <input
              required={!editingId}
              type="password"
              placeholder="••••••••"
              value={form.password}
              onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              onClick={closeModal}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSaving}
              className="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60 transition"
            >
              {isSaving ? 'Saving…' : editingId ? 'Update Student' : 'Enroll Student'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
