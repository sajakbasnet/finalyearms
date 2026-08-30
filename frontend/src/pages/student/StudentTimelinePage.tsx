import { useEffect, useState } from 'react'
import { StatusChip } from '../../components/StatusChip'
import { extractError, fetchTimeline, type TimelineItem } from '../../api/student'

export function StudentTimelinePage() {
  const [items, setItems] = useState<TimelineItem[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void fetchTimeline()
      .then(setItems)
      .catch((err) => setError(extractError(err, 'Could not load timeline.')))
  }, [])

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Project timeline
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Track milestone due dates and completion status for your FYP.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}

      {items.length === 0 ? (
        <p className="text-[var(--color-ink-muted)]">
          No timeline yet. Milestones appear after you submit a proposal.
        </p>
      ) : (
        <ol className="relative space-y-4 border-l border-[var(--color-paper-deep)] pl-6">
          {items.map((item) => (
            <li key={item.id} className="relative">
              <span className="absolute -left-[1.55rem] top-1.5 h-3 w-3 rounded-full bg-[var(--color-sea)]" />
              <div className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h2 className="font-semibold">{item.title}</h2>
                  <StatusChip status={item.status} label={item.status_label} />
                </div>
                <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
                  Due {item.due_date ?? '—'}
                </p>
                {item.remarks && <p className="mt-2 text-sm">{item.remarks}</p>}
              </div>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}
