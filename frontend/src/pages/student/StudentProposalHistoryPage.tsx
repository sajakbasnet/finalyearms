import { useEffect, useState } from 'react'
import api from '../../api/client'
import { extractError } from '../../api/admin'
import { StatusChip } from '../../components/StatusChip'

interface HistoryReply {
  id: number
  reply: string
  author: string | null
  created_at: string | null
}

interface HistoryComment {
  id: number
  section: string | null
  comment: string
  action: string | null
  author: string | null
  created_at: string | null
  replies: HistoryReply[]
}

interface ProposalVersion {
  id: number
  version_number: number
  title: string
  abstract: string | null
  status: string
  status_label: string
  submitted_at: string | null
  submitted_by: string | null
  pdf_path: string | null
  comments: HistoryComment[]
}

async function fetchHistory(): Promise<ProposalVersion[]> {
  const { data } = await api.get<{ data: ProposalVersion[] }>('/student/proposals/history')
  return data.data
}

/**
 * Every version of the proposal, with the review thread attached to each.
 *
 * Newest first, because the current state is what a student checks most often;
 * older versions are the record of how it got there.
 */
export function StudentProposalHistoryPage() {
  const [versions, setVersions] = useState<ProposalVersion[]>([])
  const [expanded, setExpanded] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void fetchHistory()
      .then((data) => {
        setVersions(data)
        // The newest version is the one most likely to be read.
        setExpanded(data[0]?.id ?? null)
      })
      .catch((err) => setError(extractError(err, 'Could not load the proposal history.')))
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Proposal history
        </h1>
        <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
          Every version you have submitted, and what your supervisor said about
          each one.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading history…</p>
      ) : versions.length === 0 ? (
        <p className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-4 py-10 text-center text-[var(--color-ink-muted)]">
          No proposal versions yet. Save a draft to get started.
        </p>
      ) : (
        <ol className="grid gap-3">
          {versions.map((version) => {
            const isOpen = expanded === version.id

            return (
              <li
                key={version.id}
                className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70"
              >
                <button
                  type="button"
                  onClick={() => setExpanded(isOpen ? null : version.id)}
                  className="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-4 text-left"
                >
                  <span className="min-w-0">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="font-[family-name:var(--font-display)] text-lg tabular-nums text-[var(--color-sea-deep)]">
                        v{version.version_number}
                      </span>
                      <span className="font-semibold">{version.title}</span>
                      <StatusChip status={version.status} label={version.status_label} />
                    </span>
                    <span className="mt-1 block text-sm text-[var(--color-ink-muted)]">
                      {version.submitted_at
                        ? `Submitted ${new Date(version.submitted_at).toLocaleDateString()}`
                        : 'Not submitted'}
                      {version.submitted_by && ` by ${version.submitted_by}`}
                      {version.comments.length > 0 &&
                        ` · ${version.comments.length} comment${version.comments.length === 1 ? '' : 's'}`}
                    </span>
                  </span>
                  <span aria-hidden className="text-[var(--color-ink-muted)]">
                    {isOpen ? '−' : '+'}
                  </span>
                </button>

                {isOpen && (
                  <div className="border-t border-[var(--color-paper-deep)] px-5 py-4">
                    {version.abstract && (
                      <p className="max-w-prose text-sm text-[var(--color-ink-muted)]">
                        {version.abstract}
                      </p>
                    )}

                    {version.comments.length === 0 ? (
                      <p className="mt-3 text-sm text-[var(--color-ink-muted)]">
                        No review comments on this version.
                      </p>
                    ) : (
                      <ul className="mt-4 grid gap-3">
                        {version.comments.map((comment) => (
                          <li
                            key={comment.id}
                            className="rounded-lg bg-[var(--color-paper)] px-4 py-3 text-sm"
                          >
                            <p className="font-medium">
                              {comment.author}
                              {comment.section && (
                                <span className="ml-2 text-[var(--color-ink-muted)]">
                                  on {comment.section}
                                </span>
                              )}
                            </p>
                            <p className="mt-1">{comment.comment}</p>

                            {comment.replies.length > 0 && (
                              <ul className="mt-3 grid gap-2 border-l-2 border-[var(--color-sea-soft)] pl-3">
                                {comment.replies.map((reply) => (
                                  <li key={reply.id}>
                                    <span className="font-medium">{reply.author}</span>
                                    <span className="block text-[var(--color-ink-muted)]">
                                      {reply.reply}
                                    </span>
                                  </li>
                                ))}
                              </ul>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}
              </li>
            )
          })}
        </ol>
      )}
    </div>
  )
}
