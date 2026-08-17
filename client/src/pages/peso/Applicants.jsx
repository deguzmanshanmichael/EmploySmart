import { useState, useEffect, useCallback } from 'react'
import { jobService } from '../../services/jobService'
import { applicationService } from '../../services/index'
import { LoadingSpinner, EmptyState, Pagination } from '../../components/index'
import { format } from 'date-fns'
import toast from 'react-hot-toast'
import { API_BASE_URL } from '../../config/api.js'

const normalizeApplicationStatus = status => status === 'rejected' ? 'declined' : status
const statusBadge = { pending:'badge-yellow', reviewed:'badge-blue', accepted:'badge-green', declined:'badge-red', rejected:'badge-red' }
const BASE_URL = API_BASE_URL

export default function Applicants() {
  const [jobs, setJobs]         = useState([])
  const [selectedJob, setSelectedJob] = useState(null)
  const [applicants, setApplicants]   = useState([])
  const [page, setPage]         = useState(1)
  const [pages, setPages]       = useState(1)
  const [loading, setLoading]   = useState(true)
  const [loadingApps, setLoadingApps] = useState(false)

  useEffect(() => {
    const load = async () => {
      try {
        const res = await jobService.getAll({ approval_status: 'approved', limit: 100 })
        setJobs(res.data.data || [])
      } catch {}
      setLoading(false)
    }
    load()
  }, [])

  const loadApplicants = async (job) => {
    setSelectedJob(job)
    setLoadingApps(true)
    try {
      const res = await applicationService.getJobApplicants(job.id, { page, limit: 20 })
      setApplicants(res.data.data || [])
      setPages(res.data.pagination?.pages || 1)
    } catch {}
    setLoadingApps(false)
  }

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Applicants Overview 👥</h1>
        <p className="page-subtitle">Monitor job applications across all employers</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div className="space-y-2">
          <h3 className="font-bold text-gray-700 text-sm uppercase tracking-wide">Approved Jobs</h3>
          {jobs.length === 0 ? <p className="text-sm text-gray-400">No approved jobs</p> : (
            jobs.map(job => (
              <button key={job.id} onClick={() => loadApplicants(job)}
                className={`w-full text-left p-3 rounded-xl border-2 transition-all ${selectedJob?.id === job.id ? 'border-blue-600 bg-blue-50' : 'border-gray-100 bg-white hover:border-gray-300'}`}>
                <p className="font-semibold text-sm text-gray-800 truncate">{job.title}</p>
                <p className="text-xs text-gray-400">{job.company_name}</p>
              </button>
            ))
          )}
        </div>

        <div className="md:col-span-2">
          {!selectedJob ? (
            <EmptyState icon="👈" title="Select a job" description="Select a job on the left to view its applicants" />
          ) : loadingApps ? <LoadingSpinner /> : applicants.length === 0 ? (
            <EmptyState icon="📭" title="No applicants" description={`No one has applied to "${selectedJob.title}" yet`} />
          ) : (
            <div className="space-y-3">
              <h3 className="font-bold text-gray-700">
                {selectedJob.title} — {applicants.length} applicant{applicants.length !== 1 ? 's' : ''}
              </h3>
              <div className="table-wrapper">
                <table className="table">
                  <thead>
                    <tr><th>Name</th><th>Education</th><th>Applied</th><th>Status</th><th>Resume</th></tr>
                  </thead>
                  <tbody>
                    {applicants.map(app => (
                      <tr key={app.id}>
                        <td>
                          <p className="font-semibold">{app.first_name} {app.last_name}</p>
                          <p className="text-xs text-gray-500">{app.email}</p>
                        </td>
                        <td className="capitalize text-xs">{app.education_level?.replace(/_/g,' ')}</td>
                        <td className="text-xs">{format(new Date(app.applied_at), 'MMM d, yyyy')}</td>
                        <td><span className={statusBadge[normalizeApplicationStatus(app.application_status)]}>{normalizeApplicationStatus(app.application_status)}</span></td>
                        <td>
                          {app.resume_path && (
                            <a href={`${BASE_URL}/${app.resume_path}`} target="_blank" rel="noopener noreferrer" className="text-blue-600 text-xs underline">View</a>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination page={page} pages={pages} onPageChange={p => { setPage(p); loadApplicants(selectedJob) }} />
            </div>
          )}
        </div>
      </div>
    </div>
  )
}