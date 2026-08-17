import { useState, useEffect } from 'react'
import { userService } from '../../services/index'
import { LoadingSpinner, StatCard } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, LineChart, Line, CartesianGrid } from 'recharts'
import { FiUsers, FiBriefcase, FiFileText, FiAward } from 'react-icons/fi'

export default function Reports() {
  const [stats, setStats]   = useState(null)
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

  const summaryData = [
    { label: 'Jobseekers',   value: stats?.total_jobseekers   || 0 },
    { label: 'Employers',    value: stats?.total_employers    || 0 },
    { label: 'Active Jobs',  value: stats?.total_jobs         || 0 },
    { label: 'Applications', value: stats?.total_applications || 0 },
  ]

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">PESO Reports 📊</h1>
        <p className="page-subtitle">Employment statistics and system overview</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Jobseekers"   value={stats?.total_jobseekers}    icon={<FiUsers />}     color="bg-blue-50 text-blue-600" />
        <StatCard label="Active Jobs"        value={stats?.total_jobs}          icon={<FiBriefcase />} color="bg-green-50 text-green-600" />
        <StatCard label="Applications Filed" value={stats?.total_applications}  icon={<FiFileText />}  color="bg-purple-50 text-purple-600" />
        <StatCard label="Active Trainings"   value={stats?.active_trainings}    icon={<FiAward />}     color="bg-yellow-50 text-yellow-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-4">System Summary</h3>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={summaryData} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
              <XAxis dataKey="label" tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip />
              <Bar dataKey="value" fill="#1d4ed8" radius={[4,4,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-4">Verification Status</h3>
          <div className="space-y-3">
            {[
              { label: 'Verified Users',        value: stats?.verified_users,    color: 'bg-green-500', total: stats?.total_users },
              { label: 'Pending Employers',      value: stats?.pending_employers, color: 'bg-yellow-500', total: stats?.total_employers },
            ].map(item => (
              <div key={item.label}>
                <div className="flex justify-between text-sm mb-1">
                  <span className="font-medium text-gray-700">{item.label}</span>
                  <span className="text-gray-500">{item.value} / {item.total}</span>
                </div>
                <div className="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                  <div
                    className={`h-full rounded-full ${item.color} transition-all duration-700`}
                    style={{ width: item.total ? `${Math.min(100,(item.value/item.total)*100)}%` : '0%' }}
                  />
                </div>
              </div>
            ))}
          </div>

          <div className="mt-6 grid grid-cols-2 gap-3 text-center">
            {[
              { label: 'Verified Users',    value: stats?.verified_users,    color: 'text-green-600' },
              { label: 'Pending Employers', value: stats?.pending_employers, color: 'text-yellow-600' },
              { label: 'Total Employers',   value: stats?.total_employers,   color: 'text-blue-600' },
              { label: 'Active Trainings',  value: stats?.active_trainings,  color: 'text-purple-600' },
            ].map(item => (
              <div key={item.label} className="bg-gray-50 rounded-xl p-3">
                <p className={`text-2xl font-bold ${item.color}`}>{item.value ?? 0}</p>
                <p className="text-xs text-gray-500 mt-0.5">{item.label}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}