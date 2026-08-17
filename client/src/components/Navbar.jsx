import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useNotifications } from '../context/NotificationContext'
import { FiBell, FiMenu, FiX, FiLogOut, FiUser, FiChevronDown } from 'react-icons/fi'
import { InstallApp } from './InstallApp'
import { AppDownloadOptions } from './AppDownloadOptions'
import toast from 'react-hot-toast'
import { API_BASE_URL } from '../config/api.js'

const BASE_URL = API_BASE_URL

export default function Navbar({ onToggleSidebar, sidebarOpen }) {
  const { user, logout } = useAuth()
  const { unreadCount } = useNotifications()
  const navigate = useNavigate()
  const [dropdownOpen, setDropdownOpen] = useState(false)

  const roleLabels = {
    jobseeker: 'Job Seeker',
    employer:  'Employer',
    peso:      'PESO Officer',
    clcdo:     'CLCDO Staff',
    admin:     'Administrator',
  }

  const roleBadgeColor = {
    jobseeker: 'badge-blue',
    employer:  'badge-purple',
    peso:      'badge-green',
    clcdo:     'badge-yellow',
    admin:     'badge-red',
  }

  const profileRoute = {
    jobseeker: '/jobseeker/profile',
    employer:  '/employer/company',
    peso:      '#',
    clcdo:     '#',
    admin:     '#',
  }

  const handleLogout = async () => {
    await logout()
    toast.success('Logged out successfully')
    navigate('/login/user')
  }

  const avatar = user?.profile_picture
    ? `${BASE_URL}/${user.profile_picture}`
    : null

  return (
    <header className="sticky top-0 z-40 bg-white border-b border-gray-100 shadow-sm">
      <div className="flex items-center justify-between h-16 px-4 md:px-6">
        {/* Left: hamburger + brand */}
        <div className="flex items-center gap-3">
          <button
            onClick={onToggleSidebar}
            className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors lg:hidden"
            aria-label="Toggle sidebar"
          >
            {sidebarOpen ? <FiX size={20} /> : <FiMenu size={20} />}
          </button>
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center">
              <span className="text-white text-xs font-bold">ES</span>
            </div>
            <span className="font-display font-bold text-gray-900 text-lg hidden sm:block">
              EmploySmart
            </span>
          </div>
        </div>

        {/* Right: user menu */}
        <div className="flex items-center gap-2">
          <InstallApp />
          <AppDownloadOptions />
          <button
            onClick={() => navigate(user?.role ? `/${user.role}/notifications` : '/login/user' )}
            className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors relative"
            aria-label="View notifications"
          >
            <FiBell size={20} />
            {unreadCount > 0 && (
              <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-[10px] font-bold text-white flex items-center justify-center">
                {unreadCount > 9 ? '9+' : unreadCount}
              </span>
            )}
          </button>

          <div className="relative">
            <button
              onClick={() => setDropdownOpen(!dropdownOpen)}
              className="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                {avatar
                  ? <img src={avatar} alt="avatar" className="w-full h-full object-cover" />
                  : <span className="text-white text-xs font-bold">
                      {user?.name?.charAt(0).toUpperCase()}
                    </span>
                }
              </div>
              <div className="hidden md:flex flex-col items-start">
                <span className="text-sm font-semibold text-gray-800 leading-tight">
                  {user?.name}
                </span>
                <span className={`text-xs ${roleBadgeColor[user?.role]}`}>
                  {roleLabels[user?.role]}
                </span>
              </div>
              <FiChevronDown size={16} className="text-gray-400 hidden md:block" />
            </button>

            {dropdownOpen && (
              <>
                <div className="fixed inset-0 z-10" onClick={() => setDropdownOpen(false)} />
                <div className="absolute right-0 top-full mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-20 animate-slide-up">
                  <div className="px-4 py-2.5 border-b border-gray-50">
                    <p className="text-sm font-semibold text-gray-800">{user?.name}</p>
                    <p className="text-xs text-gray-500">{user?.email}</p>
                  </div>
                  {profileRoute[user?.role] !== '#' && (
                    <button
                      onClick={() => { navigate(profileRoute[user?.role]); setDropdownOpen(false) }}
                      className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                      <FiUser size={15} /> My Profile
                    </button>
                  )}
                  <button
                    onClick={handleLogout}
                    className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                  >
                    <FiLogOut size={15} /> Sign Out
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </header>
  )
}