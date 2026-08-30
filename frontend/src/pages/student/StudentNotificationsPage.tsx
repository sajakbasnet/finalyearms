import { useEffect, useState } from 'react'
import {
  extractError,
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type NotificationItem,
} from '../../api/student'

export function StudentNotificationsPage() {
  const [items, setItems] = useState<NotificationItem[]>([])
  const [error, setError] = useState<string | null>(null)

  async function load() {
    setItems(await fetchNotifications())
  }

  useEffect(() => {
    void load().catch((err) => setError(extractError(err, 'Could not load notifications.')))
  }, [])

  async function handleRead(id: string) {
    await markNotificationRead(id)
    await load()
  }

  async function handleReadAll() {
    await markAllNotificationsRead()
    await load()
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            Notifications
          </h1>
          <p className="mt-2 text-[var(--color-ink-muted)]">
            Updates about proposals, revisions, and submissions.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void handleReadAll()}
          className="rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2 text-sm"
        >
          Mark all read
        </button>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}

      <div className="space-y-3">
        {items.length === 0 ? (
          <p className="text-[var(--color-ink-muted)]">No notifications yet.</p>
        ) : (
          items.map((item) => (
            <article
              key={item.id}
              className={`rounded-xl border border-[var(--color-paper-deep)] p-4 ${
                item.read_at ? 'bg-[var(--color-surface)]/60' : 'bg-[var(--color-sea-soft)]/40'
              }`}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="font-semibold">{item.data.title ?? 'Notification'}</h2>
                  <p className="mt-1 text-sm text-[var(--color-ink-muted)]">{item.data.message}</p>
                  <p className="mt-2 text-xs text-[var(--color-ink-muted)]">
                    {item.created_at ? new Date(item.created_at).toLocaleString() : ''}
                  </p>
                </div>
                {!item.read_at && (
                  <button
                    type="button"
                    onClick={() => void handleRead(item.id)}
                    className="text-sm text-[var(--color-sea)] hover:underline"
                  >
                    Mark read
                  </button>
                )}
              </div>
            </article>
          ))
        )}
      </div>
    </div>
  )
}
