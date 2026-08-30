import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { isAxiosError } from 'axios'
import { forgotPassword } from '../api/auth'
import { useBranding } from '../branding/BrandingContext'
import { BrandMark } from '../components/BrandMark'

/**
 * Requests a reset link.
 *
 * The response is deliberately the same whether or not the address is
 * registered, so this page must not imply otherwise — it confirms that a link
 * was sent *if* the account exists, and nothing more.
 */
export function ForgotPasswordPage() {
  const { branding } = useBranding()
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    setError(null)

    try {
      await forgotPassword(email)
      setSent(true)
    } catch (err) {
      setError(
        isAxiosError(err) && err.response?.status === 429
          ? 'Too many attempts. Wait a minute and try again.'
          : 'Could not send the link. Check the address and try again.',
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
              Reset your password
            </h1>

            {sent ? (
              <>
                <p className="mt-3 text-[var(--color-ink-muted)]">
                  If <strong>{email}</strong> belongs to an account at{' '}
                  {branding.institution_name}, a reset link is on its way. The link
                  expires, so use it soon.
                </p>
                <Link
                  to="/login"
                  className="mt-6 inline-block rounded-lg bg-[var(--color-sea)] px-4 py-3 font-semibold text-[var(--color-on-brand)]"
                >
                  Back to sign in
                </Link>
              </>
            ) : (
              <>
                <p className="mt-3 text-[var(--color-ink-muted)]">
                  Enter your email and we will send you a link to set a new one.
                </p>

                <form className="mt-6 space-y-5" onSubmit={handleSubmit}>
                  <label className="block space-y-2">
                    <span className="text-sm font-medium text-[var(--color-ink-muted)]">Email</span>
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      required
                      autoFocus
                      autoComplete="username"
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
                    {isSubmitting ? 'Sending…' : 'Send reset link'}
                  </button>

                  <Link
                    to="/login"
                    className="block text-center text-sm text-[var(--color-sea)] hover:underline"
                  >
                    Back to sign in
                  </Link>
                </form>
              </>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
