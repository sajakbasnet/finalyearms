type Tone = 'success' | 'danger' | 'warning' | 'neutral'

/**
 * Status vocabularies from proposals (approved/rejected/…) and milestones
 * (completed/overdue/…) both land here. Anything unmapped renders neutral.
 */
const toneByStatus: Record<string, Tone> = {
  // Proposals
  approved: 'success',
  rejected: 'danger',
  cancelled: 'danger',
  revision_requested: 'warning',
  // Milestones / timeline
  completed: 'success',
  overdue: 'danger',
  in_progress: 'warning',
}

const toneClass: Record<Tone, string> = {
  success: 'bg-[var(--tint-success)] text-[var(--color-success)]',
  danger: 'bg-[var(--tint-danger)] text-[var(--color-danger)]',
  warning: 'bg-[var(--tint-amber)] text-[var(--color-amber)]',
  neutral: 'bg-[var(--color-sea-soft)] text-[var(--color-sea-deep)]',
}

function statusTone(status: string): Tone {
  return toneByStatus[status] ?? 'neutral'
}

export function StatusChip({ status, label }: { status: string; label: string }) {
  return (
    <span
      className={`inline-flex rounded-md px-2 py-1 text-xs font-semibold ${toneClass[statusTone(status)]}`}
    >
      {label}
    </span>
  )
}
