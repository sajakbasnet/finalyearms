import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { isAxiosError } from 'axios'
import { resetPassword } from '../api/auth'
import { BrandMark } from '../components/BrandMark'

/**
 * Completes a reset from the emailed link.
 *
 * The token and email arrive as query parameters — this is the page the
 * backend's reset email points at.
 */
export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()

  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const linkIsUsable = token !== '' && email !== ''

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    setError(null)

    try {
      await resetPassword({
        token,
        email,
        password,
        password_confirmation: confirmation,
      })

      navigate('/login', {
        replace: true,
        state: { notice: 'Password updated. Sign in with your new password.' },
      })
    } catch (err) {
      setError(
        isAxiosError(err)
          ? (err.response?.data?.errors?.email?.[0]
            ?? err.response?.data?.errors?.password?.[0]
            ?? err.response?.data?.message
            ?? 'Could not reset the password.')
          : 'Could not reset the password.',
      )
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="relative min-h-screen bg-[var(--color-ink)] text-[var(--color-paper)]">
      <div className="relative mx-auto grid min-h-screen max-w-md items-center px-6 py-12">
        <section>
          <BrandMark variant="sidebar" className="mb-6" />

          <div className="rounded-2xl border border-[var(--color-paper)]/10 bg-[var(--surface-card)] p-8 text-[var(--color-ink)]">
            <h1 className="font-[family-name:var(--font-display)] text-3xl text-[var(--color-sea-deep)]">
              Set a new password
            </h1>

            {!linkIsUsable ? (
              <>
                {/* Reached without a token — almost always a truncated or
                    already-used link. */}
                <p className="mt-3 text-[var(--color-ink-muted)]">
                  This reset link is incomplete. Request a new one and use the
                  most recent email.
                </p>
                <Link
                  to="/forgot-password"
                  className="mt-6 inline-block rounded-lg bg-[var(--color-sea)] px-4 py-3 font-semibold text-[var(--color-on-brand)]"
                >
                  Request a new link
                </Link>
              </>
            ) : (
              <>
                <p className="mt-3 text-[var(--color-ink-muted)]">
                  Resetting signs you out everywhere else.
                </p>

                <form className="mt-6 space-y-5" onSubmit={handleSubmit}>
                  <label className="block space-y-2">
                    <span className="text-sm font-medium text-[var(--color-ink-muted)]">
                      Account
                    </span>
                    <input
                      type="email"
                      value={email}
                      readOnly
                      className="w-full rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-paper)] px-4 py-3 text-[var(--color-ink-muted)]"
                    />
                  </label>

                  <label className="block space-y-2">
                    <span className="text-sm font-medium text-[var(--color-ink-muted)]">
                      New password
                    </span>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      autoFocus
                      autoComplete="new-password"
                      className="w-full rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-4 py-3 outline-none transition focus:border-[var(--color-sea)] focus:ring-2 focus:ring-[var(--color-sea-soft)]"
                    />
                  </label>

                  <label className="block space-y-2">
                    <span className="text-sm font-medium text-[var(--color-ink-muted)]">
                      Confirm new password
                    </span>
                    <input
                      type="password"
                      value={confirmation}
                      onChange={(e) => setConfirmation(e.target.value)}
                      required
                      autoComplete="new-password"
                      className="w-full rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-4 py-3 outline-none transition focus:border-[var(--color-sea)] focus:ring-2 focus:ring-[var(--color-sea-soft)]"
                    />
                  </label>

                  {error && (
                    <p className="rounded-lg bg-[var(--tint-danger)] px-3 py-2 text-sm text-[var(--color-danger)]">
                      {error}
                    </p>
                  )}

                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="w-full rounded-lg bg-[var(--color-sea)] px-4 py-3 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-70"
                  >
                    {isSubmitting ? 'Saving…' : 'Set new password'}
                  </button>
                </form>
              </>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
