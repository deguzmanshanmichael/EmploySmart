import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Navbar from '../components/Navbar'
import Sidebar from '../components/Sidebar'
import { FiHome, FiBriefcase, FiCheckSquare, FiUsers, FiShield, FiFileText } from 'react-icons/fi'

const links = [
  { to: '/peso',                        label: 'Dashboard',            icon: <FiHome />,        end: true },
  { type: 'divider', label: 'Jobs' },
  { to: '/peso/jobs',                   label: 'All Jobs',             icon: <FiBriefcase /> },
  { to: '/peso/approve-jobs',           label: 'Approve Jobs',         icon: <FiCheckSquare /> },
  { type: 'divider', label: 'People' },
  { to: '/peso/applicants',             label: 'Applicants',           icon: <FiUsers /> },
  { to: '/peso/employer-verification',  label: 'Verify Employers',     icon: <FiShield /> },
  { type: 'divider', label: 'Reports' },
  { to: '/peso/reports',                label: 'Reports',              icon: <FiFileText /> },
]

export default function PesoLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <Navbar onToggleSidebar={() => setSidebarOpen(o => !o)} sidebarOpen={sidebarOpen} />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar
          links={links}
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          roleLabel="PESO Office"
          roleColor="bg-green-50 text-green-700"
        />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}