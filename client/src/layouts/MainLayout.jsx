import { Outlet } from 'react-router-dom'

/**
 * MainLayout — used for public/unauthenticated pages (login, register, etc.)
 * Role-specific layouts (JobSeekerLayout, EmployerLayout, etc.) are used for authenticated portals.
 */
export default function MainLayout() {
  return (
    <div className="min-h-screen bg-gray-50">
      <Outlet />
    </div>
  )
}