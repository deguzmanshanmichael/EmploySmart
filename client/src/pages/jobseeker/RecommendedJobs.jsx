// RecommendedJobs.jsx
import { useState, useEffect } from 'react'
import { matchService, applicationService, skillService } from '../../services/index'
import { JobCard, LoadingSpinner, EmptyState, Modal } from '../../components/index'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'

export function RecommendedJobs() {
  const [jobs, setJobs]           = useState([])
  const [loading, setLoading]     = useState(true)
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
        const [matchRes, appsRes] = await Promise.allSettled([
          matchService.getRecommended(),
          applicationService.getMyApplications({ limit: 200 }),
        ])
        if (matchRes.status === 'fulfilled') setJobs(matchRes.value.data.data || [])
        if (appsRes.status === 'fulfilled')
          setAppliedIds(new Set((appsRes.value.data.data || []).map(a => a.job_id)))
      } catch {}
      setLoading(false)
    }
    load()
  }, [])

  const handleApply = (job) => {
    setApplyModalJob(job)
    setApplicationMessage('')
  }

  const handleViewDetails = (job) => {
    setSelectedJob(job)
  }

  const submitApplication = async () => {
    if (!applyModalJob) return

    const message = applicationMessage.trim()
    if (!message) {
      toast.error('Please enter a short note for the employer.')
      return
    }

    try {
      await applicationService.apply({ job_id: applyModalJob.id, message })
      setAppliedIds(s => new Set([...s, applyModalJob.id]))
      toast.success('Your application has been sent to the hiring team.')
      setApplyModalJob(null)
      setApplicationMessage('')
    } catch (err) { toast.error(err.response?.data?.message || 'Failed') }
  }

  if (loading) return <LoadingSpinner />
  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Recommended Jobs ⭐</h1>
        <p className="page-subtitle">Matched based on your skills, education, and training</p>
      </div>
      {applyModalJob && (
        <Modal
          title={`Apply to ${applyModalJob.title}`}
          onClose={() => setApplyModalJob(null)}
          size="md"
          footer={
            <div className="flex gap-3">
              <button onClick={() => setApplyModalJob(null)} className="btn-secondary">Cancel</button>
              <button onClick={submitApplication} className="btn-primary">Submit Application</button>
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

      {jobs.length === 0 ? (
        <EmptyState
          icon="🎯" title="No recommendations yet"
          description="Add skills to get matched with the best jobs for you"
          action={<Link to="/jobseeker/skills" className="btn-primary btn-sm">Add Skills</Link>}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {jobs.map(job => (
            <JobCard key={job.id} job={job} matchScore={job.match_score}
              applied={appliedIds.has(job.id)} onApply={handleApply} onView={handleViewDetails} />
          ))}
        </div>
      )}

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
    </div>
  )
}

export default RecommendedJobs