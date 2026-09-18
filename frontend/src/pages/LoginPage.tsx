import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { isAxiosError } from 'axios'
import { useBranding } from '../branding/BrandingContext'
import { BrandMark } from '../components/BrandMark'
import { useAuth } from '../contexts/AuthContext'

/**
 * One shortcut per assignable role, so every portal is reachable while
 * developing. These went stale once `admin` and `teacher` were renamed, which
 * left the Coordinator portal with no way in from this page.
 */
const demoAccounts = [
  { role: 'Institution Admin', email: 'admin@fyp.local' },
  { role: 'Coordinator', email: 'coordinator@fyp.local' },
  { role: 'Supervisor', email: 'teacher@fyp.local' },
  { role: 'Student', email: 'student@fyp.local' },
]

/**
 * Demo credentials are a local-development affordance — never render them on a
 * tenant deployment.
 */
const showDemoAccounts = import.meta.env.DEV

export function LoginPage() {
  const { login } = useAuth()
  const { branding } = useBranding()
  const navigate = useNavigate()
  const [email, setEmail] = useState(showDemoAccounts ? 'student@fyp.local' : '')
  const [password, setPassword] = useState(showDemoAccounts ? 'password' : '')
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setIsSubmitting(true)

    try {
      await login(email, password)
      navigate('/dashboard', { replace: true })
    } catch (err) {
      if (isAxiosError(err)) {
        setError(
          err.response?.data?.message
            ?? err.response?.data?.errors?.email?.[0]
            ?? 'Unable to sign in. Check your credentials.',
        )
      } else {
        setError('Unable to sign in. Check your credentials.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="relative min-h-screen overflow-hidden bg-[var(--color-ink)] text-[var(--color-paper)]">
      <div
        className="absolute inset-0 opacity-90"
        style={{
          backgroundImage: `
            radial-gradient(circle at 12% 18%, color-mix(in srgb, var(--color-amber) 28%, transparent), transparent 34%),
            radial-gradient(circle at 88% 12%, color-mix(in srgb, var(--color-sea) 55%, transparent), transparent 40%),
            linear-gradient(160deg, var(--color-ink-deep) 0%, var(--color-ink) 48%, var(--color-sea-deep) 100%)
          `,
        }}
      />
      <div
        className="absolute inset-0 opacity-[0.18]"
        style={{
          backgroundImage:
            'linear-gradient(var(--surface-hairline) 1px, transparent 1px), linear-gradient(90deg, var(--surface-hairline) 1px, transparent 1px)',
          backgroundSize: '48px 48px',
        }}
      />

      <div className="relative mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-6 py-12 lg:grid-cols-[1.1fr_0.9fr]">
        <section className="animate-[fadeRise_700ms_ease-out]">
          <BrandMark variant="hero" className="mb-4" />
          <h1 className="max-w-xl text-2xl font-medium leading-snug text-[var(--color-paper)]/90 sm:text-3xl">
            {branding.tagline || 'Submit proposals, track progress, and stay in sync with your supervisor.'}
          </h1>
          <p className="mt-5 max-w-lg text-base leading-relaxed text-[var(--color-paper)]/70">
            Sign in to access your final year project workflow, supervision, and administration.
          </p>
        </section>

        <section className="animate-[fadeRise_900ms_ease-out] rounded-2xl border border-[var(--color-paper)]/10 bg-[var(--surface-card)] p-8 text-[var(--color-ink)] shadow-[0_24px_80px_rgba(0,0,0,0.35)]">
          <p className="text-sm uppercase tracking-[0.18em] text-[var(--color-ink-muted)]">
            Secure access
          </p>
          <h2 className="mt-2 font-[family-name:var(--font-display)] text-3xl text-[var(--color-sea-deep)]">
            Sign in
          </h2>

          <form className="mt-8 space-y-5" onSubmit={handleSubmit}>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-[var(--color-ink-muted)]">Email</span>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                autoComplete="username"
                className="w-full rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-4 py-3 outline-none transition focus:border-[var(--color-sea)] focus:ring-2 focus:ring-[var(--color-sea-soft)]"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-[var(--color-ink-muted)]">Password</span>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                autoComplete="current-password"
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
              className="w-full rounded-lg bg-[var(--color-sea)] px-4 py-3 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:cursor-not-allowed disabled:opacity-70"
            >
              {isSubmitting ? 'Signing in…' : 'Sign in'}
            </button>

            <Link
              to="/forgot-password"
              className="block text-center text-sm text-[var(--color-sea-deep)] hover:underline"
            >
              Forgot your password?
            </Link>
          </form>

          {showDemoAccounts && (
            <div className="mt-8 border-t border-[var(--color-paper-deep)] pt-6">
              <p className="text-xs uppercase tracking-[0.16em] text-[var(--color-ink-muted)]">
                Demo accounts · password
              </p>
              <ul className="mt-3 space-y-2">
                {demoAccounts.map((account) => (
                  <li key={account.email}>
                    <button
                      type="button"
                      onClick={() => {
                        setEmail(account.email)
                        setPassword('password')
                      }}
                      className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm transition hover:bg-[var(--color-sea-soft)]"
                    >
                      <span className="font-medium">{account.role}</span>
                      <span className="text-[var(--color-ink-muted)]">{account.email}</span>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </section>
      </div>

      <style>{`
        @keyframes fadeRise {
          from { opacity: 0; transform: translateY(16px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>
  )
}
