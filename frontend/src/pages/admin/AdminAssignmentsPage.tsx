import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { isAxiosError } from 'axios'
import {
  assignSupervisor,
  fetchDepartments,
  fetchStudents,
  fetchTeachers,
  type DepartmentOption,
  type StudentListItem,
  type TeacherListItem,
} from '../../api/admin'
import { Modal } from '../../components/Modal'

export function AdminAssignmentsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [students, setStudents] = useState<StudentListItem[]>([])
  const [teachers, setTeachers] = useState<TeacherListItem[]>([])
  const [departmentId, setDepartmentId] = useState('')
  const [search, setSearch] = useState('')
  const [unassignedOnly, setUnassignedOnly] = useState(false)
  const [activeStudent, setActiveStudent] = useState<StudentListItem | null>(null)
  const [selectedTeacherId, setSelectedTeacherId] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const availableTeachers = useMemo(() => {
    if (!activeStudent?.department) {
      return teachers
    }
    return teachers.filter(
      (teacher) => teacher.department?.id === activeStudent.department?.id,
    )
  }, [teachers, activeStudent])

  useEffect(() => {
    void Promise.all([fetchDepartments(), fetchTeachers()])
      .then(([departmentList, teacherList]) => {
        setDepartments(departmentList)
        setTeachers(teacherList)
      })
      .catch(() => setError('Could not load departments or teachers.'))
  }, [])

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      setError(null)
      try {
        const response = await fetchStudents({
          department_id: departmentId || undefined,
          search: search || undefined,
          unassigned: unassignedOnly || undefined,
        })
        setStudents(response.data)
      } catch {
        setError('Could not load students.')
      } finally {
        setIsLoading(false)
      }
    }

    const timer = window.setTimeout(() => {
      void load()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [departmentId, search, unassignedOnly])

  function openAssignModal(student: StudentListItem) {
    setActiveStudent(student)
    setSelectedTeacherId(student.supervisor?.id ? String(student.supervisor.id) : '')
    setError(null)
    setSuccess(null)
    setIsModalOpen(true)
  }

  function closeModal() {
    setIsModalOpen(false)
    setActiveStudent(null)
    setSelectedTeacherId('')
  }

  async function handleAssign(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!activeStudent || !selectedTeacherId) {
      return
    }

    setIsSaving(true)
    setError(null)
    setSuccess(null)

    try {
      await assignSupervisor(activeStudent.id, Number(selectedTeacherId))
      setSuccess(`Supervisor assigned successfully for ${activeStudent.name}.`)
      closeModal()

      const response = await fetchStudents({
        department_id: departmentId || undefined,
        search: search || undefined,
        unassigned: unassignedOnly || undefined,
      })
      setStudents(response.data)

      const refreshedTeachers = await fetchTeachers()
      setTeachers(refreshedTeachers)
    } catch (err) {
      if (isAxiosError(err)) {
        setError(
          err.response?.data?.message
            ?? err.response?.data?.errors?.teacher_id?.[0]
            ?? 'Could not assign supervisor.',
        )
      } else {
        setError('Could not assign supervisor.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Assign Supervisors</h1>
          <p className="mt-1 text-slate-500">
            Pair students with eligible supervisors from their respective departments.
          </p>
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}
      {success && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
          {success}
        </div>
      )}

      {/* Filter toolbar */}
      <div className="grid gap-4 md:grid-cols-[240px_1fr_auto] items-center rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <select
          value={departmentId}
          onChange={(e) => setDepartmentId(e.target.value)}
          className="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
        >
          <option value="">All Departments</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </select>

        <div className="relative">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by student name or registration number…"
            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 pl-10 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
          />
          <svg
            className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <label className="flex items-center gap-2.5 text-sm font-medium text-slate-700 cursor-pointer select-none">
          <input
            type="checkbox"
            checked={unassignedOnly}
            onChange={(e) => setUnassignedOnly(e.target.checked)}
            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4"
          />
          <span>Unassigned only</span>
        </label>
      </div>

      {/* Full-width White Details Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading student assignment roster…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Student</th>
                  <th className="px-6 py-4">Department</th>
                  <th className="px-6 py-4">Batch</th>
                  <th className="px-6 py-4">Supervisor Status</th>
                  <th className="px-6 py-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {students.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                      No matching students found.
                    </td>
                  </tr>
                ) : (
                  students.map((student) => (
                    <tr key={student.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4">
                        <div className="font-semibold text-slate-900">{student.name}</div>
                        <div className="text-xs text-slate-500 font-mono">{student.registration_number}</div>
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                          {student.department?.name ?? '—'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-slate-600 text-xs font-medium">
                        {student.batch ?? <span className="text-slate-400 italic">No batch</span>}
                      </td>
                      <td className="px-6 py-4">
                        {student.supervisor ? (
                          <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            {student.supervisor.name}
                          </div>
                        ) : (
                          <div className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                            <span className="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            Unassigned
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => openAssignModal(student)}
                          className={`rounded-lg px-3.5 py-1.5 text-xs font-semibold transition ${
                            student.supervisor
                              ? 'border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300'
                              : 'bg-blue-600 text-white hover:bg-blue-700 shadow-sm'
                          }`}
                        >
                          {student.supervisor ? 'Change Supervisor' : 'Assign Supervisor'}
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Assignment Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        title={activeStudent?.supervisor ? 'Change Assigned Supervisor' : 'Assign Supervisor'}
        subtitle={
          activeStudent
            ? `Select an eligible faculty member from ${activeStudent.department?.name ?? 'the department'}.`
            : undefined
        }
      >
        {activeStudent && (
          <form onSubmit={handleAssign} className="space-y-4">
            <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
              <p className="text-xs font-semibold uppercase tracking-wider text-blue-700">Selected Student</p>
              <div className="mt-1 flex items-center justify-between">
                <div>
                  <p className="text-base font-bold text-slate-900">{activeStudent.name}</p>
                  <p className="text-xs text-slate-600 font-mono">{activeStudent.registration_number}</p>
                </div>
                <span className="rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-blue-700 border border-blue-200">
                  {activeStudent.department?.name}
                </span>
              </div>
            </div>

            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Choose Faculty Supervisor</span>
              <select
                required
                value={selectedTeacherId}
                onChange={(e) => setSelectedTeacherId(e.target.value)}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">Select a supervisor</option>
                {availableTeachers.map((teacher) => (
                  <option key={teacher.id} value={teacher.id}>
                    {teacher.name} — ({teacher.max_projects} max quota)
                  </option>
                ))}
              </select>
            </label>

            {availableTeachers.length === 0 && (
              <p className="text-xs text-amber-700 bg-amber-50 p-3 rounded-lg border border-amber-200">
                No supervisors currently found in {activeStudent.department?.name}. Please add teachers to this department first.
              </p>
            )}

            <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
              <button
                type="button"
                onClick={closeModal}
                className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSaving || !selectedTeacherId}
                className="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60 transition"
              >
                {isSaving ? 'Assigning…' : 'Confirm Assignment'}
              </button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  )
}
