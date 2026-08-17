import { useEffect, useMemo, useState } from 'react'
import { useAuth } from '../../context/AuthContext'
import { feedbackService, resumeRecommendationService, settingsService, skillService, trainingService } from '../../services/index'
import toast from 'react-hot-toast'
import { FiAward, FiBookOpen, FiMessageSquare, FiTrendingUp, FiUpload } from 'react-icons/fi'

export default function GrowthCenter() {
  const { user } = useAuth()
  const [municipality, setMunicipality] = useState(null)
  const [progression, setProgression] = useState(null)
  const [trainingHistory, setTrainingHistory] = useState([])
  const [recommendations, setRecommendations] = useState([])
  const [feedback, setFeedback] = useState('')
  const [rating, setRating] = useState(5)
  const [savingFeedback, setSavingFeedback] = useState(false)

  useEffect(() => {
    const load = async () => {
      try {
        const [settingsRes, progressionRes, recRes, trainingRes] = await Promise.all([
          settingsService.getMunicipality(),
          skillService.getProgression(user?.id),
          resumeRecommendationService.getForUser(user?.id),
          trainingService.getUserTrainings(user?.id),
        ])
        setMunicipality(settingsRes.data?.data || null)
        setProgression(progressionRes.data?.data || null)
        setTrainingHistory(trainingRes.data?.data || [])
        setRecommendations(recRes.data?.data?.recommendations || [])
      } catch (error) {
        console.error(error)
      }
    }

    if (user?.id) load()
  }, [user?.id])

  const completedTrainingCount = useMemo(() => {
    const fromTraining = trainingHistory.filter((item) => String(item?.status || '').toLowerCase() === 'completed').length
    if (fromTraining > 0) return fromTraining
    if (typeof progression?.completed_training_count === 'number') return progression.completed_training_count
    const items = Array.isArray(progression?.progression) ? progression.progression : []
    return items.filter((item) => String(item?.status || '').toLowerCase() === 'completed').length
  }, [trainingHistory, progression])

  const feedbackSummary = useMemo(() => {
    const count = recommendations.length
    return count > 0 ? `${count} recommendation${count > 1 ? 's' : ''} based on your uploaded profile details.` : 'Upload a resume to receive personalized recommendations.'
  }, [recommendations.length])

  const submitFeedback = async () => {
    if (!feedback.trim()) {
      toast.error('Please share a short feedback note')
      return
    }
    setSavingFeedback(true)
    try {
      await feedbackService.create({ target_type: 'jobseeker', target_id: user.id, rating, feedback })
      toast.success('Feedback shared with PESO')
      setFeedback('')
      setRating(5)
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to send feedback')
    } finally {
      setSavingFeedback(false)
    }
  }

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="page-header">
        <h1 className="page-title">Growth Center</h1>
        <p className="page-subtitle">Track your skills, resume-based opportunities, and feedback with PESO.</p>
      </div>

      <div className="card-flat bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <p className="text-sm uppercase tracking-wide opacity-80">Municipality service focus</p>
        <h2 className="text-2xl font-bold mt-1">{municipality?.municipality_name || 'EmploySmart Municipality'}</h2>
        <p className="mt-2 text-sm opacity-90">{municipality?.welcome_message || 'Welcome to the PESO employment portal.'}</p>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        <div className="card-flat space-y-4">
          <div className="flex items-center gap-2 text-blue-700 font-semibold">
            <FiTrendingUp /> Skill Progression
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            <div className="rounded-xl bg-blue-50 p-4">
              <p className="text-sm text-blue-700">Tracked skills</p>
              <p className="text-2xl font-bold text-blue-900">{progression?.skill_count || 0}</p>
            </div>
            <div className="rounded-xl bg-emerald-50 p-4">
              <p className="text-sm text-emerald-700">Completed trainings</p>
              <p className="text-2xl font-bold text-emerald-900">{completedTrainingCount}</p>
            </div>
          </div>
          <div className="space-y-2">
            {(progression?.progression || []).length === 0 ? (
              <p className="text-sm text-gray-500">Complete trainings to build a progression history.</p>
            ) : (
              (progression?.progression || []).slice(-4).map((item, idx) => (
                <div key={idx} className="flex items-center justify-between rounded-lg border border-gray-100 p-3 text-sm">
                  <span>{item.program_name}</span>
                  <span className="text-gray-500">{item.date}</span>
                </div>
              ))
            )}
          </div>
        </div>

        <div className="card-flat space-y-4">
          <div className="flex items-center gap-2 text-purple-700 font-semibold">
            <FiBookOpen /> Resume-Driven Recommendations
          </div>
          <p className="text-sm text-gray-600">{feedbackSummary}</p>
          {recommendations.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">
              <div className="flex items-center gap-2 mb-2 text-gray-700"><FiUpload /> Add a resume in your profile to generate recommendations.</div>
            </div>
          ) : (
            <div className="space-y-2">
              {recommendations.map((item) => (
                <div key={item.skill_id} className="rounded-lg border border-gray-100 p-3 text-sm flex items-center justify-between">
                  <span className="font-medium text-gray-800">{item.skill_name}</span>
                  <span className="text-xs text-purple-600">Suggested from resume</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="card-flat space-y-4">
        <div className="flex items-center gap-2 text-amber-700 font-semibold">
          <FiMessageSquare /> PESO & Employer Feedback
        </div>
        <p className="text-sm text-gray-600">Share feedback about placement support, training quality, or employer communication.</p>
        <div className="space-y-3">
          <div>
            <label className="label">Rating</label>
            <select className="input" value={rating} onChange={(e) => setRating(Number(e.target.value))}>
              {[5, 4, 3, 2, 1].map((value) => <option key={value} value={value}>{value} star{value > 1 ? 's' : ''}</option>)}
            </select>
          </div>
          <div>
            <label className="label">Feedback</label>
            <textarea className="input h-24 resize-none" value={feedback} onChange={(e) => setFeedback(e.target.value)} placeholder="Tell PESO about your experience, placements, or support needed." />
          </div>
          <button className="btn-primary" onClick={submitFeedback} disabled={savingFeedback}>
            {savingFeedback ? 'Sending...' : 'Submit Feedback'}
          </button>
        </div>
      </div>
    </div>
  )
}
