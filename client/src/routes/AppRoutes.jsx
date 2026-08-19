import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

// Layouts
import MainLayout      from '../layouts/MainLayout'
import JobSeekerLayout from '../layouts/JobSeekerLayout'
import EmployerLayout  from '../layouts/EmployerLayout'
import PesoLayout      from '../layouts/PesoLayout'
import ClcdoLayout     from '../layouts/ClcdoLayout'
import AdminLayout     from '../layouts/AdminLayout'

// Auth pages
import Login              from '../pages/auth/Login'
import UserLogin          from '../pages/auth/UserLogin'
import StaffLogin         from '../pages/auth/StaffLogin'
import Register           from '../pages/auth/Register'
import RequestVerification from '../pages/auth/RequestVerification'

// Error pages
import NotFound           from '../pages/errors/NotFound'
import Unauthorized       from '../pages/errors/Unauthorized'
import Forbidden           from '../pages/errors/Forbidden'
import ServerError        from '../pages/errors/ServerError'
import SessionExpired     from '../pages/errors/SessionExpired'
import LandingPage        from '../pages/public/LandingPage'

// Jobseeker pages
import JobSeekerDashboard  from '../pages/jobseeker/Dashboard'
import JobList             from '../pages/jobseeker/JobList'
import RecommendedJobs     from '../pages/jobseeker/RecommendedJobs'
import MyApplications      from '../pages/jobseeker/MyApplications'
import SkillsForm          from '../pages/jobseeker/SkillsForm'
import TrainingHistory     from '../pages/jobseeker/TrainingHistory'
import JobSeekerProfile    from '../pages/jobseeker/Profile'
import GrowthCenter         from '../pages/jobseeker/GrowthCenter'
import NotificationsPage   from '../pages/notifications/NotificationsPage'

// Employer pages
import EmployerDashboard  from '../pages/employer/Dashboard'
import CreateJob          from '../pages/employer/CreateJob'
import ManageJobs         from '../pages/employer/ManageJobs'
import Applicants         from '../pages/employer/Applicants'
import CompanyProfile     from '../pages/employer/CompanyProfile'
import JobAnalytics       from '../pages/employer/JobAnalytics'

// PESO pages
import PesoDashboard          from '../pages/peso/Dashboard'
import PesoManageJobs         from '../pages/peso/ManageJobs'
import ApproveJobs            from '../pages/peso/ApproveJobs'
import PesoApplicants         from '../pages/peso/Applicants'
import EmployerVerification   from '../pages/peso/EmployerVerification'
import PesoReports            from '../pages/peso/Reports'

// CLCDO pages
import ClcdoDashboard       from '../pages/clcdo/Dashboard'
import TrainingPrograms     from '../pages/clcdo/TrainingPrograms'
import CreateTraining       from '../pages/clcdo/CreateTraining'
import EnrollParticipants   from '../pages/clcdo/EnrollParticipants'
import TrainingCompletion   from '../pages/clcdo/TrainingCompletion'
import TrainingReports      from '../pages/clcdo/TrainingReports'

// Admin pages
import AdminDashboard      from '../pages/admin/Dashboard'
import UserManagement      from '../pages/admin/UserManagement'
import RoleManagement      from '../pages/admin/RoleManagement'
import SystemLogs          from '../pages/admin/SystemLogs'
import PlatformAnalytics   from '../pages/admin/PlatformAnalytics'
import MunicipalitySettings from '../pages/admin/MunicipalitySettings'

// Protected Route component
function ProtectedRoute({ children, allowedRoles }) {
  const { user, loading } = useAuth()
  if (loading) return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="flex flex-col items-center gap-3">
        <div className="spinner w-10 h-10" />
        <p className="text-sm text-gray-500 font-medium">Loading EmploySmart...</p>
      </div>
    </div>
  )
  if (!user) return <Navigate to="/login/user" replace />
  if (allowedRoles && !allowedRoles.includes(user.role)) {
    return <Navigate to={`/${user.role}`} replace />
  }
  return children
}

