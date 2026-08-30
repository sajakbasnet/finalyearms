import { useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  commentOnProposal,
  extractError,
  fetchTeacherProposal,
  replyToComment,
  reviewProposal,
  type TeacherProposalDetail,
} from '../../api/teacher'

const sections = [
  ['abstract', 'Abstract'],
  ['background', 'Background'],
  ['problem_statement', 'Problem statement'],
  ['objectives', 'Objectives'],
  ['scope', 'Scope'],
  ['methodology', 'Methodology'],
  ['literature_review', 'Literature review'],
  ['timeline', 'Timeline'],
  ['expected_outcome', 'Expected outcome'],
  ['technologies', 'Technologies'],
  ['references', 'References'],
] as const

export function TeacherProposalDetailPage() {
  const { id } = useParams()
  const proposalId = Number(id)
  const [proposal, setProposal] = useState<TeacherProposalDetail | null>(null)
  const [comment, setComment] = useState('')
  const [section, setSection] = useState('')
  const [reviewComment, setReviewComment] = useState('')
  const [replyDrafts, setReplyDrafts] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)

  async function load() {
    const data = await fetchTeacherProposal(proposalId)
    setProposal(data)
  }

  useEffect(() => {
    if (!proposalId) {
      return
    }
    void load().catch((err) => setError(extractError(err, 'Could not load proposal.')))
  }, [proposalId])

  async function handleReview(action: 'approve' | 'reject' | 'request_revision') {
    setIsSaving(true)
    setError(null)
    setSuccess(null)
    try {
      const updated = await reviewProposal(proposalId, {
        action,
        comment: reviewComment || undefined,
        section: section || undefined,
      })
      setProposal(updated)
      setReviewComment('')
      setSuccess(`Proposal ${action.replace('_', ' ')} saved.`)
    } catch (err) {
      setError(extractError(err, 'Could not save review.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleComment(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    try {
      await commentOnProposal(proposalId, {
        comment,
        section: section || undefined,
      })
      setComment('')
      await load()
      setSuccess('Comment added.')
    } catch (err) {
      setError(extractError(err, 'Could not add comment.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleReply(commentId: number) {
    const reply = replyDrafts[commentId]?.trim()
    if (!reply) {
      return
    }
    setIsSaving(true)
    setError(null)
    try {
      await replyToComment(commentId, reply)
      setReplyDrafts((prev) => ({ ...prev, [commentId]: '' }))
      await load()
      setSuccess('Reply posted.')
    } catch (err) {
      setError(extractError(err, 'Could not post reply.'))
    } finally {
      setIsSaving(false)
    }
  }

  if (!proposal) {
    return <p className="text-[var(--color-ink-muted)]">{error ?? 'Loading proposal…'}</p>
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/teacher/proposals" className="text-sm text-[var(--color-sea)] hover:underline">
            ← Back to proposals
          </Link>
          <h1 className="mt-2 font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            {proposal.title}
          </h1>
          <p className="mt-2 text-[var(--color-ink-muted)]">
            {proposal.student.name} · {proposal.student.registration_number} · v{proposal.version_number}
          </p>
        </div>
        <div className="rounded-lg bg-[var(--color-sea-soft)] px-3 py-2 text-sm font-semibold text-[var(--color-sea-deep)]">
          {proposal.status_label}
        </div>
      </div>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <section className="space-y-4 rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
          {sections.map(([key, label]) => {
            const value = proposal[key as keyof TeacherProposalDetail]
            if (typeof value !== 'string' || !value) {
              return null
            }
            return (
              <div key={key}>
                <h2 className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">{label}</h2>
                <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed">{value}</p>
              </div>
            )
          })}
          <Link
            to={`/teacher/projects/${proposal.project_id}`}
            className="inline-flex text-sm font-medium text-[var(--color-sea)] hover:underline"
          >
            Open project workspace →
          </Link>
        </section>

        <div className="space-y-4">
          <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
            <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
              Decision
            </h2>
            <textarea
              value={reviewComment}
              onChange={(e) => setReviewComment(e.target.value)}
              placeholder="Optional review note"
              className="mt-3 min-h-24 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5 text-sm"
            />
            <div className="mt-3 grid gap-2">
              <button
                type="button"
                disabled={isSaving}
                onClick={() => void handleReview('approve')}
                className="rounded-lg bg-[var(--color-success)] px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
              >
                Approve proposal
              </button>
              <button
                type="button"
                disabled={isSaving}
                onClick={() => void handleReview('request_revision')}
                className="rounded-lg bg-[var(--color-amber)] px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
              >
                Request revision
              </button>
              <button
                type="button"
                disabled={isSaving}
                onClick={() => void handleReview('reject')}
                className="rounded-lg bg-[var(--color-danger)] px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
              >
                Reject proposal
              </button>
            </div>
          </section>

          <form onSubmit={handleComment} className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
            <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
              Add comment
            </h2>
            <select
              value={section}
              onChange={(e) => setSection(e.target.value)}
              className="mt-3 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5 text-sm"
            >
              <option value="">Entire proposal</option>
              {sections.map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
            <textarea
              required
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              className="mt-3 min-h-24 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5 text-sm"
              placeholder="Write feedback for the student"
            />
            <button
              type="submit"
              disabled={isSaving}
              className="mt-3 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
            >
              Post comment
            </button>
          </form>

          <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
            <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
              Conversation
            </h2>
            <div className="mt-4 space-y-4">
              {proposal.comments.length === 0 ? (
                <p className="text-sm text-[var(--color-ink-muted)]">No comments yet.</p>
              ) : (
                proposal.comments.map((item) => (
                  <div key={item.id} className="rounded-lg border border-[var(--color-paper-deep)] p-3">
                    <p className="text-xs uppercase tracking-[0.12em] text-[var(--color-ink-muted)]">
                      {item.user.name} · {item.action ?? 'comment'}
                      {item.section ? ` · ${item.section}` : ''}
                    </p>
                    <p className="mt-1 text-sm">{item.comment}</p>
                    <div className="mt-3 space-y-2 border-t border-[var(--color-paper-deep)] pt-3">
                      {item.replies.map((reply) => (
                        <div key={reply.id} className="text-sm">
                          <span className="font-medium">{reply.user.name}: </span>
                          {reply.reply}
                        </div>
                      ))}
                      <div className="flex gap-2">
                        <input
                          value={replyDrafts[item.id] ?? ''}
                          onChange={(e) =>
                            setReplyDrafts((prev) => ({ ...prev, [item.id]: e.target.value }))
                          }
                          placeholder="Reply to student…"
                          className="flex-1 rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                        />
                        <button
                          type="button"
                          disabled={isSaving}
                          onClick={() => void handleReply(item.id)}
                          className="rounded-lg border border-[var(--color-sea)] px-3 py-2 text-sm text-[var(--color-sea)]"
                        >
                          Reply
                        </button>
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}
