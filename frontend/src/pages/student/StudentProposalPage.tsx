import { useEffect, useState, type FormEvent } from 'react'
import {
  extractError,
  fetchWorkspace,
  replyToComment,
  saveProposal,
  submitProposal,
  type ProposalData,
  type StudentWorkspace,
} from '../../api/student'

const emptyForm = {
  title: '',
  abstract: '',
  background: '',
  problem_statement: '',
  objectives: '',
  scope: '',
  methodology: '',
  literature_review: '',
  timeline: '',
  expected_outcome: '',
  technologies: '',
  references: '',
  domain: '',
}

export function StudentProposalPage() {
  const [workspace, setWorkspace] = useState<StudentWorkspace | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [pdf, setPdf] = useState<File | null>(null)
  const [replyDrafts, setReplyDrafts] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [loaded, setLoaded] = useState(false)

  function applyProposal(proposal: ProposalData | null) {
    if (!proposal) {
      setForm(emptyForm)
      return
    }
    setForm({
      title: proposal.title ?? '',
      abstract: proposal.abstract ?? '',
      background: proposal.background ?? '',
      problem_statement: proposal.problem_statement ?? '',
      objectives: proposal.objectives ?? '',
      scope: proposal.scope ?? '',
      methodology: proposal.methodology ?? '',
      literature_review: proposal.literature_review ?? '',
      timeline: proposal.timeline ?? '',
      expected_outcome: proposal.expected_outcome ?? '',
      technologies: proposal.technologies ?? '',
      references: proposal.references ?? '',
      domain: '',
    })
  }

  async function load() {
    const data = await fetchWorkspace()
    setWorkspace(data)
    applyProposal(data?.latest_proposal ?? null)
  }

  useEffect(() => {
    void load()
      .catch((err) => setError(extractError(err, 'Could not load proposal.')))
      .finally(() => setLoaded(true))
  }, [])

  const proposal = workspace?.latest_proposal ?? null
  const canEdit = !proposal || proposal.can_edit

  async function handleSave(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)

    const body = new FormData()
    Object.entries(form).forEach(([key, value]) => {
      if (value) {
        body.append(key, value)
      }
    })
    if (pdf) {
      body.append('pdf', pdf)
    }

    try {
      const saved = await saveProposal(body)
      setSuccess(`Draft v${saved.version_number} saved.`)
      await load()
      setPdf(null)
    } catch (err) {
      setError(extractError(err, 'Could not save proposal.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleSubmit() {
    setIsSaving(true)
    setError(null)
    setSuccess(null)
    try {
      const submitted = await submitProposal(proposal?.id)
      setSuccess(`Proposal v${submitted.version_number} submitted (${submitted.status_label}).`)
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not submit proposal.'))
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
      setSuccess('Reply sent to supervisor.')
    } catch (err) {
      setError(extractError(err, 'Could not send reply.'))
    } finally {
      setIsSaving(false)
    }
  }

  if (!loaded) {
    return <p className="text-[var(--color-ink-muted)]">Loading proposal…</p>
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            My proposal
          </h1>
          <p className="mt-2 text-[var(--color-ink-muted)]">
            Save drafts, upload PDF revisions, submit for review, and reply to supervisor comments.
          </p>
        </div>
        {proposal && (
          <span className="rounded-lg bg-[var(--color-sea-soft)] px-3 py-2 text-sm font-semibold text-[var(--color-sea-deep)]">
            {proposal.status_label} · v{proposal.version_number}
          </span>
        )}
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <form onSubmit={handleSave} className="space-y-3 rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
          <fieldset disabled={!canEdit || isSaving} className="space-y-3 disabled:opacity-70">
            {(
              [
                ['title', 'Project title', true],
                ['domain', 'Domain', false],
                ['abstract', 'Abstract', false],
                ['background', 'Background', false],
                ['problem_statement', 'Problem statement', false],
                ['objectives', 'Objectives', false],
                ['scope', 'Scope', false],
                ['methodology', 'Methodology', false],
                ['literature_review', 'Literature review', false],
                ['timeline', 'Timeline', false],
                ['expected_outcome', 'Expected outcome', false],
                ['technologies', 'Technologies', false],
                ['references', 'References', false],
              ] as const
            ).map(([key, label, required]) => (
              <label key={key} className="block space-y-1 text-sm">
                <span className="text-[var(--color-ink-muted)]">{label}</span>
                {key === 'title' || key === 'domain' ? (
                  <input
                    required={required}
                    value={form[key]}
                    onChange={(e) => setForm((prev) => ({ ...prev, [key]: e.target.value }))}
                    className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                  />
                ) : (
                  <textarea
                    required={required}
                    value={form[key]}
                    onChange={(e) => setForm((prev) => ({ ...prev, [key]: e.target.value }))}
                    className="min-h-20 w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                  />
                )}
              </label>
            ))}

            <label className="block space-y-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Proposal PDF</span>
              <input
                type="file"
                accept="application/pdf"
                onChange={(e) => setPdf(e.target.files?.[0] ?? null)}
                className="w-full text-sm"
              />
              {proposal?.has_pdf && (
                <span className="text-xs text-[var(--color-success)]">A PDF is already attached to this version.</span>
              )}
            </label>
          </fieldset>

          <div className="flex flex-wrap gap-2 pt-2">
            <button
              type="submit"
              disabled={!canEdit || isSaving}
              className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
            >
              {proposal?.status === 'revision_requested' ? 'Save revision draft' : 'Save draft'}
            </button>
            <button
              type="button"
              disabled={isSaving || !proposal || !['draft', 'revision_requested'].includes(proposal.status)}
              onClick={() => void handleSubmit()}
              className="rounded-lg bg-[var(--color-success)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
            >
              Submit for review
            </button>
          </div>
          {!canEdit && (
            <p className="text-sm text-[var(--color-ink-muted)]">
              This proposal is locked for editing until your supervisor requests a revision.
            </p>
          )}
        </form>

        <div className="space-y-4">
          <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
            <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
              Version history
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              {(workspace?.versions ?? []).length === 0 ? (
                <li className="text-[var(--color-ink-muted)]">No versions yet.</li>
              ) : (
                workspace?.versions.map((version) => (
                  <li key={version.id} className="flex justify-between gap-3 border-b border-[var(--color-paper-deep)] pb-2">
                    <span>v{version.version_number}</span>
                    <span>{version.status_label}</span>
                  </li>
                ))
              )}
            </ul>
          </section>

          <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80 p-5">
            <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
              Supervisor comments
            </h2>
            <div className="mt-3 space-y-3">
              {(proposal?.comments ?? []).length === 0 ? (
                <p className="text-sm text-[var(--color-ink-muted)]">No comments yet.</p>
              ) : (
                proposal?.comments.map((comment) => (
                  <div key={comment.id} className="rounded-lg border border-[var(--color-paper-deep)] p-3">
                    <p className="text-xs uppercase tracking-[0.12em] text-[var(--color-ink-muted)]">
                      {comment.user.name}
                      {comment.section ? ` · ${comment.section}` : ''}
                      {comment.action ? ` · ${comment.action}` : ''}
                    </p>
                    <p className="mt-1 text-sm">{comment.comment}</p>
                    <div className="mt-2 space-y-1">
                      {comment.replies.map((reply) => (
                        <p key={reply.id} className="text-sm">
                          <span className="font-medium">{reply.user.name}: </span>
                          {reply.reply}
                        </p>
                      ))}
                    </div>
                    <div className="mt-2 flex gap-2">
                      <input
                        value={replyDrafts[comment.id] ?? ''}
                        onChange={(e) =>
                          setReplyDrafts((prev) => ({ ...prev, [comment.id]: e.target.value }))
                        }
                        placeholder="Reply to supervisor…"
                        className="flex-1 rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                      />
                      <button
                        type="button"
                        disabled={isSaving}
                        onClick={() => void handleReply(comment.id)}
                        className="rounded-lg border border-[var(--color-sea)] px-3 py-2 text-sm text-[var(--color-sea)]"
                      >
                        Reply
                      </button>
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