function RoleRedirect() {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login/user" replace />
  const roleMap = {
    jobseeker: '/jobseeker',
    employer:  '/employer',
    peso:      '/peso',
    clcdo:     '/clcdo',
    admin:     '/admin',
  }
  return <Navigate to={roleMap[user.role] || '/login'} replace />
}

function HomeRoute() {
  const { user, loading } = useAuth()
  if (loading) return <LandingPage loading />
  return user ? <RoleRedirect /> : <LandingPage />
}

export default function AppRoutes() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/login" element={<Login />} />
      <Route path="/login/user" element={<UserLogin />} />
      <Route path="/login/staff" element={<StaffLogin />} />
      <Route path="/register" element={<Register />} />
      <Route path="/request-verification" element={<RequestVerification />} />
      <Route path="/" element={<HomeRoute />} />

      {/* Jobseeker */}
      <Route path="/jobseeker" element={
        <ProtectedRoute allowedRoles={['jobseeker']}>
          <JobSeekerLayout />
        </ProtectedRoute>
      }>
        <Route index element={<JobSeekerDashboard />} />
        <Route path="jobs" element={<JobList />} />
        <Route path="recommended" element={<RecommendedJobs />} />
        <Route path="applications" element={<MyApplications />} />
        <Route path="skills" element={<SkillsForm />} />
        <Route path="trainings" element={<TrainingHistory />} />
        <Route path="growth" element={<GrowthCenter />} />
        <Route path="profile" element={<JobSeekerProfile />} />
        <Route path="notifications" element={<NotificationsPage />} />
      </Route>

      {/* Employer */}
      <Route path="/employer" element={
        <ProtectedRoute allowedRoles={['employer']}>
          <EmployerLayout />
        </ProtectedRoute>
      }>
        <Route index element={<EmployerDashboard />} />
        <Route path="create-job" element={<CreateJob />} />
        <Route path="jobs" element={<ManageJobs />} />
        <Route path="applicants" element={<Applicants />} />
        <Route path="company" element={<CompanyProfile />} />
        <Route path="analytics" element={<JobAnalytics />} />
        <Route path="notifications" element={<NotificationsPage />} />
      </Route>

      {/* PESO */}
      <Route path="/peso" element={
        <ProtectedRoute allowedRoles={['peso']}>
          <PesoLayout />
        </ProtectedRoute>
      }>
        <Route index element={<PesoDashboard />} />
        <Route path="jobs" element={<PesoManageJobs />} />
        <Route path="approve-jobs" element={<ApproveJobs />} />
        <Route path="applicants" element={<PesoApplicants />} />
        <Route path="employer-verification" element={<EmployerVerification />} />
        <Route path="reports" element={<PesoReports />} />
        <Route path="notifications" element={<NotificationsPage />} />
      </Route>

      {/* CLCDO */}
      <Route path="/clcdo" element={
        <ProtectedRoute allowedRoles={['clcdo']}>
          <ClcdoLayout />
        </ProtectedRoute>
      }>
        <Route index element={<ClcdoDashboard />} />
        <Route path="programs" element={<TrainingPrograms />} />
        <Route path="create-training" element={<CreateTraining />} />
        <Route path="enroll" element={<EnrollParticipants />} />
        <Route path="completion" element={<TrainingCompletion />} />
        <Route path="reports" element={<TrainingReports />} />
        <Route path="notifications" element={<NotificationsPage />} />
      </Route>

      {/* Admin */}
      <Route path="/admin" element={
        <ProtectedRoute allowedRoles={['admin']}>
          <AdminLayout />
        </ProtectedRoute>
      }>
        <Route index element={<AdminDashboard />} />
        <Route path="users" element={<UserManagement />} />
        <Route path="roles" element={<RoleManagement />} />
        <Route path="logs" element={<SystemLogs />} />
        <Route path="analytics" element={<PlatformAnalytics />} />
        <Route path="municipality" element={<MunicipalitySettings />} />
        <Route path="notifications" element={<NotificationsPage />} />
      </Route>

      {/* Catch-all 404 */}
      <Route path="/401" element={<Unauthorized />} />
      <Route path="/403" element={<Forbidden />} />
      <Route path="/500" element={<ServerError />} />
      <Route path="/session-expired" element={<SessionExpired />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}