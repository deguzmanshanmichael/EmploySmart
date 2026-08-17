import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { userService } from '../../services/index'
import { StatCard, LoadingSpinner } from '../../components/index'
import { FiUsers, FiBriefcase, FiFileText, FiShield, FiActivity, FiAward } from 'react-icons/fi'

export default function AdminDashboard() {
  const [stats, setStats]   = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    userService.getDashboardStats()
      .then(res => { setStats(res.data.data); setLoading(false) })
      .catch(() => setLoading(false))
  }, [])

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Admin Dashboard ⚙️</h1>
        <p className="page-subtitle">EmploySmart Platform Control Center</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Users"       value={stats?.total_users}       icon={<FiUsers />}     color="bg-blue-50 text-blue-600" />
        <StatCard label="Job Seekers"       value={stats?.total_jobseekers}  icon={<FiUsers />}     color="bg-indigo-50 text-indigo-600" />
        <StatCard label="Employers"         value={stats?.total_employers}   icon={<FiShield />}    color="bg-purple-50 text-purple-600" />
        <StatCard label="Active Jobs"       value={stats?.total_jobs}        icon={<FiBriefcase />} color="bg-green-50 text-green-600" />
        <StatCard label="Applications"      value={stats?.total_applications} icon={<FiFileText />} color="bg-orange-50 text-orange-600" />
        <StatCard label="Verified Users"    value={stats?.verified_users}    icon={<FiShield />}    color="bg-teal-50 text-teal-600" />
        <StatCard label="Pending Employers" value={stats?.pending_employers} icon={<FiActivity />}  color="bg-yellow-50 text-yellow-600" />
        <StatCard label="Active Trainings"  value={stats?.active_trainings}  icon={<FiAward />}     color="bg-pink-50 text-pink-600" />
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { to: '/admin/users',     icon: '👥', label: 'Manage Users',     desc: 'View, verify, archive users' },
          { to: '/admin/roles',     icon: '🛡️', label: 'Role Management',  desc: 'Manage staff accounts' },
          { to: '/admin/logs',      icon: '📋', label: 'System Logs',      desc: 'Audit trail' },
          { to: '/admin/analytics', icon: '📊', label: 'Analytics',        desc: 'Platform metrics' },
        ].map(link => (
          <Link key={link.to} to={link.to}
            className="card flex flex-col p-4 hover:border-red-200 hover:shadow-md transition-all">
            <span className="text-3xl mb-2">{link.icon}</span>
            <span className="text-sm font-bold text-gray-800">{link.label}</span>
            <span className="text-xs text-gray-500 mt-0.5">{link.desc}</span>
          </Link>
        ))}
      </div>
    </div>
  )
}