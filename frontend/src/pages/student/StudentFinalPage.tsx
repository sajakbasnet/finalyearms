import { useEffect, useState, type FormEvent } from 'react'
import {
  extractError,
  fetchWorkspace,
  uploadFinal,
  type StudentWorkspace,
} from '../../api/student'

export function StudentFinalPage() {
  const [workspace, setWorkspace] = useState<StudentWorkspace | null>(null)
  const [thesis, setThesis] = useState<File | null>(null)
  const [presentation, setPresentation] = useState<File | null>(null)
  const [github, setGithub] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)

  async function load() {
    const data = await fetchWorkspace()
    setWorkspace(data)
    setGithub(data?.final_submission?.github_repository ?? '')
  }

  useEffect(() => {
    void load().catch((err) => setError(extractError(err, 'Could not load final submission.')))
  }, [])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)

    const body = new FormData()
    if (thesis) {
      body.append('thesis', thesis)
    }
    if (presentation) {
      body.append('presentation', presentation)
    }
    if (github) {
      body.append('github_repository', github)
    }

    try {
      await uploadFinal(body)
      setSuccess('Final files uploaded.')
      setThesis(null)
      setPresentation(null)
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not upload final files.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Final submission
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Upload your final thesis/report and presentation slides.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 md:grid-cols-2">
        <form onSubmit={handleSubmit} className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <label className="block space-y-1 text-sm">
            <span className="text-[var(--color-ink-muted)]">Final report / thesis (PDF, DOC)</span>
            <input
              type="file"
              accept=".pdf,.doc,.docx"
              onChange={(e) => setThesis(e.target.files?.[0] ?? null)}
              className="w-full"
            />
          </label>
          <label className="mt-4 block space-y-1 text-sm">
            <span className="text-[var(--color-ink-muted)]">Presentation (PDF, PPT)</span>
            <input
              type="file"
              accept=".pdf,.ppt,.pptx"
              onChange={(e) => setPresentation(e.target.files?.[0] ?? null)}
              className="w-full"
            />
          </label>
          <label className="mt-4 block space-y-1 text-sm">
            <span className="text-[var(--color-ink-muted)]">GitHub repository</span>
            <input
              type="url"
              value={github}
              onChange={(e) => setGithub(e.target.value)}
              placeholder="https://github.com/..."
              className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
          </label>
          <button
            type="submit"
            disabled={isSaving}
            className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
          >
            {isSaving ? 'Uploading…' : 'Upload files'}
          </button>
        </form>

        <section className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            Current files
          </h2>
          <ul className="mt-4 space-y-2 text-sm">
            <li>Thesis: {workspace?.final_submission?.has_thesis ? 'Uploaded' : 'Not uploaded'}</li>
            <li>Presentation: {workspace?.final_submission?.has_presentation ? 'Uploaded' : 'Not uploaded'}</li>
            <li>
              GitHub:{' '}
              {workspace?.final_submission?.github_repository ?? 'Not set'}
            </li>
            <li>
              Last update:{' '}
              {workspace?.final_submission?.submitted_at
                ? new Date(workspace.final_submission.submitted_at).toLocaleString()
                : '—'}
            </li>
          </ul>
        </section>
      </div>
    </div>
  )
}
