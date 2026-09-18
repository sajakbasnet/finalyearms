import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { extractError, fetchTeacherStudents, type TeacherStudentRow } from '../../api/teacher'

export function TeacherStudentsPage() {
  const [rows, setRows] = useState<TeacherStudentRow[]>([])
  const [error, setError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    void fetchTeacherStudents()
      .then(setRows)
      .catch((err) => setError(extractError(err, 'Could not load assigned students.')))
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Assigned Students</h1>
          <p className="mt-1 text-slate-500">
            Roster of students and projects currently supervised under your account.
          </p>
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading students…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Student</th>
                  <th className="px-6 py-4">Department & Batch</th>
                  <th className="px-6 py-4">Project Workspace</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-6 py-12 text-center text-slate-500">
                      No students currently assigned to you for supervision.
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.assignment_id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4">
                        <p className="font-semibold text-slate-900">{row.student.name}</p>
                        <p className="text-xs text-slate-500">
                          {row.student.registration_number} · {row.student.email}
                        </p>
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 border border-blue-200">
                          {row.student.department ?? '—'}
                        </span>
                        <div className="text-xs text-slate-500 mt-1">Batch: {row.student.batch ?? '—'}</div>
                      </td>
                      <td className="px-6 py-4">
                        {row.project ? (
                          <div>
                            <p className="font-medium text-slate-800">{row.project.title}</p>
                            <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 mt-0.5">
                              {row.project.status_label}
                            </span>
                          </div>
                        ) : (
                          <span className="text-slate-400 italic text-xs">No active project yet</span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        {row.project ? (
                          <Link
                            to={`/teacher/projects/${row.project.id}`}
                            className="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition shadow-sm"
                          >
                            Open Project
                          </Link>
                        ) : (
                          <span className="text-slate-300 text-xs">—</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
