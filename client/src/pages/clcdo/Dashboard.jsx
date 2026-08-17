import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { trainingService } from '../../services/index'
import { StatCard, LoadingSpinner } from '../../components/index'
import { FiBook, FiUsers, FiCheckCircle, FiClock } from 'react-icons/fi'

export default function ClcdoDashboard() {
  const [programs, setPrograms] = useState([])
  const [loading, setLoading]   = useState(true)

  useEffect(() => {
    const load = async () => {
      try {
        const res = await trainingService.getAll({ limit: 100 })
        setPrograms(res.data.data || [])
      } catch {}
      setLoading(false)
    }
    load()
  }, [])

  if (loading) return <LoadingSpinner />

  const stats = {
    total:     programs.length,
    upcoming:  programs.filter(p => p.status === 'upcoming').length,
    ongoing:   programs.filter(p => p.status === 'ongoing').length,
    completed: programs.filter(p => p.status === 'completed').length,
    enrolled:  programs.reduce((sum, p) => sum + (p.enrolled_count || 0), 0),
  }

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">CLCDO Dashboard 📚</h1>
        <p className="page-subtitle">City Livelihood and Community Development Office</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Programs"    value={stats.total}     icon={<FiBook />}        color="bg-blue-50 text-blue-600" />
        <StatCard label="Ongoing"           value={stats.ongoing}   icon={<FiClock />}       color="bg-yellow-50 text-yellow-600" />
        <StatCard label="Completed"         value={stats.completed} icon={<FiCheckCircle />} color="bg-green-50 text-green-600" />
        <StatCard label="Total Enrolled"    value={stats.enrolled}  icon={<FiUsers />}       color="bg-purple-50 text-purple-600" />
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {[
          { to: '/clcdo/create-training', icon: '➕', label: 'Create Program',     desc: 'Add a new training program' },
          { to: '/clcdo/programs',        icon: '📚', label: 'All Programs',       desc: 'View and manage programs' },
          { to: '/clcdo/enroll',          icon: '👤', label: 'Enroll Participant', desc: 'Add participants to programs' },
          { to: '/clcdo/completion',      icon: '✅', label: 'Mark Completion',    desc: 'Issue certificates' },
          { to: '/clcdo/reports',         icon: '📊', label: 'Training Reports',   desc: 'View statistics' },
        ].map(link => (
          <Link key={link.to} to={link.to}
            className="card flex flex-col p-4 hover:border-yellow-300 hover:shadow-md transition-all">
            <span className="text-3xl mb-2">{link.icon}</span>
            <span className="text-sm font-bold text-gray-800">{link.label}</span>
            <span className="text-xs text-gray-500 mt-0.5">{link.desc}</span>
          </Link>
        ))}
      </div>

      {programs.length > 0 && (
        <div>
          <h2 className="font-bold text-gray-800 mb-3">Recent Programs</h2>
          <div className="space-y-2">
            {programs.slice(0, 5).map(p => (
              <div key={p.id} className="card flex items-center justify-between">
                <div>
                  <p className="font-semibold text-gray-800">{p.program_name}</p>
                  <p className="text-xs text-gray-500">{p.location} · {p.enrolled_count || 0} enrolled</p>
                </div>
                <span className={`badge ${p.status === 'completed' ? 'badge-green' : p.status === 'ongoing' ? 'badge-blue' : 'badge-yellow'}`}>
                  {p.status}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}