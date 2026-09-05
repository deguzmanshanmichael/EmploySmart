import { useState, useEffect, useCallback } from 'react'
import { jobService } from '../../services/jobService'
import { applicationService, userService } from '../../services/index'
import { LoadingSpinner, EmptyState, Pagination, Modal } from '../../components/index'
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
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [profileApplicant, setProfileApplicant] = useState(null)
  const [profileData, setProfileData] = useState(null)
  const [profileLoading, setProfileLoading] = useState(false)

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

  const loadApplicants = async (job, targetPage = 1) => {
    setSelectedJob(job)
    setLoadingApps(true)
    try {
      const res = await applicationService.getJobApplicants(job.id, { page: targetPage, limit: 20, search, status })
      setApplicants(res.data.data || [])
      setPages(res.data.pagination?.pages || 1)
      setPage(targetPage)
    } catch {}
    setLoadingApps(false)
  }

  useEffect(() => {
    if (selectedJob) loadApplicants(selectedJob, 1)
  }, [search, status])

  const openProfile = async (app) => {
    setProfileApplicant(app)
    setProfileLoading(true)
    setProfileData(null)
    try {
      const res = await userService.getOne(app.user_id)
      setProfileData(res.data.data)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to load profile')
    }
    setProfileLoading(false)
  }

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-5">
      {profileApplicant && (
        <Modal title={`${profileApplicant.first_name} ${profileApplicant.last_name}`} onClose={() => setProfileApplicant(null)} size="lg">
          {profileLoading ? <LoadingSpinner text="Loading profile..." /> : profileData ? (
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                <div><span className="font-semibold">Email:</span> {profileData.email}</div>
                <div><span className="font-semibold">Phone:</span> {profileData.phone || 'Not provided'}</div>
                <div><span className="font-semibold">Address:</span> {profileData.address || 'Not provided'}</div>
                <div><span className="font-semibold">City:</span> {profileData.city || 'Not provided'}</div>
                <div><span className="font-semibold">Education:</span> {profileData.education_level?.replace(/_/g, ' ') || 'Not provided'}</div>
                <div><span className="font-semibold">Employment status:</span> {profileData.employment_status?.replace(/_/g, ' ') || 'Not provided'}</div>
              </div>
              {profileData.bio && <div><div className="font-semibold text-gray-800 mb-1">Bio</div><p className="text-sm text-gray-600 whitespace-pre-line">{profileData.bio}</p></div>}
              {profileData.skills?.length > 0 && <div><div className="font-semibold text-gray-800 mb-2">Skills</div><div className="flex flex-wrap gap-2">{profileData.skills.map(skill => <span key={skill.id} className="badge badge-blue">{skill.skill_name}</span>)}</div></div>}
              <div className="space-y-3 pt-2 border-t border-gray-200">
                <div className="font-semibold text-gray-800">Resume Documents</div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div className="rounded-xl border border-blue-100 bg-blue-50 p-3"><div className="text-xs font-semibold uppercase tracking-wide text-blue-700 mb-2">Uploaded Resume</div>{profileData.resume_path ? <a href={`${BASE_URL}/${profileData.resume_path}`} target="_blank" rel="noopener noreferrer" className="btn-primary btn-sm inline-flex">📄 View Uploaded Resume</a> : <p className="text-sm text-gray-500">No uploaded resume available.</p>}</div>
                  <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-3"><div className="text-xs font-semibold uppercase tracking-wide text-emerald-700 mb-2">Generated Resume</div>{profileData.id ? <a href={`${BASE_URL}/uploads/resumes/generated/resume_${profileData.id}_generated.pdf`} target="_blank" rel="noopener noreferrer" className="btn-success btn-sm inline-flex">🧾 View Generated Resume</a> : <p className="text-sm text-gray-500">No generated resume available.</p>}</div>
                </div>
              </div>
            </div>
          ) : <p className="text-sm text-gray-500">No profile information available.</p>}
        </Modal>
      )}
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
          ) : (
            <div className="space-y-3">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 className="font-bold text-gray-700">{selectedJob.title}</h3>
                <div className="flex flex-col gap-2 sm:flex-row">
                  <input className="input sm:w-56" value={search} placeholder="Search applicants..." onChange={e => setSearch(e.target.value)} />
                  <select className="input sm:w-40" value={status} onChange={e => setStatus(e.target.value)}>
                    <option value="">All statuses</option><option value="pending">Pending</option><option value="reviewed">Reviewed</option><option value="accepted">Accepted</option><option value="rejected">Declined</option><option value="withdrawn">Withdrawn</option>
                  </select>
                </div>
              </div>
              {loadingApps ? <LoadingSpinner /> : applicants.length === 0 ? (
                <EmptyState icon="📭" title="No matching applicants" description="Try another search term or status filter." />
              ) : <>
              <p className="text-sm text-gray-500">{applicants.length} applicant{applicants.length !== 1 ? 's' : ''} on this page</p>
              <div className="table-wrapper">
                <table className="table">
                  <thead>
                    <tr><th>Name</th><th>Education</th><th>Applied</th><th>Status</th><th>Profile</th></tr>
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
                        <td><button onClick={() => openProfile(app)} className="btn-secondary btn-sm">👤 Profile</button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination page={page} pages={pages} onPageChange={p => loadApplicants(selectedJob, p)} />
              </>}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}