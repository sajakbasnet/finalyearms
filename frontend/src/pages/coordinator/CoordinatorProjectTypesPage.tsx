import { useEffect, useState, type FormEvent } from 'react'
import { extractError } from '../../api/admin'
import {
  createProjectType,
  deleteProjectType,
  fetchProjectTypes,
  type ProjectTypeItem,
} from '../../api/coordinator'
import { Modal } from '../../components/Modal'

const emptyForm = { name: '', description: '', requires_employer: false }

export function CoordinatorProjectTypesPage() {
  const [types, setTypes] = useState<ProjectTypeItem[]>([])
  const [form, setForm] = useState(emptyForm)
  const [isModalOpen, setIsModalOpen] = useState(false)
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

  function openCreate() {
    setForm(emptyForm)
    setError(null)
    setSuccess(null)
    setIsModalOpen(true)
  }

  function closeModal() {
    setIsModalOpen(false)
    setForm(emptyForm)
  }

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
      closeModal()
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not create the project type.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete(type: ProjectTypeItem) {
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
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Project Types</h1>
          <p className="mt-1 text-slate-500">
            Define project tracks (e.g. Research Thesis, Software Capstone, Industry Internship).
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
          Add Project Type
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

      {/* Full-width Details Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading project types…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Type</th>
                  <th className="px-6 py-4">Description</th>
                  <th className="px-6 py-4">Templates</th>
                  <th className="px-6 py-4">Placement Requirement</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {types.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      No project types configured yet. Click &quot;Add Project Type&quot; above to create one.
                    </td>
                  </tr>
                ) : (
                  types.map((type) => (
                    <tr key={type.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4">
                        <div className="font-semibold text-slate-900">{type.name}</div>
                        {type.is_default && (
                          <span className="text-[10px] font-semibold text-blue-600 uppercase tracking-wider">
                            Standard Track
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-slate-600 max-w-sm">
                        {type.description || <span className="text-slate-400 italic">No description</span>}
                      </td>
                      <td className="px-6 py-4 tabular-nums text-slate-700 font-medium">
                        {type.activity_templates_count}
                      </td>
                      <td className="px-6 py-4">
                        {type.requires_employer ? (
                          <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 border border-blue-200">
                            Host Employer Required
                          </span>
                        ) : (
                          <span className="text-slate-400 text-xs">Academic Only</span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <span
                          className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                            type.is_active
                              ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                              : 'bg-slate-100 text-slate-600'
                          }`}
                        >
                          {type.is_active ? 'Active' : 'Disabled'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => void handleDelete(type)}
                          className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 hover:border-red-300 transition"
                        >
                          {type.is_default ? 'Deactivate' : 'Delete'}
                        </button>
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
        title="Add Project Type"
        subtitle="Specify a track or format for student final year projects."
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Project Type Name</span>
            <input
              required
              placeholder="e.g. Industrial Attachment / Capstone"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Description</span>
            <textarea
              rows={3}
              placeholder="Explain the scope and prerequisites for this project type..."
              value={form.description}
              onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <label className="flex items-start gap-3 text-sm cursor-pointer select-none">
              <input
                type="checkbox"
                checked={form.requires_employer}
                onChange={(e) => setForm((p) => ({ ...p, requires_employer: e.target.checked }))}
                className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4"
              />
              <div>
                <span className="font-semibold text-slate-900">Requires an external employer / host</span>
                <p className="text-xs text-slate-500 mt-0.5">
                  Check this for internships or industry placements that require co-evaluation from a company supervisor.
                </p>
              </div>
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
              {isSaving ? 'Saving…' : 'Create Project Type'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
