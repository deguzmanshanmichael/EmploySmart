import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { userService } from '../../services/index'
import { StatCard, LoadingSpinner } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { FiUsers, FiBriefcase, FiShield, FiFileText, FiCheckSquare, FiClock } from 'react-icons/fi'

export default function PesoDashboard() {
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const load = async () => {
      try {
        const res = await userService.getDashboardStats()
        setStats(res.data.data)
      } catch {}
      setLoading(false)
    }
    load()
  }, [])

  if (loading) return <LoadingSpinner />

  const workQueueData = [
    { name: 'Pending jobs', total: stats?.pending_jobs || 0 },
    { name: 'Pending employers', total: stats?.pending_employers || 0 },
    { name: 'Pending applications', total: stats?.pending_applications || 0 },
  ]

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">PESO Dashboard 🏛️</h1>
        <p className="page-subtitle">Public Employment Service Office — Cabanatuan City</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Jobseekers"    value={stats?.total_jobseekers}   icon={<FiUsers />}       color="bg-blue-50 text-blue-600" />
        <StatCard label="Active Jobs"         value={stats?.total_jobs}         icon={<FiBriefcase />}   color="bg-green-50 text-green-600" />
        <StatCard label="Pending Employers"   value={stats?.pending_employers}  icon={<FiShield />}      color="bg-orange-50 text-orange-600" />
        <StatCard label="Total Applications"  value={stats?.total_applications} icon={<FiFileText />}    color="bg-purple-50 text-purple-600" />
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {[
          { to: '/peso/approve-jobs',          icon: '✅', label: 'Approve Jobs',       desc: 'Review pending job posts' },
          { to: '/peso/employer-verification', icon: '🛡️', label: 'Verify Employers',   desc: 'Approve employer accounts' },
          { to: '/peso/applicants',            icon: '👥', label: 'View Applicants',    desc: 'Monitor job applications' },
          { to: '/peso/jobs',                  icon: '📋', label: 'All Jobs',           desc: 'Manage all job listings' },
          { to: '/peso/reports',               icon: '📊', label: 'Reports',            desc: 'Generate PESO reports' },
        ].map(link => (
          <Link key={link.to} to={link.to}
            className="card flex flex-col p-4 hover:border-green-200 hover:shadow-md transition-all">
            <span className="text-3xl mb-2">{link.icon}</span>
            <span className="text-sm font-bold text-gray-800">{link.label}</span>
            <span className="text-xs text-gray-500 mt-0.5">{link.desc}</span>
          </Link>
        ))}
      </div>

      <div className="card-flat">
        <h2 className="font-bold text-gray-800">PESO review workload</h2>
        <p className="mt-1 text-sm text-gray-500">Current records waiting for employment-office review or action.</p>
        <ResponsiveContainer width="100%" height={230}>
          <BarChart data={workQueueData} margin={{ top: 18, right: 12, left: -20, bottom: 4 }}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
            <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
            <Tooltip formatter={(value) => [value, 'Records']} />
            <Bar dataKey="total" fill="#15803d" radius={[5, 5, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>

      {stats?.pending_employers > 0 && (
        <div className="alert-warning">
          <FiClock className="flex-shrink-0" />
          <div>
            <p className="font-bold">{stats.pending_employers} employer{stats.pending_employers > 1 ? 's' : ''} awaiting verification</p>
            <Link to="/peso/employer-verification" className="text-sm underline mt-0.5 inline-block">Review now →</Link>
          </div>
        </div>
      )}
    </div>
  )
}