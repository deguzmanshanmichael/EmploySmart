import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Navbar from '../components/Navbar'
import Sidebar from '../components/Sidebar'
import { FiHome, FiUsers, FiShield, FiActivity, FiBarChart2, FiMapPin } from 'react-icons/fi'

const links = [
  { to: '/admin',            label: 'Dashboard',          icon: <FiHome />,      end: true },
  { type: 'divider', label: 'Management' },
  { to: '/admin/users',      label: 'User Management',    icon: <FiUsers /> },
  { to: '/admin/roles',      label: 'Role Management',    icon: <FiShield /> },
  { type: 'divider', label: 'System' },
  { to: '/admin/logs',       label: 'System Logs',        icon: <FiActivity /> },
  { to: '/admin/analytics',  label: 'Platform Analytics', icon: <FiBarChart2 /> },
  { to: '/admin/municipality', label: 'Municipality Config', icon: <FiMapPin /> },
]

export default function AdminLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <Navbar onToggleSidebar={() => setSidebarOpen(o => !o)} sidebarOpen={sidebarOpen} />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar
          links={links}
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          roleLabel="Admin Panel"
          roleColor="bg-red-50 text-red-700"
        />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}