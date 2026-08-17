import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Navbar from '../components/Navbar'
import Sidebar from '../components/Sidebar'
import { FiHome, FiSearch, FiStar, FiFileText, FiAward, FiBook, FiUser, FiTrendingUp } from 'react-icons/fi'

const links = [
  { to: '/jobseeker',             label: 'Dashboard',        icon: <FiHome />,     end: true },
  { type: 'divider', label: 'Jobs' },
  { to: '/jobseeker/jobs',        label: 'Browse Jobs',      icon: <FiSearch /> },
  { to: '/jobseeker/recommended', label: 'Recommended',      icon: <FiStar /> },
  { to: '/jobseeker/applications',label: 'My Applications',  icon: <FiFileText /> },
  { type: 'divider', label: 'Growth' },
  { to: '/jobseeker/skills',      label: 'My Skills',        icon: <FiAward /> },
  { to: '/jobseeker/growth',       label: 'Growth Center',    icon: <FiTrendingUp /> },
  { to: '/jobseeker/trainings',   label: 'Training History', icon: <FiBook /> },
  { type: 'divider', label: 'Account' },
  { to: '/jobseeker/profile',     label: 'My Profile',       icon: <FiUser /> },
]

export default function JobSeekerLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <Navbar onToggleSidebar={() => setSidebarOpen(o => !o)} sidebarOpen={sidebarOpen} />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar
          links={links}
          open={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          roleLabel="Job Seeker Portal"
          roleColor="bg-blue-50 text-blue-700"
        />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}