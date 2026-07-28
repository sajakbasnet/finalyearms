import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import type { RoleSlug } from '../types/auth'

export function ProtectedRoute({ roles }: { roles?: RoleSlug[] }) {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="grid min-h-screen place-items-center bg-[var(--color-paper)] text-[var(--color-ink-muted)]">
        Loading portal…
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  if (roles && user.role && !roles.includes(user.role.slug)) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}

export function GuestRoute() {
  const { user, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="grid min-h-screen place-items-center bg-[var(--color-paper)] text-[var(--color-ink-muted)]">
        Loading portal…
      </div>
    )
  }

  if (user) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}
