import { useState, type ReactNode } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { useBranding } from '../branding/BrandingContext'
import { BrandMark } from '../components/BrandMark'
import { useAuth } from '../contexts/AuthContext'
import type { RoleSlug } from '../types/auth'

interface NavItem {
  to: string
  label: string
  end?: boolean
}

const roleHomeLabel: Record<RoleSlug, string> = {
  institution_admin: 'Admin Console',
  coordinator: 'Coordinator Desk',
  supervisor: 'Supervisor Desk',
  student: 'Student Workspace',
  employer: 'Employer Portal',
}

/**
 * Navigation mirrors the API's permission split: Institution Admin owns
 * accounts and org structure, Coordinator owns templates, teams and
 * supervision overrides. Items a role cannot reach are not shown, and the API
 * rejects them regardless.
 */
const navByRole: Record<RoleSlug, NavItem[]> = {
  institution_admin: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/admin/departments', label: 'Departments' },
    { to: '/admin/teachers', label: 'Supervisors' },
    { to: '/admin/students', label: 'Students' },
    { to: '/admin/batches', label: 'Batches' },
    { to: '/admin/sessions', label: 'Academic Calendar' },
  ],
  coordinator: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/coordinator/project-types', label: 'Project Types' },
    { to: '/coordinator/templates', label: 'Activity Templates' },
    { to: '/admin/assignments', label: 'Assign Supervisors' },
    { to: '/admin/proposals', label: 'Proposal Oversight' },
  ],
  supervisor: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/teacher/requests', label: 'Supervision Requests' },
    { to: '/teacher/students', label: 'Assigned Students' },
    { to: '/teacher/proposals', label: 'Review Proposals' },
    { to: '/teacher/progress', label: 'Progress Reports' },
  ],
  student: [
    { to: '/dashboard', label: 'Dashboard', end: true },
    { to: '/student/team', label: 'My Team' },
    { to: '/student/find-supervisor', label: 'Find a Supervisor' },
    { to: '/student/supervisor', label: 'My Supervisor' },
    { to: '/student/proposal', label: 'My Proposal' },
    { to: '/student/proposal/history', label: 'Proposal History' },
    { to: '/student/progress', label: 'Progress Reports' },
    { to: '/student/final', label: 'Final Submission' },
    { to: '/student/timeline', label: 'Project Timeline' },
    { to: '/student/notifications', label: 'Notifications' },
  ],
  // Internship features are not built yet; the role exists so an employer can
  // sign in and see the portal rather than a broken shell.
  employer: [{ to: '/dashboard', label: 'Dashboard', end: true }],
}

function navClass(isActive: boolean): string {
  return [
    'block rounded-lg px-3 py-2.5 text-sm transition',
    isActive
      ? 'bg-[var(--color-sea)] font-semibold text-[var(--color-on-brand)]'
      : 'text-[var(--color-on-ink)]/75 hover:bg-[var(--color-on-ink)]/10 hover:text-[var(--color-on-ink)]',
  ].join(' ')
}

export function AppLayout({ children }: { children?: ReactNode }) {
  const { user, logout } = useAuth()
  const { branding } = useBranding()
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
          'fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-[var(--color-ink)] text-[var(--color-on-ink)] transition-transform lg:static lg:translate-x-0',
          mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ].join(' ')}
      >
        <div className="border-b border-[var(--color-on-ink)]/10 px-5 py-5">
          <BrandMark variant="sidebar" />
          <p className="mt-1 text-sm text-[var(--color-on-ink)]/60">{roleHomeLabel[roleSlug]}</p>
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

        <div className="border-t border-[var(--color-on-ink)]/10 px-5 py-4">
          <p className="truncate text-sm font-semibold">{user?.name}</p>
          <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-on-ink)]/50">
            {user?.role?.name}
          </p>
          <NavLink
            to="/account/password"
            onClick={() => setMobileOpen(false)}
            className="mt-3 block text-xs text-[var(--color-on-ink)]/60 hover:text-[var(--color-on-ink)]"
          >
            Change password
          </NavLink>
          <button
            type="button"
            onClick={() => void logout()}
            className="mt-3 w-full rounded-lg border border-[var(--color-on-ink)]/20 px-3 py-2 text-sm hover:bg-[var(--color-on-ink)]/10"
          >
            Logout
          </button>
        </div>
      </aside>

      <div className="min-w-0 flex-1">
        <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-[var(--color-paper-deep)] bg-[var(--surface-veil)] px-4 py-3 backdrop-blur lg:px-8">
          <button
            type="button"
            className="rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2 text-sm lg:hidden"
            onClick={() => setMobileOpen(true)}
          >
            Menu
          </button>
          {branding.tagline && (
            <p className="text-sm text-[var(--color-ink-muted)]">{branding.tagline}</p>
          )}
        </header>
        <main className="px-4 py-6 lg:px-8 lg:py-8">{children ?? <Outlet />}</main>
      </div>
    </div>
  )
}
