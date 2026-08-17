import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import { employerService } from '../../services/index'
import { jobService } from '../../services/jobService'
import { applicationService } from '../../services/index'
import { StatCard, LoadingSpinner, EmptyState } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend } from 'recharts'
import { FiBriefcase, FiUsers, FiCheckCircle, FiClock } from 'react-icons/fi'

const COLORS = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444']

const normalizeApprovalStatus = status => status === 'rejected' ? 'declined' : status

export default function JobAnalytics() {
  const { user }  = useAuth()
  const [analytics, setAnalytics] = useState(null)
  const [jobs, setJobs]           = useState([])
  const [loading, setLoading]     = useState(true)

  useEffect(() => {
    const load = async () => {
      try {
        const eRes = await employerService.getByUser(user.id)
        const emp  = eRes.data.data
        if (emp?.id) {
          const [statsRes, jobsRes] = await Promise.all([
            employerService.getAnalytics(emp.id),
            jobService.getByEmployer(emp.id, { limit: 100 }),
          ])
          setAnalytics(statsRes.data.data)
          setJobs(jobsRes.data.data || [])
        }
      } catch {}
      setLoading(false)
    }
    load()
  }, [user.id])

  if (loading) return <LoadingSpinner />
  if (!analytics) return <EmptyState icon="📊" title="No analytics yet" description="Post jobs to start seeing analytics" />

  const pieData = [
    { name: 'Pending',  value: analytics.pending_applications  || 0 },
    { name: 'Accepted', value: analytics.accepted_applications || 0 },
    { name: 'Declined', value: (analytics.total_applications || 0) - (analytics.accepted_applications || 0) - (analytics.pending_applications || 0) },
  ].filter(d => d.value > 0)

  const barData = jobs.slice(0, 8).map(j => ({
    name: j.title.length > 15 ? j.title.slice(0, 15) + '…' : j.title,
    vacancies: j.vacancies || 1,
    status: j.approval_status,
  }))

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Job Analytics 📊</h1>
        <p className="page-subtitle">Performance overview of your job postings</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Posted"       value={analytics.total_jobs}           icon={<FiBriefcase />}   color="bg-blue-50 text-blue-600" />
        <StatCard label="Approved"           value={analytics.approved_jobs}        icon={<FiCheckCircle />} color="bg-green-50 text-green-600" />
        <StatCard label="Total Applications" value={analytics.total_applications}   icon={<FiUsers />}       color="bg-purple-50 text-purple-600" />
        <StatCard label="Pending Review"     value={analytics.pending_applications} icon={<FiClock />}       color="bg-yellow-50 text-yellow-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-4">Applications Breakdown</h3>
          {pieData.length === 0 ? (
            <p className="text-sm text-gray-400 text-center py-8">No application data yet</p>
          ) : (
            <ResponsiveContainer width="100%" height={220}>
              <PieChart>
                <Pie data={pieData} cx="50%" cy="50%" innerRadius={55} outerRadius={85} paddingAngle={3} dataKey="value">
                  {pieData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          )}
        </div>

        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-4">Job Vacancies</h3>
          {barData.length === 0 ? (
            <p className="text-sm text-gray-400 text-center py-8">No jobs yet</p>
          ) : (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={barData} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
                <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip />
                <Bar dataKey="vacancies" fill="#3b82f6" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </div>
      </div>

      <div className="card-flat">
        <h3 className="font-bold text-gray-800 mb-3">Jobs Summary</h3>
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr>
                <th>Job Title</th>
                <th>Type</th>
                <th>Vacancies</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {jobs.map(job => (
                <tr key={job.id}>
                  <td className="font-semibold">{job.title}</td>
                  <td className="capitalize">{job.job_type}</td>
                  <td>{job.vacancies}</td>
                  <td>
                    <span className={`badge ${job.approval_status === 'approved' ? 'badge-green' : job.approval_status === 'rejected' ? 'badge-red' : 'badge-yellow'}`}>
                      {normalizeApprovalStatus(job.approval_status)}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}