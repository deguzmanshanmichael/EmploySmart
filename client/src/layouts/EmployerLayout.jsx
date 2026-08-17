import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Navbar from '../components/Navbar'
import Sidebar from '../components/Sidebar'
import { FiHome, FiPlusSquare, FiBriefcase, FiUsers, FiBarChart2 } from 'react-icons/fi'
import { FaBuilding } from 'react-icons/fa' // ✅ ADD THIS

const links = [
  { to: '/employer',             label: 'Dashboard',       icon: <FiHome />,       end: true },
  { type: 'divider', label: 'Jobs' },
  { to: '/employer/create-job',  label: 'Post a Job',      icon: <FiPlusSquare /> },
  { to: '/employer/jobs',        label: 'Manage Jobs',     icon: <FiBriefcase /> },
  { to: '/employer/applicants',  label: 'Applicants',      icon: <FiUsers /> },
  { type: 'divider', label: 'Company' },
  { to: '/employer/company',     label: 'Company Profile', icon: <FaBuilding /> }, // ✅ FIXED
  { to: '/employer/analytics',   label: 'Analytics',       icon: <FiBarChart2 /> },
]

export default function EmployerLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <Navbar onToggleSidebar={() => setSidebarOpen(o => !o)} sidebarOpen={sidebarOpen} />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar
          links={links}
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          roleLabel="Employer Portal"
          roleColor="bg-purple-50 text-purple-700"
        />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}