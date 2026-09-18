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
import { Modal } from '../../components/Modal'

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
  const [isModalOpen, setIsModalOpen] = useState(false)
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

  function openCreate() {
    setEditingId(null)
    setForm(emptyForm)
    setError(null)
    setSuccess(null)
    setIsModalOpen(true)
  }

  function startEditing(batch: BatchOption) {
    setEditingId(batch.id)
    setForm({
      department_id: String(batch.department?.id ?? ''),
      name: batch.name,
      intake_year: String(batch.intake_year),
    })
    setError(null)
    setSuccess(null)
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
      closeModal()
      await loadBatches()
    } catch (err) {
      setError(extractError(err, 'Could not save the batch.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete(batch: BatchOption) {
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
      if (editingId === batch.id) {
        closeModal()
      }
      await loadBatches()
    } catch (err) {
      setError(extractError(err, 'Could not remove the batch.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Batches</h1>
          <p className="mt-1 text-slate-500">
            Intake cohorts scoped to a department to manage academic progression.
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
          Add Batch
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
      <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex items-center gap-3">
          <label htmlFor="dept-filter" className="text-sm font-medium text-slate-600">
            Filter by Department:
          </label>
          <select
            id="dept-filter"
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
        </div>
        <div className="text-sm text-slate-500">
          Showing <span className="font-semibold text-slate-900">{batches.length}</span> batch(es)
        </div>
      </div>

      {/* Details Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading batches…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Batch</th>
                  <th className="px-6 py-4">Department</th>
                  <th className="px-6 py-4">Intake Year</th>
                  <th className="px-6 py-4">Enrolled Students</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {batches.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      No batches found. Click &quot;Add Batch&quot; above to create one.
                    </td>
                  </tr>
                ) : (
                  batches.map((batch) => (
                    <tr key={batch.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4 font-semibold text-slate-900">{batch.name}</td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                          {batch.department?.name ?? '—'}
                        </span>
                      </td>
                      <td className="px-6 py-4 tabular-nums text-slate-700 font-medium">{batch.intake_year}</td>
                      <td className="px-6 py-4 tabular-nums text-slate-700 font-medium">
                        {batch.students_count}
                      </td>
                      <td className="px-6 py-4">
                        <span
                          className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                            batch.is_active
                              ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                              : 'bg-slate-100 text-slate-600'
                          }`}
                        >
                          {batch.is_active ? 'Active' : 'Retired'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEditing(batch)}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:border-blue-200 transition"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => void handleDelete(batch)}
                            className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 hover:border-red-300 transition"
                          >
                            {batch.students_count > 0 ? 'Retire' : 'Delete'}
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
        title={editingId ? 'Edit Batch' : 'Add New Batch'}
        subtitle={
          editingId
            ? 'Modify batch details and assigned department.'
            : 'Register a new student intake cohort.'
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Department</span>
            <select
              required
              value={form.department_id}
              onChange={(e) => setForm((p) => ({ ...p, department_id: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="">Select a department</option>
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name} ({department.code})
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Batch Name</span>
            <input
              required
              placeholder="e.g. 2026 Batch A"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Intake Year</span>
            <input
              required
              type="number"
              min={1980}
              max={2100}
              value={form.intake_year}
              onChange={(e) => setForm((p) => ({ ...p, intake_year: e.target.value }))}
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
              {isSaving ? 'Saving…' : editingId ? 'Save Changes' : 'Create Batch'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
