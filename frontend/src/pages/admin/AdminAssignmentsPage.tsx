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

export function AdminAssignmentsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [students, setStudents] = useState<StudentListItem[]>([])
  const [teachers, setTeachers] = useState<TeacherListItem[]>([])
  const [departmentId, setDepartmentId] = useState('')
  const [search, setSearch] = useState('')
  const [unassignedOnly, setUnassignedOnly] = useState(false)
  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null)
  const [selectedTeacherId, setSelectedTeacherId] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const selectedStudent = useMemo(
    () => students.find((student) => student.id === selectedStudentId) ?? null,
    [students, selectedStudentId],
  )

  const availableTeachers = useMemo(() => {
    if (!selectedStudent?.department) {
      return teachers
    }

    return teachers.filter(
      (teacher) => teacher.department?.id === selectedStudent.department?.id,
    )
  }, [teachers, selectedStudent])

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
        setSelectedStudentId((current) =>
          current && !response.data.some((s) => s.id === current) ? null : current,
        )
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

  useEffect(() => {
    if (!selectedStudent) {
      setSelectedTeacherId('')
      return
    }

    setSelectedTeacherId(selectedStudent.supervisor?.id ? String(selectedStudent.supervisor.id) : '')
  }, [selectedStudent])

  async function handleAssign(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!selectedStudentId || !selectedTeacherId) {
      return
    }

    setIsSaving(true)
    setError(null)
    setSuccess(null)

    try {
      await assignSupervisor(selectedStudentId, Number(selectedTeacherId))
      setSuccess('Supervisor assigned successfully.')

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
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Assign supervisors
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Match students with teachers from the same department.
        </p>
      </header>

      <div className="grid gap-3 md:grid-cols-[1fr_1.3fr_auto]">
        <select
          value={departmentId}
          onChange={(e) => setDepartmentId(e.target.value)}
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-sea)]"
        >
          <option value="">All departments</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </select>

        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search student name or registration…"
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-sea)]"
        />

        <label className="flex items-center gap-2 rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 text-sm">
          <input
            type="checkbox"
            checked={unassignedOnly}
            onChange={(e) => setUnassignedOnly(e.target.checked)}
          />
          Unassigned only
        </label>
      </div>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div className="overflow-hidden rounded-xl border border-[var(--color-paper-deep)] bg-white/70">
          {isLoading ? (
            <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading students…</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Student</th>
                  <th className="px-4 py-3 font-medium">Department</th>
                  <th className="px-4 py-3 font-medium">Supervisor</th>
                </tr>
              </thead>
              <tbody>
                {students.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="px-4 py-6 text-[var(--color-ink-muted)]">
                      No students found.
                    </td>
                  </tr>
                ) : (
                  students.map((student) => {
                    const isSelected = student.id === selectedStudentId
                    return (
                      <tr
                        key={student.id}
                        className={`cursor-pointer border-t border-[var(--color-paper-deep)] ${
                          isSelected ? 'bg-[var(--color-sea-soft)]' : 'hover:bg-[var(--color-paper)]'
                        }`}
                        onClick={() => setSelectedStudentId(student.id)}
                      >
                        <td className="px-4 py-3">
                          <p className="font-medium">{student.name}</p>
                          <p className="text-[var(--color-ink-muted)]">{student.registration_number}</p>
                        </td>
                        <td className="px-4 py-3">{student.department?.name ?? '—'}</td>
                        <td className="px-4 py-3">
                          {student.supervisor?.name ?? (
                            <span className="text-[var(--color-amber)]">Unassigned</span>
                          )}
                        </td>
                      </tr>
                    )
                  })
                )}
              </tbody>
            </table>
          )}
        </div>

        <form
          onSubmit={handleAssign}
          className="rounded-xl border border-[var(--color-paper-deep)] bg-white/70 p-5"
        >
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            Assignment panel
          </h2>

          {!selectedStudent ? (
            <p className="mt-4 text-sm text-[var(--color-ink-muted)]">
              Select a student from the list to assign or change their supervisor.
            </p>
          ) : (
            <div className="mt-4 space-y-4">
              <div>
                <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">Student</p>
                <p className="mt-1 font-semibold">{selectedStudent.name}</p>
                <p className="text-sm text-[var(--color-ink-muted)]">
                  {selectedStudent.registration_number} · {selectedStudent.department?.name}
                </p>
              </div>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-[var(--color-ink-muted)]">Teacher</span>
                <select
                  value={selectedTeacherId}
                  onChange={(e) => setSelectedTeacherId(e.target.value)}
                  required
                  className="w-full rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-sea)]"
                >
                  <option value="">Select teacher</option>
                  {availableTeachers.map((teacher) => (
                    <option key={teacher.id} value={teacher.id}>
                      {teacher.name} ({teacher.active_students_count}/{teacher.maximum_students})
                    </option>
                  ))}
                </select>
              </label>

              {availableTeachers.length === 0 && (
                <p className="text-sm text-[var(--color-amber)]">
                  No teachers available in this department.
                </p>
              )}

              <button
                type="submit"
                disabled={isSaving || !selectedTeacherId}
                className="w-full rounded-lg bg-[var(--color-sea)] px-4 py-3 font-semibold text-white transition hover:bg-[var(--color-sea-deep)] disabled:cursor-not-allowed disabled:opacity-70"
              >
                {isSaving ? 'Saving…' : 'Assign teacher'}
              </button>
            </div>
          )}
        </form>
      </div>
    </div>
  )
}
