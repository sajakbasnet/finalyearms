import { useEffect, useState, type FormEvent } from 'react'
import {
  extractError,
  fetchWorkspace,
  submitProgress,
  type StudentWorkspace,
} from '../../api/student'

export function StudentProgressPage() {
  const [workspace, setWorkspace] = useState<StudentWorkspace | null>(null)
  const [form, setForm] = useState({
    title: '',
    description: '',
    percentage_completed: '25',
    demo_link: '',
    git_repository: '',
  })
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)

  async function load() {
    setWorkspace(await fetchWorkspace())
  }

  useEffect(() => {
    void load().catch((err) => setError(extractError(err, 'Could not load progress reports.')))
  }, [])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)
    try {
      await submitProgress({
        title: form.title,
        description: form.description || undefined,
        percentage_completed: Number(form.percentage_completed),
        demo_link: form.demo_link || undefined,
        git_repository: form.git_repository || undefined,
      })
      setSuccess('Progress report submitted.')
      setForm({
        title: '',
        description: '',
        percentage_completed: '25',
        demo_link: '',
        git_repository: '',
      })
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not submit progress report.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Progress reports
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Submit progress updates with completion percentage, demo link, and repository.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
        <form onSubmit={handleSubmit} className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            New report
          </h2>
          <div className="mt-4 space-y-3">
            <input
              required
              placeholder="Title"
              value={form.title}
              onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
              className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <textarea
              placeholder="Description"
              value={form.description}
              onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
              className="min-h-28 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <input
              required
              type="number"
              min={0}
              max={100}
              value={form.percentage_completed}
              onChange={(e) => setForm((p) => ({ ...p, percentage_completed: e.target.value }))}
              className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <input
              type="url"
              placeholder="Demo link"
              value={form.demo_link}
              onChange={(e) => setForm((p) => ({ ...p, demo_link: e.target.value }))}
              className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <input
              type="url"
              placeholder="Git repository"
              value={form.git_repository}
              onChange={(e) => setForm((p) => ({ ...p, git_repository: e.target.value }))}
              className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
          </div>
          <button
            type="submit"
            disabled={isSaving}
            className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
          >
            {isSaving ? 'Submitting…' : 'Submit progress'}
          </button>
        </form>

        <section className="space-y-3">
          {(workspace?.progress_reports ?? []).length === 0 ? (
            <p className="text-[var(--color-ink-muted)]">No progress reports submitted yet.</p>
          ) : (
            workspace?.progress_reports.map((report) => (
              <article key={report.id} className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="font-semibold">{report.title}</h3>
                    <p className="text-sm text-[var(--color-ink-muted)]">
                      {report.percentage_completed}% · {report.status}
                    </p>
                  </div>
                </div>
                <p className="mt-2 text-sm">{report.description}</p>
                {report.comments.length > 0 && (
                  <div className="mt-3 border-t border-[var(--color-paper-deep)] pt-2 text-sm">
                    {report.comments.map((comment) => (
                      <p key={comment.id}>
                        <span className="font-medium">{comment.user}: </span>
                        {comment.comment}
                      </p>
                    ))}
                  </div>
                )}
              </article>
            ))
          )}
        </section>
      </div>
    </div>
  )
}
