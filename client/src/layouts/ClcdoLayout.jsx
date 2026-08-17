import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Navbar from '../components/Navbar'
import Sidebar from '../components/Sidebar'
import { FiHome, FiBook, FiPlusSquare, FiUserPlus, FiCheckCircle, FiFileText } from 'react-icons/fi'

const links = [
  { to: '/clcdo',                  label: 'Dashboard',         icon: <FiHome />,        end: true },
  { type: 'divider', label: 'Training' },
  { to: '/clcdo/programs',         label: 'Programs',          icon: <FiBook /> },
  { to: '/clcdo/create-training',  label: 'Create Program',    icon: <FiPlusSquare /> },
  { to: '/clcdo/enroll',           label: 'Enroll Participants', icon: <FiUserPlus /> },
  { to: '/clcdo/completion',       label: 'Mark Completion',   icon: <FiCheckCircle /> },
  { type: 'divider', label: 'Reports' },
  { to: '/clcdo/reports',          label: 'Training Reports',  icon: <FiFileText /> },
]

export default function ClcdoLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <Navbar onToggleSidebar={() => setSidebarOpen(o => !o)} sidebarOpen={sidebarOpen} />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar
          links={links}
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          roleLabel="CLCDO Office"
          roleColor="bg-yellow-50 text-yellow-700"
        />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}