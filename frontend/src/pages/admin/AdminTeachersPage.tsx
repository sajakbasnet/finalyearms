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
import { Modal } from '../../components/Modal'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  password: '',
  employee_id: '',
  designation: '',
  department_id: '',
  max_projects: '5',
}

export function AdminTeachersPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [teachers, setTeachers] = useState<TeacherListItem[]>([])
  const [departmentFilter, setDepartmentFilter] = useState('')
  const [search, setSearch] = useState('')
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
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

  function openCreate() {
    setEditingId(null)
    setForm(emptyForm)
    setError(null)
    setSuccess(null)
    setIsModalOpen(true)
  }

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
      max_projects: String(teacher.max_projects),
    })
    setSuccess(null)
    setError(null)
    setIsModalOpen(true)
  }

  function closeModal() {
    setIsModalOpen(false)
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
      max_projects: Number(form.max_projects || 5),
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
      closeModal()
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
        closeModal()
      }
      await loadTeachers()
    } catch (err) {
      setError(extractError(err, 'Could not delete teacher.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Supervisors & Teachers</h1>
          <p className="mt-1 text-slate-500">
            Manage academic supervisors and their departmental supervision capacities.
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
          Add Teacher
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
            placeholder="Search by name, email, employee ID…"
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
          <div className="py-12 text-center text-slate-400">Loading supervisors…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Supervisor</th>
                  <th className="px-6 py-4">Employee ID</th>
                  <th className="px-6 py-4">Department</th>
                  <th className="px-6 py-4">Designation</th>
                  <th className="px-6 py-4">Max Projects</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {teachers.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      No teachers found. Click &quot;Add Teacher&quot; above to register one.
                    </td>
                  </tr>
                ) : (
                  teachers.map((teacher) => (
                    <tr key={teacher.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4">
                        <div className="font-semibold text-slate-900">{teacher.name}</div>
                        <div className="text-xs text-slate-500">{teacher.email}</div>
                        {teacher.phone && <div className="text-xs text-slate-400">{teacher.phone}</div>}
                      </td>
                      <td className="px-6 py-4 font-mono text-xs text-slate-600">
                        {teacher.employee_id}
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                          {teacher.department?.name ?? '—'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-slate-700 font-medium">
                        {teacher.designation || <span className="text-slate-400 italic">—</span>}
                      </td>
                      <td className="px-6 py-4 font-semibold text-slate-800">
                        <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                          {teacher.max_projects} projects max
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEdit(teacher)}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:border-blue-200 transition"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => void handleDelete(teacher.id)}
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
        title={editingId ? 'Edit Teacher' : 'Add New Teacher'}
        subtitle={
          editingId
            ? 'Update teacher profile, department and project limits.'
            : 'Register a teacher account and assign them to an academic department.'
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Full Name</span>
              <input
                required
                placeholder="Dr. Jane Doe"
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
                placeholder="jane.doe@univ.edu"
                value={form.email}
                onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Employee ID</span>
              <input
                required
                placeholder="EMP-1024"
                value={form.employee_id}
                onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>

            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Designation</span>
              <input
                placeholder="Associate Professor"
                value={form.designation}
                onChange={(e) => setForm((p) => ({ ...p, designation: e.target.value }))}
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
              <span>Max Projects Supervised</span>
              <input
                type="number"
                min={1}
                max={50}
                value={form.max_projects}
                onChange={(e) => setForm((p) => ({ ...p, max_projects: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Phone Number (Optional)</span>
              <input
                placeholder="+1 555-0199"
                value={form.phone}
                onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>

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
          </div>

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
              {isSaving ? 'Saving…' : editingId ? 'Update Teacher' : 'Create Teacher'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
