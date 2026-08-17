import { useState, useEffect } from 'react'
import { userService } from '../../services/index'
import { LoadingSpinner, StatCard } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend, LineChart, Line, CartesianGrid } from 'recharts'
import { FiUsers, FiBriefcase, FiFileText, FiShield, FiAward } from 'react-icons/fi'

const COLORS = ['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6']

export default function PlatformAnalytics() {
  const [stats, setStats]   = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    userService.getDashboardStats()
      .then(res => { setStats(res.data.data); setLoading(false) })
      .catch(() => setLoading(false))
  }, [])

  if (loading) return <LoadingSpinner />

  const userPieData = [
    { name: 'Job Seekers', value: stats?.total_jobseekers  || 0 },
    { name: 'Employers',   value: stats?.total_employers   || 0 },
    { name: 'PESO',        value: 1 },
    { name: 'CLCDO',       value: 1 },
    { name: 'Admin',       value: 1 },
  ].filter(d => d.value > 0)

  const summaryBar = [
    { name: 'Jobseekers',   value: stats?.total_jobseekers   || 0 },
    { name: 'Employers',    value: stats?.total_employers    || 0 },
    { name: 'Jobs',         value: stats?.total_jobs         || 0 },
    { name: 'Applications', value: stats?.total_applications || 0 },
    { name: 'Trainings',    value: stats?.active_trainings   || 0 },
  ]

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Platform Analytics 📊</h1>
        <p className="page-subtitle">Full overview of the EmploySmart platform</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Users"      value={stats?.total_users}       icon={<FiUsers />}     color="bg-blue-50 text-blue-600" />
        <StatCard label="Verified"         value={stats?.verified_users}    icon={<FiShield />}    color="bg-green-50 text-green-600" />
        <StatCard label="Active Jobs"      value={stats?.total_jobs}        icon={<FiBriefcase />} color="bg-purple-50 text-purple-600" />
        <StatCard label="Applications"     value={stats?.total_applications} icon={<FiFileText />} color="bg-orange-50 text-orange-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-4">User Distribution</h3>
          <ResponsiveContainer width="100%" height={220}>
            <PieChart>
              <Pie data={userPieData} cx="50%" cy="50%" innerRadius={50} outerRadius={80} paddingAngle={3} dataKey="value">
                {userPieData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>

        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-4">Platform Summary</h3>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={summaryBar} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
              <XAxis dataKey="name" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Bar dataKey="value" fill="#4f46e5" radius={[4,4,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="card-flat">
        <h3 className="font-bold text-gray-800 mb-4">Verification Rate</h3>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
          {[
            { label: 'User Verification Rate', verified: stats?.verified_users, total: stats?.total_users, color: 'bg-blue-500' },
            { label: 'Employer Approval Rate', verified: (stats?.total_employers || 0) - (stats?.pending_employers || 0), total: stats?.total_employers, color: 'bg-green-500' },
          ].map(item => {
            const pct = item.total ? Math.round((item.verified / item.total) * 100) : 0
            return (
              <div key={item.label}>
                <div className="flex justify-between text-sm mb-2">
                  <span className="font-medium text-gray-700">{item.label}</span>
                  <span className="font-bold text-gray-900">{pct}%</span>
                </div>
                <div className="h-3 bg-gray-100 rounded-full overflow-hidden">
                  <div className={`h-full rounded-full ${item.color} transition-all duration-700`} style={{ width: `${pct}%` }} />
                </div>
                <p className="text-xs text-gray-400 mt-1">{item.verified} / {item.total} users</p>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}