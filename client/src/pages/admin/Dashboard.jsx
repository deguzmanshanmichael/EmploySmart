import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { userService } from '../../services/index'
import { StatCard, LoadingSpinner } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
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

  const roleData = Object.entries(stats?.role_counts || {}).map(([role, total]) => ({ name: role, total }))
  const pendingData = [
    { name: 'Job postings', total: stats?.pending_jobs || 0 },
    { name: 'Applications', total: stats?.pending_applications || 0 },
  ]
  const plural = (count, singular, pluralForm = `${singular}s`) => `${count || 0} ${count === 1 ? singular : pluralForm}`

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

      <div className="grid gap-5 lg:grid-cols-2">
        <div className="card-flat">
          <h2 className="font-bold text-gray-800">Accounts by role</h2>
          <p className="mt-1 text-sm text-gray-500">{plural(stats?.total_users, 'account')} currently visible in the platform.</p>
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={roleData} margin={{ top: 15, right: 10, left: -20, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="name" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Bar dataKey="total" fill="#0f766e" radius={[5, 5, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
        <div className="card-flat">
          <h2 className="font-bold text-gray-800">Pending workload</h2>
          <p className="mt-1 text-sm text-gray-500">Items requiring staff review before they can move forward.</p>
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={pendingData} margin={{ top: 15, right: 10, left: -20, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="name" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Bar dataKey="total" fill="#d97706" radius={[5, 5, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  )
}