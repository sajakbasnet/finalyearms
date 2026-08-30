import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { BrandingProvider } from './branding/BrandingContext'
import { GuestRoute, ProtectedRoute } from './components/ProtectedRoute'
import { AuthProvider } from './contexts/AuthContext'
import { AppLayout } from './layouts/AppLayout'
import { AdminAssignmentsPage } from './pages/admin/AdminAssignmentsPage'
import { AdminBatchesPage } from './pages/admin/AdminBatchesPage'
import { AdminDepartmentsPage } from './pages/admin/AdminDepartmentsPage'
import { AdminProposalsPage } from './pages/admin/AdminProposalsPage'
import { AdminSessionsPage } from './pages/admin/AdminSessionsPage'
import { AdminStudentsPage } from './pages/admin/AdminStudentsPage'
import { AdminTeachersPage } from './pages/admin/AdminTeachersPage'
import { CoordinatorProjectTypesPage } from './pages/coordinator/CoordinatorProjectTypesPage'
import { CoordinatorTemplatesPage } from './pages/coordinator/CoordinatorTemplatesPage'
import { DashboardPage } from './pages/DashboardPage'
import { ForgotPasswordPage } from './pages/ForgotPasswordPage'
import { LoginPage } from './pages/LoginPage'
import { ResetPasswordPage } from './pages/ResetPasswordPage'
import { ChangePasswordPage } from './pages/ChangePasswordPage'
import { StudentFinalPage } from './pages/student/StudentFinalPage'
import { StudentNotificationsPage } from './pages/student/StudentNotificationsPage'
import { StudentProgressPage } from './pages/student/StudentProgressPage'
import { StudentProposalHistoryPage } from './pages/student/StudentProposalHistoryPage'
import { StudentProposalPage } from './pages/student/StudentProposalPage'
import { StudentSupervisorPage } from './pages/student/StudentSupervisorPage'
import { StudentSupervisorRequestPage } from './pages/student/StudentSupervisorRequestPage'
import { StudentTeamPage } from './pages/student/StudentTeamPage'
import { StudentTimelinePage } from './pages/student/StudentTimelinePage'
import { TeacherProgressPage } from './pages/teacher/TeacherProgressPage'
import { TeacherProjectPage } from './pages/teacher/TeacherProjectPage'
import { TeacherProposalDetailPage } from './pages/teacher/TeacherProposalDetailPage'
import { TeacherProposalsPage } from './pages/teacher/TeacherProposalsPage'
import { TeacherRequestsPage } from './pages/teacher/TeacherRequestsPage'
import { TeacherStudentsPage } from './pages/teacher/TeacherStudentsPage'

export default function App() {
  return (
    <BrandingProvider>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route element={<GuestRoute />}>
              <Route path="/login" element={<LoginPage />} />
              {/* The backend's reset email points at /reset-password. */}
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPasswordPage />} />
            </Route>

            <Route element={<ProtectedRoute />}>
              <Route element={<AppLayout />}>
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/account/password" element={<ChangePasswordPage />} />
              </Route>
            </Route>

            {/* Institution Admin — accounts and organisational structure. */}
            <Route element={<ProtectedRoute roles={['institution_admin']} />}>
              <Route element={<AppLayout />}>
                <Route path="/admin/departments" element={<AdminDepartmentsPage />} />
                <Route path="/admin/teachers" element={<AdminTeachersPage />} />
                <Route path="/admin/students" element={<AdminStudentsPage />} />
                <Route path="/admin/batches" element={<AdminBatchesPage />} />
                <Route path="/admin/sessions" element={<AdminSessionsPage />} />
              </Route>
            </Route>

            {/* Coordinator — supervision overrides and proposal oversight. */}
            <Route element={<ProtectedRoute roles={['coordinator']} />}>
              <Route element={<AppLayout />}>
                <Route path="/admin/assignments" element={<AdminAssignmentsPage />} />
                <Route path="/admin/proposals" element={<AdminProposalsPage />} />
                <Route path="/coordinator/project-types" element={<CoordinatorProjectTypesPage />} />
                <Route path="/coordinator/templates" element={<CoordinatorTemplatesPage />} />
              </Route>
            </Route>

            <Route element={<ProtectedRoute roles={['supervisor']} />}>
              <Route element={<AppLayout />}>
                <Route path="/teacher/students" element={<TeacherStudentsPage />} />
                <Route path="/teacher/requests" element={<TeacherRequestsPage />} />
                <Route path="/teacher/proposals" element={<TeacherProposalsPage />} />
                <Route path="/teacher/proposals/:id" element={<TeacherProposalDetailPage />} />
                <Route path="/teacher/progress" element={<TeacherProgressPage />} />
                <Route path="/teacher/projects/:id" element={<TeacherProjectPage />} />
              </Route>
            </Route>

            <Route element={<ProtectedRoute roles={['student']} />}>
              <Route element={<AppLayout />}>
                <Route path="/student/team" element={<StudentTeamPage />} />
                <Route path="/student/find-supervisor" element={<StudentSupervisorRequestPage />} />
                <Route path="/student/supervisor" element={<StudentSupervisorPage />} />
                <Route path="/student/proposal" element={<StudentProposalPage />} />
                <Route path="/student/proposal/history" element={<StudentProposalHistoryPage />} />
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
    </BrandingProvider>
  )
}
