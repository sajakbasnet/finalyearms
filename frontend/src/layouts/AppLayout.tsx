import { useState, type ReactNode } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import type { RoleSlug } from '../types/auth'

interface NavItem {
  to: string
  label: string
  end?: boolean
}

const roleHomeLabel: Record<string, string> = {
  admin: 'Admin Console',
  teacher: 'Supervisor Desk',
  student: 'Student Workspace',
}

const navByRole: Record<RoleSlug, NavItem[]> = {
  admin: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/admin/departments', label: 'Departments' },
    { to: '/admin/teachers', label: 'Teachers' },
    { to: '/admin/students', label: 'Students' },
    { to: '/admin/assignments', label: 'Assign Supervisors' },
    { to: '/admin/proposals', label: 'Proposals' },
    { to: '/admin/sessions', label: 'Sessions' },
  ],
  teacher: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/teacher/students', label: 'Assigned Students' },
    { to: '/teacher/proposals', label: 'Review Proposals' },
    { to: '/teacher/progress', label: 'Progress Reports' },
  ],
  student: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/student/supervisor', label: 'My Supervisor' },
    { to: '/student/proposal', label: 'My Proposal' },
    { to: '/student/progress', label: 'Progress Reports' },
    { to: '/student/final', label: 'Final Submission' },
    { to: '/student/timeline', label: 'Project Timeline' },
    { to: '/student/notifications', label: 'Notifications' },
  ],
}

function navClass(isActive: boolean): string {
  return [
    'block rounded-lg px-3 py-2.5 text-sm transition',
    isActive
      ? 'bg-[var(--color-sea)] font-semibold text-white'
      : 'text-white/75 hover:bg-white/10 hover:text-white',
  ].join(' ')
}

export function AppLayout({ children }: { children?: ReactNode }) {
  const { user, logout } = useAuth()
  const roleSlug = (user?.role?.slug ?? 'student') as RoleSlug
  const items = navByRole[roleSlug] ?? navByRole.student
  const [mobileOpen, setMobileOpen] = useState(false)

  return (
    <div className="min-h-screen bg-[var(--color-paper)] lg:flex">
      {mobileOpen && (
        <button
          type="button"
          aria-label="Close menu"
          className="fixed inset-0 z-30 bg-black/40 lg:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      <aside
        className={[
          'fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-[var(--color-ink)] text-white transition-transform lg:static lg:translate-x-0',
          mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ].join(' ')}
      >
        <div className="border-b border-white/10 px-5 py-5">
          <p className="font-[family-name:var(--font-display)] text-2xl tracking-tight">FYP Portal</p>
          <p className="mt-1 text-sm text-white/60">{roleHomeLabel[roleSlug]}</p>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              onClick={() => setMobileOpen(false)}
              className={({ isActive }) => navClass(isActive)}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-white/10 px-5 py-4">
          <p className="truncate text-sm font-semibold">{user?.name}</p>
          <p className="text-xs uppercase tracking-[0.14em] text-white/50">{user?.role?.name}</p>
          <button
            type="button"
            onClick={() => void logout()}
            className="mt-3 w-full rounded-lg border border-white/20 px-3 py-2 text-sm hover:bg-white/10"
          >
            Logout
          </button>
        </div>
      </aside>

      <div className="min-w-0 flex-1">
        <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-[var(--color-paper-deep)] bg-[rgba(243,239,230,0.92)] px-4 py-3 backdrop-blur lg:px-8">
          <button
            type="button"
            className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2 text-sm lg:hidden"
            onClick={() => setMobileOpen(true)}
          >
            Menu
          </button>
          <p className="text-sm text-[var(--color-ink-muted)]">
            Submit proposals, track progress, and stay in sync with your supervisor.
          </p>
        </header>
        <main className="px-4 py-6 lg:px-8 lg:py-8">{children ?? <Outlet />}</main>
      </div>
    </div>
  )
}
