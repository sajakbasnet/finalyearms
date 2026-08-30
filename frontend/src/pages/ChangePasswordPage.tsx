import { useState, type FormEvent } from 'react'
import { isAxiosError } from 'axios'
import { changePassword } from '../api/auth'
import { useAuth } from '../contexts/AuthContext'

/**
 * Changes your own password.
 *
 * The API revokes every token on success, so this signs you out on purpose
 * rather than leaving a session running on a credential that no longer exists.
 */
export function ChangePasswordPage() {
  const { logout } = useAuth()
  const [current, setCurrent] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    setError(null)

    try {
      await changePassword({
        current_password: current,
        password,
        password_confirmation: confirmation,
      })

      // Every token is gone; send them back to sign in with the new one.
      await logout()
    } catch (err) {
      setError(
        isAxiosError(err)
          ? (err.response?.data?.errors?.current_password?.[0]
            ?? err.response?.data?.errors?.password?.[0]
            ?? err.response?.data?.message
            ?? 'Could not change the password.')
          : 'Could not change the password.',
      )
      setIsSubmitting(false)
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Change password
        </h1>
        <p className="mt-2 max-w-xl text-[var(--color-ink-muted)]">
          Changing your password signs you out of every device, including this
          one. You will sign in again with the new password.
        </p>
      </header>

      <form
        onSubmit={handleSubmit}
        className="grid max-w-md gap-4 rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-6"
      >
        <label className="grid gap-1 text-sm">
          <span className="font-medium">Current password</span>
          <input
            type="password"
            value={current}
            onChange={(e) => setCurrent(e.target.value)}
            required
            autoComplete="current-password"
            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
          />
        </label>

        <label className="grid gap-1 text-sm">
          <span className="font-medium">New password</span>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="new-password"
            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
          />
        </label>

        <label className="grid gap-1 text-sm">
          <span className="font-medium">Confirm new password</span>
          <input
            type="password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            required
            autoComplete="new-password"
            className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
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
          className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-70"
        >
          {isSubmitting ? 'Saving…' : 'Change password and sign out'}
        </button>
      </form>
    </div>
  )
}
