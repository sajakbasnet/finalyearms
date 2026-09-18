import { useEffect, useState, type FormEvent } from 'react'
import {
  createDepartment,
  deleteDepartment,
  extractError,
  fetchDepartments,
  updateDepartment,
  type DepartmentOption,
} from '../../api/admin'
import { Modal } from '../../components/Modal'

const emptyForm = { name: '', code: '', description: '' }

export function AdminDepartmentsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
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

  function openCreate() {
    setEditingId(null)
    setForm(emptyForm)
    setError(null)
    setSuccess(null)
    setIsModalOpen(true)
  }

  function startEdit(department: DepartmentOption) {
    setEditingId(department.id)
    setForm({
      name: department.name,
      code: department.code,
      description: department.description ?? '',
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

    try {
      if (editingId) {
        await updateDepartment(editingId, form)
        setSuccess('Department updated.')
      } else {
        await createDepartment(form)
        setSuccess('Department created.')
      }
      closeModal()
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
        closeModal()
      }
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not delete department.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Departments</h1>
          <p className="mt-1 text-slate-500">
            Create departments and use them when organizing teachers, students, and project tracks.
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
          Add Department
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

      {/* Details Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading departments…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Department</th>
                  <th className="px-6 py-4">Code</th>
                  <th className="px-6 py-4">Description</th>
                  <th className="px-6 py-4">Teachers</th>
                  <th className="px-6 py-4">Students</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {departments.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      No departments yet. Click &quot;Add Department&quot; above to create one.
                    </td>
                  </tr>
                ) : (
                  departments.map((department) => (
                    <tr key={department.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4 font-semibold text-slate-900">{department.name}</td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                          {department.code}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-slate-600 max-w-xs truncate">
                        {department.description || <span className="text-slate-400 italic">None</span>}
                      </td>
                      <td className="px-6 py-4 font-medium text-slate-700">
                        {department.teachers_count ?? 0}
                      </td>
                      <td className="px-6 py-4 font-medium text-slate-700">
                        {department.students_count ?? 0}
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEdit(department)}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:border-blue-200 transition"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => void handleDelete(department.id)}
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
        title={editingId ? 'Edit Department' : 'Add New Department'}
        subtitle={
          editingId
            ? 'Update department details and codes.'
            : 'Fill in details to register a new academic department.'
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Department Name</span>
            <input
              required
              value={form.name}
              onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              placeholder="e.g. Computer Science & Engineering"
            />
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Department Code</span>
            <input
              required
              value={form.code}
              onChange={(e) => setForm((prev) => ({ ...prev, code: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              placeholder="e.g. CSE"
            />
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Description</span>
            <textarea
              rows={3}
              value={form.description}
              onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              placeholder="Brief overview of the department or programme..."
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
              {isSaving ? 'Saving…' : editingId ? 'Update Department' : 'Create Department'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
