// TrainingHistory.jsx
import { useState, useEffect } from 'react'
import { trainingService } from '../../services/index'
import { useAuth } from '../../context/AuthContext'
import { LoadingSpinner, EmptyState } from '../../components/index'
import toast from 'react-hot-toast'
import { format } from 'date-fns'
import { API_BASE_URL } from '../../config/api.js'

export default function TrainingHistory() {
  const { user } = useAuth()
  const [myTrainings, setMyTrainings] = useState([])
  const [availableTrainings, setAvailableTrainings] = useState([])
  const [loading, setLoading] = useState(true)
  const BASE_URL = API_BASE_URL

  useEffect(() => {
    const load = async () => {
      if (!user?.id) return

      try {
        const [myRes, availableRes] = await Promise.all([
          trainingService.getUserTrainings(user.id),
          trainingService.getAll({ page: 1, limit: 100 }),
        ])

        setMyTrainings(myRes.data?.data || [])
        setAvailableTrainings(availableRes.data?.data || [])
      } catch (error) {
        console.error(error)
        toast.error('Failed to load training records')
      } finally {
        setLoading(false)
      }
    }

    load()
  }, [user?.id])

  const statusBadge = {
    enrolled: 'badge-blue',
    completed: 'badge-green',
    dropped: 'badge-red',
    upcoming: 'badge-yellow',
    ongoing: 'badge-blue',
  }

  const getProgress = (training) => {
    const status = training?.status || training?.training_status || 'upcoming'

    if (status === 'completed') return { percent: 100, label: 'Completed' }
    if (status === 'dropped') return { percent: 0, label: 'Dropped' }
    if (status === 'enrolled') return { percent: 55, label: 'In Progress' }
    if (status === 'ongoing') return { percent: 75, label: 'Ongoing' }
    if (status === 'upcoming') return { percent: 20, label: 'Upcoming' }

    if (training?.program_status === 'completed') return { percent: 100, label: 'Completed' }
    if (training?.program_status === 'ongoing') return { percent: 75, label: 'Ongoing' }
    if (training?.program_status === 'upcoming') return { percent: 20, label: 'Upcoming' }

    return { percent: 0, label: 'Not started' }
  }

  const enrolledMap = new Map(myTrainings.map((training) => [training.training_id || training.id, training]))
  const availableList = availableTrainings.filter((training) => !enrolledMap.has(training.id))

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Training Dashboard 📚</h1>
        <p className="page-subtitle">Track your progress and explore available training programs.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="card-flat bg-blue-50 border border-blue-100">
          <p className="text-sm text-blue-700">My trainings</p>
          <p className="text-2xl font-bold text-blue-900">{myTrainings.length}</p>
        </div>
        <div className="card-flat bg-emerald-50 border border-emerald-100">
          <p className="text-sm text-emerald-700">Completed</p>
          <p className="text-2xl font-bold text-emerald-900">{myTrainings.filter((t) => t.status === 'completed').length}</p>
        </div>
        <div className="card-flat bg-violet-50 border border-violet-100">
          <p className="text-sm text-violet-700">Available</p>
          <p className="text-2xl font-bold text-violet-900">{availableList.length}</p>
        </div>
      </div>

      <div className="card-flat space-y-4">
        <div className="flex items-center justify-between gap-3">
          <h2 className="font-bold text-gray-900">My Training Progress</h2>
        </div>

        {myTrainings.length === 0 ? (
          <EmptyState icon="📚" title="No training records yet" description="You will see your active and completed trainings here." />
        ) : (
          <div className="space-y-4">
            {myTrainings.map((training) => {
              const progress = getProgress(training)
              return (
                <div key={training.id || training.training_id} className="rounded-xl border border-gray-200 bg-gray-50 p-4">
                  <div className="flex items-center justify-between gap-3 mb-2">
                    <div>
                      <h3 className="font-bold text-gray-900">{training.program_name}</h3>
                      <p className="text-xs text-gray-500">{training.location}</p>
                    </div>
                    <span className={statusBadge[training.status] || 'badge-gray'}>{training.status}</span>
                  </div>

                  <div className="mb-2">
                    <div className="flex items-center justify-between text-xs text-gray-600 mb-1">
                      <span>Progress</span>
                      <span>{progress.label}</span>
                    </div>
                    <div className="h-2.5 rounded-full bg-gray-200 overflow-hidden">
                      <div className="h-full rounded-full bg-gradient-to-r from-blue-500 to-emerald-500 transition-all" style={{ width: `${progress.percent}%` }} />
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    {training.start_date && <span>📅 {format(new Date(training.start_date), 'MMM d, yyyy')}</span>}
                    {training.end_date && <span>– {format(new Date(training.end_date), 'MMM d, yyyy')}</span>}
                    {training.completion_date && <span>✅ Completed: {format(new Date(training.completion_date), 'MMM d, yyyy')}</span>}
                  </div>

                  {training.certificate_path && (
                    <div className="mt-3">
                      <a href={`${BASE_URL}/${training.certificate_path}`} target="_blank" rel="noopener noreferrer" className="btn-success btn-sm inline-flex">
                        📜 View Certificate
                      </a>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}
      </div>

      <div className="card-flat space-y-4">
        <div className="flex items-center justify-between gap-3">
          <h2 className="font-bold text-gray-900">Available Trainings</h2>
        </div>

        {availableList.length === 0 ? (
          <EmptyState icon="🎯" title="No available trainings" description="There are no open training programs at the moment." />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {availableList.map((training) => (
              <div key={training.id} className="rounded-xl border border-gray-200 bg-white p-4">
                <div className="flex items-start justify-between gap-3 mb-2">
                  <h3 className="font-bold text-gray-900">{training.program_name}</h3>
                  <span className={statusBadge[training.status] || 'badge-gray'}>{training.status}</span>
                </div>

                <p className="text-sm text-gray-600 mb-3 line-clamp-3">{training.description || 'No description provided.'}</p>

                <div className="text-xs text-gray-500 space-y-1 mb-3">
                  <p>📍 {training.location}</p>
                  {training.start_date && <p>📅 {format(new Date(training.start_date), 'MMM d, yyyy')} {training.end_date ? `– ${format(new Date(training.end_date), 'MMM d, yyyy')}` : ''}</p>}
                  {training.max_participants && <p>👥 Capacity: {training.max_participants}</p>}
                </div>

                {training.skills?.length > 0 && (
                  <div className="flex flex-wrap gap-1.5 mb-3">
                    {training.skills.slice(0, 5).map((skill) => (
                      <span key={skill.id} className="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">
                        {skill.skill_name}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}