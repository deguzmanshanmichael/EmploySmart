import { useState, useEffect, useCallback } from 'react'
import { jobService } from '../../services/jobService'
import { applicationService } from '../../services/index'
import { JobCard, Pagination, LoadingSpinner, EmptyState, Modal } from '../../components/index'
import { FiSearch, FiFilter, FiX } from 'react-icons/fi'
import toast from 'react-hot-toast'

export default function JobList() {
  const [jobs, setJobs]         = useState([])
  const [total, setTotal]       = useState(0)
  const [pages, setPages]       = useState(1)
  const [page, setPage]         = useState(1)
  const [loading, setLoading]   = useState(true)
  const [applying, setApplying] = useState(null)
  const [appliedIds, setAppliedIds] = useState(new Set())
  const [selectedJob, setSelectedJob] = useState(null)
  const [applyModalJob, setApplyModalJob] = useState(null)
  const [applicationMessage, setApplicationMessage] = useState('')
  const [filters, setFilters] = useState({ search: '', location: '', job_type: '', experience_level: '' })
  const [showFilters, setShowFilters] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await jobService.getAll({ ...filters, page, limit: 12 })
      setJobs(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load jobs') }
    finally { setLoading(false) }
  }, [filters, page])

  useEffect(() => {
    const loadApplied = async () => {
      if (!localStorage.getItem('access_token')) {
        setAppliedIds(new Set())
        return
      }
      try {
        const res = await applicationService.getMyApplications({ limit: 100 })
        setAppliedIds(new Set((res.data.data || []).map(a => a.job_id)))
      } catch {
        setAppliedIds(new Set())
      }
    }
    loadApplied()
  }, [localStorage.getItem('user')?.id])

  useEffect(() => { load() }, [load])

  const handleApply = (job) => {
    setApplyModalJob(job)
    setApplicationMessage('')
  }

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
      setSelectedJob(null)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to apply')
    } finally { setApplying(null) }
  }

  const clearFilters = () => {
    setFilters({ search: '', location: '', job_type: '', experience_level: '' })
    setPage(1)
  }

  const hasFilters = Object.values(filters).some(v => v)

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Browse Jobs</h1>
        <p className="page-subtitle">{total} jobs available</p>
      </div>

      {/* Search bar */}
      <div className="flex gap-3 flex-col sm:flex-row">
        <div className="relative flex-1">
          <FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
          <input
            type="text"
            className="input pl-10"
            placeholder="Search jobs, companies..."
            value={filters.search}
            onChange={e => { setFilters(f => ({ ...f, search: e.target.value })); setPage(1) }}
          />
        </div>
        <button
          onClick={() => setShowFilters(s => !s)}
          className={`btn ${hasFilters ? 'btn-primary' : 'btn-secondary'} flex-shrink-0`}
        >
          <FiFilter size={16} />
          Filters {hasFilters && `(${Object.values(filters).filter(Boolean).length})`}
        </button>
        {hasFilters && (
          <button onClick={clearFilters} className="btn-secondary flex-shrink-0">
            <FiX size={15} /> Clear
          </button>
        )}
      </div>

      {/* Filters panel */}
      {showFilters && (
        <div className="card-flat grid grid-cols-1 sm:grid-cols-3 gap-4 animate-slide-up">
          <div>
            <label className="label">Location</label>
            <input className="input" placeholder="City or province..." value={filters.location}
              onChange={e => { setFilters(f => ({ ...f, location: e.target.value })); setPage(1) }} />
          </div>
          <div>
            <label className="label">Job Type</label>
            <select className="input" value={filters.job_type}
              onChange={e => { setFilters(f => ({ ...f, job_type: e.target.value })); setPage(1) }}>
              <option value="">All types</option>
              <option value="fulltime">Full-time</option>
              <option value="parttime">Part-time</option>
              <option value="contract">Contract</option>
              <option value="internship">Internship</option>
            </select>
          </div>
          <div>
            <label className="label">Experience Level</label>
            <select className="input" value={filters.experience_level}
              onChange={e => { setFilters(f => ({ ...f, experience_level: e.target.value })); setPage(1) }}>
              <option value="">All levels</option>
              <option value="entry">Entry Level</option>
              <option value="mid">Mid Level</option>
              <option value="senior">Senior Level</option>
            </select>
          </div>
        </div>
      )}

      {/* Results */}
      {loading ? <LoadingSpinner /> : jobs.length === 0 ? (
        <EmptyState icon="🔍" title="No jobs found" description="Try adjusting your search filters" action={<button onClick={clearFilters} className="btn-primary btn-sm">Clear Filters</button>} />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {jobs.map(job => (
            <JobCard
              key={job.id}
              job={job}
              applied={appliedIds.has(job.id)}
              onView={setSelectedJob}
              onApply={handleApply}
            />
          ))}
        </div>
      )}

      <Pagination page={page} pages={pages} onPageChange={setPage} />

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

      {/* Job Detail Modal */}
      {selectedJob && (
        <Modal
          title={selectedJob.title}
          onClose={() => setSelectedJob(null)}
          size="lg"
          footer={
            <div className="flex gap-3">
              <button onClick={() => setSelectedJob(null)} className="btn-secondary">Close</button>
              <button
                onClick={() => handleApply(selectedJob)}
                disabled={appliedIds.has(selectedJob.id) || applying === selectedJob.id}
                className={appliedIds.has(selectedJob.id) ? 'btn bg-green-100 text-green-700' : 'btn-primary'}
              >
                {applying === selectedJob.id ? <><div className="spinner w-4 h-4" /> Applying...</> : appliedIds.has(selectedJob.id) ? '✓ Applied' : 'Apply Now'}
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