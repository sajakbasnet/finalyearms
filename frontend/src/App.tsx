import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { GuestRoute, ProtectedRoute } from './components/ProtectedRoute'
import { AuthProvider } from './contexts/AuthContext'
import { AppLayout } from './layouts/AppLayout'
import { AdminAssignmentsPage } from './pages/admin/AdminAssignmentsPage'
import { AdminDepartmentsPage } from './pages/admin/AdminDepartmentsPage'
import { AdminProposalsPage } from './pages/admin/AdminProposalsPage'
import { AdminSessionsPage } from './pages/admin/AdminSessionsPage'
import { AdminStudentsPage } from './pages/admin/AdminStudentsPage'
import { AdminTeachersPage } from './pages/admin/AdminTeachersPage'
import { DashboardPage } from './pages/DashboardPage'
import { LoginPage } from './pages/LoginPage'
import { StudentFinalPage } from './pages/student/StudentFinalPage'
import { StudentNotificationsPage } from './pages/student/StudentNotificationsPage'
import { StudentProgressPage } from './pages/student/StudentProgressPage'
import { StudentProposalPage } from './pages/student/StudentProposalPage'
import { StudentSupervisorPage } from './pages/student/StudentSupervisorPage'
import { StudentTimelinePage } from './pages/student/StudentTimelinePage'
import { TeacherProgressPage } from './pages/teacher/TeacherProgressPage'
import { TeacherProjectPage } from './pages/teacher/TeacherProjectPage'
import { TeacherProposalDetailPage } from './pages/teacher/TeacherProposalDetailPage'
import { TeacherProposalsPage } from './pages/teacher/TeacherProposalsPage'
import { TeacherStudentsPage } from './pages/teacher/TeacherStudentsPage'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<GuestRoute />}>
            <Route path="/login" element={<LoginPage />} />
          </Route>

          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route path="/dashboard" element={<DashboardPage />} />
            </Route>
          </Route>

          <Route element={<ProtectedRoute roles={['admin']} />}>
            <Route element={<AppLayout />}>
              <Route path="/admin/departments" element={<AdminDepartmentsPage />} />
              <Route path="/admin/teachers" element={<AdminTeachersPage />} />
              <Route path="/admin/students" element={<AdminStudentsPage />} />
              <Route path="/admin/assignments" element={<AdminAssignmentsPage />} />
              <Route path="/admin/proposals" element={<AdminProposalsPage />} />
              <Route path="/admin/sessions" element={<AdminSessionsPage />} />
            </Route>
          </Route>

          <Route element={<ProtectedRoute roles={['teacher']} />}>
            <Route element={<AppLayout />}>
              <Route path="/teacher/students" element={<TeacherStudentsPage />} />
              <Route path="/teacher/proposals" element={<TeacherProposalsPage />} />
              <Route path="/teacher/proposals/:id" element={<TeacherProposalDetailPage />} />
              <Route path="/teacher/progress" element={<TeacherProgressPage />} />
              <Route path="/teacher/projects/:id" element={<TeacherProjectPage />} />
            </Route>
          </Route>

          <Route element={<ProtectedRoute roles={['student']} />}>
            <Route element={<AppLayout />}>
              <Route path="/student/supervisor" element={<StudentSupervisorPage />} />
              <Route path="/student/proposal" element={<StudentProposalPage />} />
              <Route path="/student/progress" element={<StudentProgressPage />} />
              <Route path="/student/final" element={<StudentFinalPage />} />
              <Route path="/student/timeline" element={<StudentTimelinePage />} />
              <Route path="/student/notifications" element={<StudentNotificationsPage />} />
            </Route>
          </Route>

          <Route path="/" element={<Navigate to="/login" replace />} />
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
