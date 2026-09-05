import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import { employerService } from '../../services/index'
import { StatCard, LoadingSpinner } from '../../components/index'
import { Link } from 'react-router-dom'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { FiBriefcase, FiUsers, FiCheckCircle, FiClock, FiAlertCircle } from 'react-icons/fi'

export default function EmployerDashboard() {
  const { user } = useAuth()
  const [analytics, setAnalytics] = useState(null)
  const [employer, setEmployer]   = useState(null)
  const [loading, setLoading]     = useState(true)

  useEffect(() => {
    const load = async () => {
      try {
        const empRes = await employerService.getByUser(user.id)
        const emp = empRes.data.data
        setEmployer(emp)
        if (emp?.id) {
          const statsRes = await employerService.getAnalytics(emp.id)
          setAnalytics(statsRes.data.data)
        }
      } catch {}
      setLoading(false)
    }
    load()
  }, [user.id])

  if (loading) return <LoadingSpinner />

  const notApproved = employer?.verification_status !== 'approved'
  const applicationStatusData = analytics ? [
    { name: 'Pending', total: analytics.pending_applications || 0 },
    { name: 'Accepted', total: analytics.accepted_applications || 0 },
    { name: 'Declined', total: Math.max(0, (analytics.total_applications || 0) - (analytics.pending_applications || 0) - (analytics.accepted_applications || 0)) },
  ] : []

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Employer Dashboard 🏢</h1>
        <p className="page-subtitle">Welcome, {employer?.company_name || user?.name}</p>
      </div>

      {notApproved && (
        <div className="alert-warning">
          <FiAlertCircle className="flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-bold">Account Pending Verification</p>
            <p className="text-sm mt-0.5">Your employer account is under review by PESO. You can prepare your company profile while waiting.</p>
          </div>
        </div>
      )}

      {analytics && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard label="Total Jobs Posted" value={analytics.total_jobs}           icon={<FiBriefcase />} color="bg-blue-50 text-blue-600" />
          <StatCard label="Approved Jobs"      value={analytics.approved_jobs}        icon={<FiCheckCircle />} color="bg-green-50 text-green-600" />
          <StatCard label="Total Applications" value={analytics.total_applications}   icon={<FiUsers />} color="bg-purple-50 text-purple-600" />
          <StatCard label="Pending Review"     value={analytics.pending_applications} icon={<FiClock />} color="bg-yellow-50 text-yellow-600" />
        </div>
      )}

      {analytics && (
        <div className="card-flat">
          <h2 className="font-bold text-gray-800">Application pipeline</h2>
          <p className="mt-1 text-sm text-gray-500">A live view of how candidate applications are progressing across your jobs.</p>
          <ResponsiveContainer width="100%" height={230}>
            <BarChart data={applicationStatusData} margin={{ top: 18, right: 12, left: -20, bottom: 4 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="name" tick={{ fontSize: 12 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
              <Tooltip formatter={(value) => [value, 'Applications']} />
              <Bar dataKey="total" fill="#4f46e5" radius={[5, 5, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { to: '/employer/create-job', icon: '➕', label: 'Post a Job',       disabled: notApproved },
          { to: '/employer/jobs',       icon: '📋', label: 'Manage Jobs' },
          { to: '/employer/applicants', icon: '👥', label: 'View Applicants' },
          { to: '/employer/company',    icon: '🏢', label: 'Company Profile' },
        ].map(link => (
          <Link
            key={link.to}
            to={link.disabled ? '#' : link.to}
            className={`card flex flex-col items-center text-center p-4 transition-all ${link.disabled ? 'opacity-50 cursor-not-allowed' : 'hover:border-blue-200 hover:shadow-md'}`}
          >
            <span className="text-3xl mb-2">{link.icon}</span>
            <span className="text-xs font-semibold text-gray-700">{link.label}</span>
            {link.disabled && <span className="text-xs text-orange-500 mt-0.5">Requires verification</span>}
          </Link>
        ))}
      </div>
    </div>
  )
}