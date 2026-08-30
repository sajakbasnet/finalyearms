import { useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  downloadProjectFile,
  extractError,
  fetchProjectWorkspace,
  saveEvaluation,
  updateMilestone,
  type ProjectWorkspace,
} from '../../api/teacher'

const milestoneStatuses = [
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed (approved)' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'skipped', label: 'Skipped' },
]

export function TeacherProjectPage() {
  const { id } = useParams()
  const projectId = Number(id)
  const [project, setProject] = useState<ProjectWorkspace | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [evaluation, setEvaluation] = useState({
    innovation: 70,
    implementation: 70,
    documentation: 70,
    presentation: 70,
    testing: 70,
    remarks: '',
  })

  async function load() {
    const data = await fetchProjectWorkspace(projectId)
    setProject(data)
    if (data.evaluation) {
      setEvaluation({
        innovation: data.evaluation.innovation,
        implementation: data.evaluation.implementation,
        documentation: data.evaluation.documentation,
        presentation: data.evaluation.presentation,
        testing: data.evaluation.testing,
        remarks: data.evaluation.remarks ?? '',
      })
    }
  }

  useEffect(() => {
    if (!projectId) {
      return
    }
    void load().catch((err) => setError(extractError(err, 'Could not load project workspace.')))
  }, [projectId])

  async function handleMilestone(milestoneId: number, status: string) {
    setIsSaving(true)
    setError(null)
    try {
      await updateMilestone(milestoneId, {
        status,
        remarks: status === 'completed' ? 'Approved by supervisor' : undefined,
      })
      await load()
      setSuccess('Milestone updated.')
    } catch (err) {
      setError(extractError(err, 'Could not update milestone.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleEvaluation(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    try {
      await saveEvaluation(projectId, evaluation)
      await load()
      setSuccess('Evaluation saved.')
    } catch (err) {
      setError(extractError(err, 'Could not save evaluation.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDownload(path: string, label: string) {
    try {
      await downloadProjectFile(projectId, path, label.replace(/\s+/g, '-').toLowerCase())
    } catch (err) {
      setError(extractError(err, 'Could not download file.'))
    }
  }

  if (!project) {
    return <p className="text-[var(--color-ink-muted)]">{error ?? 'Loading project…'}</p>
  }

  return (
    <div className="space-y-6">
      <div>
        <Link to="/teacher/students" className="text-sm text-[var(--color-sea)] hover:underline">
          ← Back to students
        </Link>
        <h1 className="mt-2 font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          {project.title}
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          {project.student.name} · {project.student.registration_number} · {project.status_label}
        </p>
      </div>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
        <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
          Milestones
        </h2>
        <div className="mt-4 space-y-3">
          {project.milestones.map((milestone) => (
            <div
              key={milestone.id}
              className="flex flex-col gap-3 rounded-lg border border-[var(--color-paper-deep)] p-3 md:flex-row md:items-center md:justify-between"
            >
              <div>
                <p className="font-semibold">{milestone.title}</p>
                <p className="text-sm text-[var(--color-ink-muted)]">
                  Due {milestone.due_date ?? '—'} · {milestone.status_label}
                </p>
              </div>
              <select
                disabled={isSaving}
                value={milestone.status}
                onChange={(e) => void handleMilestone(milestone.id, e.target.value)}
                className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
              >
                {milestoneStatuses.map((status) => (
                  <option key={status.value} value={status.value}>
                    {status.label}
                  </option>
                ))}
              </select>
            </div>
          ))}
        </div>
      </section>

      <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
        <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
          Download uploaded files
        </h2>
        {project.files.length === 0 ? (
          <p className="mt-3 text-sm text-[var(--color-ink-muted)]">No files uploaded yet.</p>
        ) : (
          <ul className="mt-3 space-y-2">
            {project.files.map((file) => (
              <li key={file.key} className="flex items-center justify-between gap-3 text-sm">
                <span>{file.label}</span>
                <button
                  type="button"
                  onClick={() => void handleDownload(file.path, file.label)}
                  className="text-[var(--color-sea)] hover:underline"
                >
                  Download
                </button>
              </li>
            ))}
          </ul>
        )}
        {project.final_submission?.github_repository && (
          <p className="mt-3 text-sm">
            GitHub:{' '}
            <a
              href={project.final_submission.github_repository}
              target="_blank"
              rel="noreferrer"
              className="text-[var(--color-sea)]"
            >
              {project.final_submission.github_repository}
            </a>
          </p>
        )}
      </section>

      <form onSubmit={handleEvaluation} className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
        <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
          Evaluate final project
        </h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {(['innovation', 'implementation', 'documentation', 'presentation', 'testing'] as const).map((field) => (
            <label key={field} className="block space-y-1 text-sm">
              <span className="capitalize text-[var(--color-ink-muted)]">{field}</span>
              <input
                type="number"
                min={0}
                max={100}
                required
                value={evaluation[field]}
                onChange={(e) =>
                  setEvaluation((prev) => ({ ...prev, [field]: Number(e.target.value) }))
                }
                className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            </label>
          ))}
        </div>
        <textarea
          value={evaluation.remarks}
          onChange={(e) => setEvaluation((prev) => ({ ...prev, remarks: e.target.value }))}
          placeholder="Remarks"
          className="mt-3 min-h-24 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5 text-sm"
        />
        {project.evaluation && (
          <p className="mt-2 text-sm text-[var(--color-ink-muted)]">
            Current overall score: {project.evaluation.overall_score}
          </p>
        )}
        <button
          type="submit"
          disabled={isSaving}
          className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
        >
          Save evaluation
        </button>
      </form>
    </div>
  )
}
