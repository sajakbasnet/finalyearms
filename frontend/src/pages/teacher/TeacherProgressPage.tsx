import { useEffect, useState } from 'react'
import {
  extractError,
  fetchProgressReports,
  reviewProgressReport,
  type ProgressReportRow,
} from '../../api/teacher'

export function TeacherProgressPage() {
  const [rows, setRows] = useState<ProgressReportRow[]>([])
  const [notes, setNotes] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)

  async function load() {
    setRows(await fetchProgressReports())
  }

  useEffect(() => {
    void load()
      .catch((err) => setError(extractError(err, 'Could not load progress reports.')))
      .finally(() => setIsLoading(false))
  }, [])

  async function handleReview(id: number, action: 'approve' | 'reject') {
    setIsSaving(true)
    setError(null)
    setSuccess(null)
    try {
      await reviewProgressReport(id, {
        action,
        comment: notes[id] || undefined,
      })
      await load()
      setSuccess(`Progress report ${action}d.`)
    } catch (err) {
      setError(extractError(err, 'Could not review progress report.'))
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
          Review and approve student progress submissions.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading…</p>
      ) : (
        <div className="space-y-4">
          {rows.length === 0 ? (
            <p className="text-[var(--color-ink-muted)]">No progress reports yet.</p>
          ) : (
            rows.map((row) => (
              <article key={row.id} className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 className="text-xl font-semibold">{row.title}</h2>
                    <p className="text-sm text-[var(--color-ink-muted)]">
                      {row.student.name} · {row.project.title} · {row.percentage_completed}%
                    </p>
                  </div>
                  <span className="rounded-md bg-[var(--color-sea-soft)] px-2 py-1 text-xs font-semibold uppercase text-[var(--color-sea-deep)]">
                    {row.status}
                  </span>
                </div>
                <p className="mt-3 text-sm leading-relaxed">{row.description}</p>
                <div className="mt-2 flex flex-wrap gap-4 text-sm text-[var(--color-sea)]">
                  {row.demo_link && (
                    <a href={row.demo_link} target="_blank" rel="noreferrer">Demo</a>
                  )}
                  {row.git_repository && (
                    <a href={row.git_repository} target="_blank" rel="noreferrer">Repository</a>
                  )}
                </div>

                {row.comments.length > 0 && (
                  <div className="mt-3 space-y-1 border-t border-[var(--color-paper-deep)] pt-3 text-sm">
                    {row.comments.map((comment) => (
                      <p key={comment.id}>
                        <span className="font-medium">{comment.user}: </span>
                        {comment.comment}
                      </p>
                    ))}
                  </div>
                )}

                {row.status === 'submitted' && (
                  <div className="mt-4 space-y-2">
                    <textarea
                      value={notes[row.id] ?? ''}
                      onChange={(e) => setNotes((prev) => ({ ...prev, [row.id]: e.target.value }))}
                      placeholder="Optional review note"
                      className="min-h-20 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                    />
                    <div className="flex gap-2">
                      <button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleReview(row.id, 'approve')}
                        className="rounded-lg bg-[var(--color-success)] px-3 py-2 text-sm font-semibold text-white disabled:opacity-70"
                      >
                        Approve
                      </button>
                      <button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleReview(row.id, 'reject')}
                        className="rounded-lg bg-[var(--color-danger)] px-3 py-2 text-sm font-semibold text-white disabled:opacity-70"
                      >
                        Reject
                      </button>
                    </div>
                  </div>
                )}
              </article>
            ))
          )}
        </div>
      )}
    </div>
  )
}
