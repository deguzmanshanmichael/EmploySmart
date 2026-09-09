import { useState, useEffect } from 'react'
import { trainingService } from '../../services/index'
import { LoadingSpinner, StatCard } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts'
import { FiBook, FiUsers, FiCheckCircle, FiClock } from 'react-icons/fi'
import { FiPrinter } from 'react-icons/fi'
import { printReport } from '../../utils/printReport'

export default function TrainingReports() {
  const [programs, setPrograms] = useState([])
  const [loading, setLoading]   = useState(true)

  useEffect(() => {
    trainingService.getAll({ limit: 100 }).then(res => {
      setPrograms(res.data.data || [])
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [])

  if (loading) return <LoadingSpinner />

  const stats = {
    total:     programs.length,
    upcoming:  programs.filter(p => p.status === 'upcoming').length,
    ongoing:   programs.filter(p => p.status === 'ongoing').length,
    completed: programs.filter(p => p.status === 'completed').length,
    enrolled:  programs.reduce((sum, p) => sum + (p.enrolled_count || 0), 0),
  }

  const chartData = programs.map(p => ({
    name: p.program_name.length > 18 ? p.program_name.slice(0, 18) + '…' : p.program_name,
    enrolled: p.enrolled_count || 0,
    max: p.max_participants || 0,
  }))

  const printReportView = () => {
    if (!printReport({
      title: 'CLCDO Training Report',
      subtitle: 'Training programs, enrollment, and capacity overview',
      summary: `${programs.length} training programs · ${stats.enrolled} total enrolled participants`,
      tableHeaders: ['Program', 'Location', 'Status', 'Enrolled', 'Capacity'],
      tableRows: programs.map(p => [p.program_name, p.location || '—', p.status, p.enrolled_count || 0, p.max_participants || 'Unlimited']),
    })) return
  }

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <div className="flex items-start justify-between gap-3"><div><h1 className="page-title">Training Reports 📊</h1><p className="page-subtitle">Overview of all CLCDO training programs</p></div><button onClick={printReportView} className="btn-secondary btn-sm"><FiPrinter /> Print Report</button></div>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Programs" value={stats.total}     icon={<FiBook />}        color="bg-blue-50 text-blue-600" />
        <StatCard label="Upcoming"       value={stats.upcoming}  icon={<FiClock />}       color="bg-yellow-50 text-yellow-600" />
        <StatCard label="Ongoing"        value={stats.ongoing}   icon={<FiClock />}       color="bg-orange-50 text-orange-600" />
        <StatCard label="Completed"      value={stats.completed} icon={<FiCheckCircle />} color="bg-green-50 text-green-600" />
      </div>

      <div className="card-flat">
        <h3 className="font-bold text-gray-800 mb-4">Enrollment per Program</h3>
        {chartData.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-8">No data yet</p>
        ) : (
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={chartData} margin={{ top: 5, right: 10, left: -20, bottom: 40 }}>
              <XAxis dataKey="name" tick={{ fontSize: 11 }} angle={-30} textAnchor="end" />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Bar dataKey="enrolled" name="Enrolled" fill="#f59e0b" radius={[4,4,0,0]} />
              <Bar dataKey="max" name="Capacity" fill="#e5e7eb" radius={[4,4,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </div>

      <div className="card-flat">
        <h3 className="font-bold text-gray-800 mb-3">All Programs</h3>
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr><th>Program</th><th>Location</th><th>Status</th><th>Enrolled</th><th>Capacity</th></tr>
            </thead>
            <tbody>
              {programs.map(p => (
                <tr key={p.id}>
                  <td className="font-semibold">{p.program_name}</td>
                  <td>{p.location}</td>
                  <td>
                    <span className={`badge ${p.status === 'completed' ? 'badge-green' : p.status === 'ongoing' ? 'badge-blue' : 'badge-yellow'}`}>
                      {p.status}
                    </span>
                  </td>
                  <td>{p.enrolled_count || 0}</td>
                  <td>{p.max_participants || '∞'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}