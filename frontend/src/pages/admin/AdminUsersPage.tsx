import { useState, useEffect, type FormEvent } from 'react'
import { Modal } from '../../components/Modal'
import {
  fetchUsers,
  fetchRoles,
  createUser,
  updateUser,
  deleteUser,
  toggleUserStatus,
  type ManagedUser,
  type Role,
  type CreateUserData,
  type UpdateUserData,
} from '../../api/users'
import { fetchDepartments, fetchBatches, type DepartmentOption, type BatchOption } from '../../api/admin'
import { useAuth } from '../../contexts/AuthContext'

export function AdminUsersPage() {
  const { user: currentAuthUser } = useAuth()

  // Data state
  const [users, setUsers] = useState<ManagedUser[]>([])
  const [roles, setRoles] = useState<Role[]>([])
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [batches, setBatches] = useState<BatchOption[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  // Filters state
  const [selectedRole, setSelectedRole] = useState<string>('all')
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all')

  // Modals state
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [editingUser, setEditingUser] = useState<ManagedUser | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Add Form state
  const [createForm, setCreateForm] = useState<CreateUserData>({
    name: '',
    email: '',
    phone: '',
    password: '',
    role_id: 0,
    department_id: null,
    employee_id: '',
    designation: '',
    registration_number: '',
    roll_number: '',
    batch_id: null,
    is_active: true,
  })

  // Edit Form state
  const [editForm, setEditForm] = useState<UpdateUserData>({
    name: '',
    email: '',
    phone: '',
    password: '',
    role_id: 0,
    department_id: null,
    is_active: true,
  })

  const loadData = async () => {
    setIsLoading(true)
    try {
      const [usersRes, rolesRes, deptsRes, batchesRes] = await Promise.all([
        fetchUsers({
          role: selectedRole === 'all' ? undefined : selectedRole,
          search: searchQuery.trim() || undefined,
          is_active: statusFilter === 'all' ? undefined : statusFilter === 'active',
          per_page: 50,
        }),
        fetchRoles(),
        fetchDepartments(),
        fetchBatches(),
      ])

      setUsers(usersRes.users)
      setRoles(rolesRes)
      setDepartments(deptsRes)
      setBatches(batchesRes)

      // Set default role in createForm if not set
      if (rolesRes.length > 0 && createForm.role_id === 0) {
        const studentRole = rolesRes.find((r) => r.slug === 'student') ?? rolesRes[0]
        setCreateForm((prev) => ({ ...prev, role_id: studentRole.id }))
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to load user management data.'
      setFeedback({ type: 'error', message: msg })
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void loadData()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedRole, statusFilter])

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault()
    void loadData()
  }

  const handleOpenAddModal = () => {
    setCreateForm({
      name: '',
      email: '',
      phone: '',
      password: '',
      role_id: roles.find((r) => r.slug === 'student')?.id ?? roles[0]?.id ?? 0,
      department_id: departments[0]?.id ?? null,
      employee_id: '',
      designation: '',
      registration_number: '',
      roll_number: '',
      batch_id: batches[0]?.id ?? null,
      is_active: true,
    })
    setIsAddModalOpen(true)
  }

  const handleOpenEditModal = (targetUser: ManagedUser) => {
    setEditingUser(targetUser)
    setEditForm({
      name: targetUser.name,
      email: targetUser.email,
      phone: targetUser.phone || '',
      password: '',
      role_id: targetUser.role?.id ?? 0,
      department_id: targetUser.department?.id ?? null,
      is_active: targetUser.is_active,
    })
    setIsEditModalOpen(true)
  }

  const handleCreateSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    try {
      await createUser(createForm)
      setIsAddModalOpen(false)
      setFeedback({ type: 'success', message: `User "${createForm.name}" created successfully.` })
      void loadData()
      setTimeout(() => setFeedback(null), 4000)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to create user.'
      setFeedback({ type: 'error', message: msg })
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleEditSubmit = async (e: FormEvent) => {
    e.preventDefault()
    if (!editingUser) return
    setIsSubmitting(true)
    try {
      const payload: UpdateUserData = {
        name: editForm.name,
        email: editForm.email,
        phone: editForm.phone || null,
        role_id: editForm.role_id,
        department_id: editForm.department_id,
        is_active: editForm.is_active,
      }
      if (editForm.password && editForm.password.trim().length >= 8) {
        payload.password = editForm.password.trim()
      }

      await updateUser(editingUser.id, payload)
      setIsEditModalOpen(false)
      setFeedback({ type: 'success', message: `User "${editForm.name}" updated successfully.` })
      void loadData()
      setTimeout(() => setFeedback(null), 4000)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to update user.'
      setFeedback({ type: 'error', message: msg })
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleToggleStatus = async (targetUser: ManagedUser) => {
    if (targetUser.id === currentAuthUser?.id) {
      alert('You cannot deactivate your own administrative account.')
      return
    }

    const action = targetUser.is_active ? 'deactivate' : 'activate'
    if (!window.confirm(`Are you sure you want to ${action} ${targetUser.name}?`)) {
      return
    }

    try {
      const updated = await toggleUserStatus(targetUser.id)
      setFeedback({
        type: 'success',
        message: `Account for ${updated.name} is now ${updated.is_active ? 'Active' : 'Deactivated'}.`,
      })
      void loadData()
      setTimeout(() => setFeedback(null), 4000)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to toggle user status.'
      setFeedback({ type: 'error', message: msg })
    }
  }

  const handleDelete = async (targetUser: ManagedUser) => {
    if (targetUser.id === currentAuthUser?.id) {
      alert('You cannot delete your own administrative account.')
      return
    }

    if (
      !window.confirm(
        `Are you sure you want to permanently delete ${targetUser.name} (${targetUser.email})? This action cannot be undone.`,
      )
    ) {
      return
    }

    try {
      await deleteUser(targetUser.id)
      setFeedback({ type: 'success', message: `User ${targetUser.name} deleted successfully.` })
      void loadData()
      setTimeout(() => setFeedback(null), 4000)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to delete user.'
      setFeedback({ type: 'error', message: msg })
    }
  }

  // Selected create role slug helper
  const selectedCreateRoleSlug = roles.find((r) => r.id === createForm.role_id)?.slug

  const getRoleBadge = (slug?: string) => {
    switch (slug) {
      case 'institution_admin':
        return 'bg-purple-50 text-purple-700 border-purple-200'
      case 'coordinator':
        return 'bg-sky-50 text-sky-700 border-sky-200'
      case 'supervisor':
        return 'bg-indigo-50 text-indigo-700 border-indigo-200'
      case 'student':
        return 'bg-emerald-50 text-emerald-700 border-emerald-200'
      case 'employer':
        return 'bg-amber-50 text-amber-700 border-amber-200'
      default:
        return 'bg-slate-50 text-slate-700 border-slate-200'
    }
  }

  return (
    <div className="space-y-6">
      {/* Top Header Card */}
      <div className="rounded-xl border border-blue-200/60 bg-gradient-to-r from-blue-50 via-white to-blue-50/50 p-6 shadow-sm">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-800">
              User &amp; Access Control
            </span>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
              User &amp; Role Management
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Manage accounts, assign roles, update profiles, and control platform access across all 5 roles.
            </p>
          </div>
          <div>
            <button
              type="button"
              onClick={handleOpenAddModal}
              className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition"
            >
              <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              Add New User
            </button>
          </div>
        </div>

        {/* Filter and Search Bar */}
        <div className="mt-6 flex flex-col gap-3 pt-4 border-t border-slate-200/60 lg:flex-row lg:items-center lg:justify-between">
          {/* Role Tabs */}
          <div className="flex flex-wrap items-center gap-1.5">
            <button
              type="button"
              onClick={() => setSelectedRole('all')}
              className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                selectedRole === 'all'
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
              }`}
            >
              All Roles
            </button>
            {roles.map((r) => (
              <button
                key={r.slug}
                type="button"
                onClick={() => setSelectedRole(r.slug)}
                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                  selectedRole === r.slug
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
                }`}
              >
                {r.name}
              </button>
            ))}
          </div>

          {/* Search and Status filter */}
          <div className="flex items-center gap-2">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as 'all' | 'active' | 'inactive')}
              className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 outline-none"
            >
              <option value="all">All Status</option>
              <option value="active">Active Only</option>
              <option value="inactive">Deactivated Only</option>
            </select>

            <form onSubmit={handleSearchSubmit} className="flex items-center gap-1">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search name, email, ID..."
                className="w-48 sm:w-64 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 outline-none focus:border-blue-600"
              />
              <button
                type="submit"
                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                Search
              </button>
            </form>
          </div>
        </div>
      </div>

      {feedback && (
        <div
          className={`rounded-lg p-4 text-sm font-medium transition ${
            feedback.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              : 'bg-rose-50 text-rose-800 border border-rose-200'
          }`}
        >
          {feedback.message}
        </div>
      )}

      {/* Users Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-700">
              <tr>
                <th className="px-6 py-4">User</th>
                <th className="px-6 py-4">Role</th>
                <th className="px-6 py-4">Department &amp; Identity</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4">Created</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {isLoading ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-slate-400">
                    <div className="inline-flex items-center gap-2">
                      <span className="h-4 w-4 rounded-full border-2 border-blue-600 border-t-transparent animate-spin" />
                      Loading users...
                    </div>
                  </td>
                </tr>
              ) : users.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-slate-400">
                    No users found matching your criteria.
                  </td>
                </tr>
              ) : (
                users.map((u) => {
                  const initials = u.name
                    .split(' ')
                    .map((n) => n[0])
                    .slice(0, 2)
                    .join('')
                    .toUpperCase()

                  return (
                    <tr key={u.id} className="hover:bg-slate-50/60 transition">
                      {/* User Info */}
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-bold shadow-xs">
                            {initials}
                          </div>
                          <div>
                            <p className="font-semibold text-slate-900">{u.name}</p>
                            <p className="text-xs text-slate-500">{u.email}</p>
                            {u.phone && <p className="text-[11px] text-slate-400">{u.phone}</p>}
                          </div>
                        </div>
                      </td>

                      {/* Role Badge */}
                      <td className="px-6 py-4">
                        <span
                          className={`inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold ${getRoleBadge(
                            u.role?.slug,
                          )}`}
                        >
                          {u.role?.name ?? 'No Role'}
                        </span>
                      </td>

                      {/* Department / Details */}
                      <td className="px-6 py-4 text-xs">
                        {u.department ? (
                          <p className="font-medium text-slate-800">
                            {u.department.name} <span className="text-slate-400 font-normal">({u.department.code})</span>
                          </p>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                        {u.details.employee_id && (
                          <p className="text-[11px] text-slate-500">ID: {u.details.employee_id}</p>
                        )}
                        {u.details.registration_number && (
                          <p className="text-[11px] text-slate-500">Reg: {u.details.registration_number}</p>
                        )}
                        {u.details.batch && (
                          <p className="text-[11px] text-slate-400">Batch: {u.details.batch}</p>
                        )}
                      </td>

                      {/* Status */}
                      <td className="px-6 py-4">
                        <span
                          className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium border ${
                            u.is_active
                              ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                              : 'bg-rose-50 text-rose-700 border-rose-200'
                          }`}
                        >
                          <span
                            className={`h-1.5 w-1.5 rounded-full ${
                              u.is_active ? 'bg-emerald-500' : 'bg-rose-500'
                            }`}
                          />
                          {u.is_active ? 'Active' : 'Deactivated'}
                        </span>
                      </td>

                      {/* Created */}
                      <td className="px-6 py-4 text-xs text-slate-500">
                        {u.created_at ? new Date(u.created_at).toLocaleDateString() : '—'}
                      </td>

                      {/* Actions */}
                      <td className="px-6 py-4 text-right">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => handleOpenEditModal(u)}
                            className="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition shadow-xs"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => handleToggleStatus(u)}
                            title={u.is_active ? 'Deactivate account' : 'Activate account'}
                            className={`rounded-md border px-2.5 py-1 text-xs font-medium transition shadow-xs ${
                              u.is_active
                                ? 'border-slate-200 bg-white text-slate-600 hover:bg-amber-50 hover:text-amber-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                            }`}
                          >
                            {u.is_active ? 'Deactivate' : 'Activate'}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(u)}
                            disabled={u.id === currentAuthUser?.id}
                            className="rounded-md border border-rose-200 bg-white px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 hover:border-rose-300 transition shadow-xs disabled:opacity-40 disabled:cursor-not-allowed"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add User Modal */}
      <Modal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        title="Add New User"
        subtitle="Create an account and assign roles, department, and credentials."
        maxWidth="xl"
      >
        <form onSubmit={handleCreateSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Full Name <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                required
                value={createForm.name}
                onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })}
                placeholder="e.g. Dr. Jane Smith or Alex Hunter"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Email Address <span className="text-rose-500">*</span>
              </label>
              <input
                type="email"
                required
                value={createForm.email}
                onChange={(e) => setCreateForm({ ...createForm, email: e.target.value })}
                placeholder="user@fyp.local"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Password <span className="text-rose-500">*</span>
              </label>
              <input
                type="password"
                required
                minLength={8}
                value={createForm.password}
                onChange={(e) => setCreateForm({ ...createForm, password: e.target.value })}
                placeholder="Min. 8 characters"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Phone Number
              </label>
              <input
                type="tel"
                value={createForm.phone || ''}
                onChange={(e) => setCreateForm({ ...createForm, phone: e.target.value })}
                placeholder="+977-9800000000"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              />
            </div>
          </div>

          {/* Role selector */}
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              Role <span className="text-rose-500">*</span>
            </label>
            <select
              required
              value={createForm.role_id}
              onChange={(e) => setCreateForm({ ...createForm, role_id: Number(e.target.value) })}
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
            >
              {roles.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name} ({r.description})
                </option>
              ))}
            </select>
          </div>

          {/* Department selector (for relevant roles) */}
          {['supervisor', 'coordinator', 'student'].includes(selectedCreateRoleSlug || '') && (
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Department
              </label>
              <select
                value={createForm.department_id ?? ''}
                onChange={(e) =>
                  setCreateForm({
                    ...createForm,
                    department_id: e.target.value ? Number(e.target.value) : null,
                  })
                }
                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              >
                <option value="">Select Department...</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name} ({d.code})
                  </option>
                ))}
              </select>
            </div>
          )}

          {/* Specific fields for supervisor / coordinator */}
          {['supervisor', 'coordinator'].includes(selectedCreateRoleSlug || '') && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 rounded-lg bg-slate-50 p-3 border border-slate-200">
              <div>
                <label className="block text-xs font-medium text-slate-700">Employee ID</label>
                <input
                  type="text"
                  value={createForm.employee_id || ''}
                  onChange={(e) => setCreateForm({ ...createForm, employee_id: e.target.value })}
                  placeholder="e.g. EMP-101"
                  className="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-blue-600"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-700">Designation</label>
                <input
                  type="text"
                  value={createForm.designation || ''}
                  onChange={(e) => setCreateForm({ ...createForm, designation: e.target.value })}
                  placeholder="e.g. Assistant Professor"
                  className="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-blue-600"
                />
              </div>
            </div>
          )}

          {/* Specific fields for student */}
          {selectedCreateRoleSlug === 'student' && (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 rounded-lg bg-slate-50 p-3 border border-slate-200">
              <div>
                <label className="block text-xs font-medium text-slate-700">Registration #</label>
                <input
                  type="text"
                  value={createForm.registration_number || ''}
                  onChange={(e) => setCreateForm({ ...createForm, registration_number: e.target.value })}
                  placeholder="e.g. 077BCT001"
                  className="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-blue-600"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-700">Roll #</label>
                <input
                  type="text"
                  value={createForm.roll_number || ''}
                  onChange={(e) => setCreateForm({ ...createForm, roll_number: e.target.value })}
                  placeholder="e.g. 1"
                  className="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-blue-600"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-700">Batch</label>
                <select
                  value={createForm.batch_id ?? ''}
                  onChange={(e) =>
                    setCreateForm({
                      ...createForm,
                      batch_id: e.target.value ? Number(e.target.value) : null,
                    })
                  }
                  className="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs outline-none focus:border-blue-600"
                >
                  <option value="">Select Batch...</option>
                  {batches.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          )}

          {/* Active status */}
          <div className="flex items-center gap-2 pt-2">
            <input
              type="checkbox"
              id="create_is_active"
              checked={createForm.is_active}
              onChange={(e) => setCreateForm({ ...createForm, is_active: e.target.checked })}
              className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />
            <label htmlFor="create_is_active" className="text-sm font-medium text-slate-700">
              Active account (user can sign in immediately)
            </label>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsAddModalOpen(false)}
              className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition disabled:opacity-60"
            >
              {isSubmitting ? 'Creating User…' : 'Create User'}
            </button>
          </div>
        </form>
      </Modal>

      {/* Edit User Modal */}
      <Modal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        title="Edit User &amp; Role"
        subtitle={`Update account details and role for ${editingUser?.name || 'user'}.`}
        maxWidth="lg"
      >
        <form onSubmit={handleEditSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              Full Name <span className="text-rose-500">*</span>
            </label>
            <input
              type="text"
              required
              value={editForm.name}
              onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              Email Address <span className="text-rose-500">*</span>
            </label>
            <input
              type="email"
              required
              value={editForm.email}
              onChange={(e) => setEditForm({ ...editForm, email: e.target.value })}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              Assigned Role <span className="text-rose-500">*</span>
            </label>
            <select
              required
              value={editForm.role_id}
              onChange={(e) => setEditForm({ ...editForm, role_id: Number(e.target.value) })}
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
            >
              {roles.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name} ({r.description})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              Department
            </label>
            <select
              value={editForm.department_id ?? ''}
              onChange={(e) =>
                setEditForm({
                  ...editForm,
                  department_id: e.target.value ? Number(e.target.value) : null,
                })
              }
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
            >
              <option value="">No Department Assigned</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name} ({d.code})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Phone Number
              </label>
              <input
                type="tel"
                value={editForm.phone || ''}
                onChange={(e) => setEditForm({ ...editForm, phone: e.target.value })}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                Reset Password <span className="text-slate-400 font-normal">(Leave blank to keep)</span>
              </label>
              <input
                type="password"
                minLength={8}
                value={editForm.password || ''}
                onChange={(e) => setEditForm({ ...editForm, password: e.target.value })}
                placeholder="New password (optional)"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
              />
            </div>
          </div>

          <div className="flex items-center gap-2 pt-2">
            <input
              type="checkbox"
              id="edit_is_active"
              checked={editForm.is_active}
              onChange={(e) => setEditForm({ ...editForm, is_active: e.target.checked })}
              className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />
            <label htmlFor="edit_is_active" className="text-sm font-medium text-slate-700">
              Active account (user can log in)
            </label>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsEditModalOpen(false)}
              className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition disabled:opacity-60"
            >
              {isSubmitting ? 'Saving Changes…' : 'Save Changes'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
