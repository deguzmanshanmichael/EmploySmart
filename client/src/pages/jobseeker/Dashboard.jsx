import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { jobService } from '../../services/jobService'
import { applicationService, matchService, skillService } from '../../services/index'
import { StatCard, JobCard, LoadingSpinner, EmptyState, Modal } from '../../components/index'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { FiBriefcase, FiFileText, FiAward, FiStar } from 'react-icons/fi'
import toast from 'react-hot-toast'

export default function JobSeekerDashboard() {
  const { user } = useAuth()
  const [stats, setStats] = useState({ jobs: 0, applications: 0, skills: 0, recommended: 0 })
  const [recommended, setRecommended] = useState([])
  const [applications, setApplications] = useState([])
  const [loading, setLoading] = useState(true)
  const [applying, setApplying] = useState(null)
  const [appliedIds, setAppliedIds] = useState(new Set())
  const [applyModalJob, setApplyModalJob] = useState(null)
  const [applicationMessage, setApplicationMessage] = useState('')
  const [selectedJob, setSelectedJob] = useState(null)

  useEffect(() => {
    const load = async () => {
      if (!localStorage.getItem('access_token')) {
        setAppliedIds(new Set())
        setLoading(false)
        return
      }
      try {
        const [jobsRes, appsRes, matchRes, skillRes] = await Promise.allSettled([
          jobService.getAll({ limit: 5 }),
          applicationService.getMyApplications({ limit: 100 }),
          matchService.getRecommended(),
          skillService.getUserSkills(user.id),
        ])
        if (jobsRes.status === 'fulfilled') setStats(s => ({ ...s, jobs: jobsRes.value.data.pagination?.total || 0 }))
        if (appsRes.status === 'fulfilled') {
          const apps = appsRes.value.data.data || []
          setApplications(apps)
          setStats(s => ({ ...s, applications: apps.length }))
          setAppliedIds(new Set(apps.map(a => a.job_id)))
        }
        if (matchRes.status === 'fulfilled') {
          const allRecommended = matchRes.value.data.data || []
          setStats(s => ({ ...s, recommended: allRecommended.length }))
          setRecommended(allRecommended.slice(0, 4))
        }
        if (skillRes.status === 'fulfilled') {
          setStats(s => ({ ...s, skills: (skillRes.value.data.data || []).length }))
        }
      } catch {}
      setLoading(false)
    }
    load()
  }, [user?.id])

  const handleApply = (job) => {
    setApplyModalJob(job)
    setApplicationMessage('')
  }

  const handleViewDetails = (job) => {
    setSelectedJob(job)
  }

  const applicationStatusData = ['pending', 'reviewed', 'accepted', 'rejected', 'withdrawn']
    .map(status => ({ name: status, total: applications.filter(application => application.application_status === status).length }))
    .filter(item => item.total > 0)

  const submitApplication = async () => {
    if (!applyModalJob) return

    const message = applicationMessage.trim()
    if (!message) {
      toast.error('Please enter a short note for the employer.')
      return
    }

    setApplying(applyModalJob.id)
    try {
      await applicationService.apply({ job_id: applyModalJob.id, message })
      setAppliedIds(s => new Set([...s, applyModalJob.id]))
      toast.success('Your application has been sent to the hiring team.')
      setApplyModalJob(null)
      setApplicationMessage('')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to apply')
    } finally {
      setApplying(null)
    }
  }

  if (loading) return <LoadingSpinner text="Loading your dashboard..." />

  return (
    <div className="animate-fade-in space-y-6">
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title">Welcome back, {user?.first_name}! 👋</h1>
        <p className="page-subtitle">Here's a summary of your job search activity</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Available Jobs" value={stats.jobs} icon={<FiBriefcase />} color="bg-blue-50 text-blue-600" />
        <StatCard label="Applications" value={stats.applications} icon={<FiFileText />} color="bg-green-50 text-green-600" />
        <StatCard label="Recommended" value={stats.recommended} icon={<FiStar />} color="bg-yellow-50 text-yellow-600" />
        <StatCard label="My Skills" value={stats.skills} icon={<FiAward />} color="bg-purple-50 text-purple-600" />
      </div>

      {/* Recommended Jobs */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-gray-900">⭐ Recommended For You</h2>
          <Link to="/jobseeker/recommended" className="text-sm text-blue-600 font-semibold hover:underline">
            View all →
          </Link>
        </div>
        {recommended.length === 0 ? (
          <EmptyState
            icon="🎯"
            title="No recommendations yet"
            description="Update your skills to get personalized job recommendations"
            action={<Link to="/jobseeker/skills" className="btn-primary btn-sm">Add Skills</Link>}
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {recommended.map(job => (
              <JobCard
                key={job.id}
                job={job}
                matchScore={job.match_score}
                applied={appliedIds.has(job.id)}
                onApply={applying === job.id ? null : handleApply}
                onView={handleViewDetails}
              />
            ))}
          </div>
        )}
      </div>

      <div className="card-flat">
        <h2 className="font-bold text-gray-800">My application progress</h2>
        <p className="mt-1 text-sm text-gray-500">Track the current status of every application you have submitted.</p>
        {applicationStatusData.length === 0 ? <p className="py-10 text-center text-sm text-gray-400">No applications yet</p> : (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={applicationStatusData} margin={{ top: 18, right: 12, left: -20, bottom: 4 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="name" tick={{ fontSize: 12 }} tickFormatter={value => value.replace('_', ' ')} />
              <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
              <Tooltip formatter={(value) => [value, 'Applications']} />
              <Bar dataKey="total" fill="#0f766e" radius={[5, 5, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </div>

      {selectedJob && (
        <Modal
          title={selectedJob.title}
          onClose={() => setSelectedJob(null)}
          size="lg"
          footer={
            <div className="flex gap-3">
              <button onClick={() => setSelectedJob(null)} className="btn-secondary">Close</button>
              <button
                onClick={() => { handleApply(selectedJob); setSelectedJob(null); }}
                disabled={appliedIds.has(selectedJob.id)}
                className={appliedIds.has(selectedJob.id) ? 'btn bg-green-100 text-green-700' : 'btn-primary'}
              >
                {appliedIds.has(selectedJob.id) ? '✓ Applied' : 'Apply Now'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
              {selectedJob.job_type && <span className="badge-blue capitalize">{selectedJob.job_type}</span>}
              {selectedJob.experience_level && <span className="badge-gray capitalize">{selectedJob.experience_level} level</span>}
              {selectedJob.salary_range && <span className="badge-green">💰 {selectedJob.salary_range}</span>}
            </div>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="font-semibold text-gray-600">Company:</span> <span>{selectedJob.company_name}</span></div>
              <div><span className="font-semibold text-gray-600">Location:</span> <span>{selectedJob.location}</span></div>
              <div><span className="font-semibold text-gray-600">Vacancies:</span> <span>{selectedJob.vacancies}</span></div>
              {selectedJob.deadline && <div><span className="font-semibold text-gray-600">Deadline:</span> <span>{new Date(selectedJob.deadline).toLocaleDateString()}</span></div>}
            </div>
            {selectedJob.description && (
              <div>
                <h4 className="font-bold text-gray-800 mb-1">Description</h4>
                <p className="text-sm text-gray-600 whitespace-pre-line">{selectedJob.description}</p>
              </div>
            )}
            {selectedJob.requirements && (
              <div>
                <h4 className="font-bold text-gray-800 mb-1">Requirements</h4>
                <p className="text-sm text-gray-600 whitespace-pre-line">{selectedJob.requirements}</p>
              </div>
            )}
            {selectedJob.skills?.length > 0 && (
              <div>
                <h4 className="font-bold text-gray-800 mb-2">Required Skills</h4>
                <div className="flex flex-wrap gap-2">
                  {selectedJob.skills.map(s => (
                    <span key={s.id} className="badge-blue">{s.skill_name}</span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </Modal>
      )}

      {applyModalJob && (
        <Modal
          title={`Apply to ${applyModalJob.title}`}
          onClose={() => setApplyModalJob(null)}
          size="md"
          footer={
            <div className="flex gap-3">
              <button onClick={() => setApplyModalJob(null)} className="btn-secondary">Cancel</button>
              <button onClick={submitApplication} className="btn-primary" disabled={applying === applyModalJob.id}>
                {applying === applyModalJob.id ? 'Submitting...' : 'Submit Application'}
              </button>
            </div>
          }
        >
          <div className="space-y-3">
            <div className="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
              Share a short note with the employer before you submit your application.
            </div>
            <label className="label">Employer's Note</label>
            <textarea
              className="input min-h-[120px]"
              value={applicationMessage}
              onChange={(e) => setApplicationMessage(e.target.value)}
              placeholder="Introduce yourself and explain why you are a strong fit for this role..."
            />
          </div>
        </Modal>
      )}

      {/* Quick links */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { to: '/jobseeker/jobs',         icon: '🔍', label: 'Browse All Jobs' },
          { to: '/jobseeker/applications', icon: '📄', label: 'My Applications' },
          { to: '/jobseeker/skills',       icon: '🎓', label: 'Manage Skills' },
          { to: '/jobseeker/trainings',    icon: '📚', label: 'Training History' },
        ].map(link => (
          <Link
            key={link.to}
            to={link.to}
            className="card flex flex-col items-center text-center p-4 hover:border-blue-200 hover:shadow-md transition-all"
          >
            <span className="text-3xl mb-2">{link.icon}</span>
            <span className="text-xs font-semibold text-gray-700">{link.label}</span>
          </Link>
        ))}
      </div>
    </div>
  )
}