// Applicants.jsx
import { useState, useEffect, useCallback } from 'react'
import { useAuth } from '../../context/AuthContext'
import { jobService } from '../../services/jobService'
import { applicationService, employerService, messageService, userService } from '../../services/index'
import { LoadingSpinner, EmptyState, Modal, Pagination } from '../../components/index'
import toast from 'react-hot-toast'
import { format } from 'date-fns'
import { API_BASE_URL } from '../../config/api.js'

const normalizeApplicationStatus = status => status === 'rejected' ? 'declined' : status
const statusColors = { pending:'badge-yellow', reviewed:'badge-blue', accepted:'badge-green', declined:'badge-red', rejected:'badge-red' }
const actionLabels = { reviewed:'Review', accepted:'Approve', declined:'Decline' }
const BASE_URL = API_BASE_URL

export default function Applicants() {
  const { user }   = useAuth()
  const [jobs, setJobs]           = useState([])
  const [selectedJob, setSelectedJob] = useState(null)
  const [applicants, setApplicants]   = useState([])
  const [loading, setLoading]         = useState(true)
  const [loadingApps, setLoadingApps] = useState(false)
  const [threadOpen, setThreadOpen]   = useState(null)
  const [threadMessages, setThreadMessages] = useState([])
  const [threadLoading, setThreadLoading] = useState(false)
  const [messageDraft, setMessageDraft] = useState('')
  const [canSendMessage, setCanSendMessage] = useState(false)
  const [profileApplicant, setProfileApplicant] = useState(null)
  const [profileData, setProfileData] = useState(null)
  const [profileLoading, setProfileLoading] = useState(false)

  useEffect(() => {
    const load = async () => {
      try {
        const eRes = await employerService.getByUser(user.id)
        const emp = eRes.data.data
        if (emp?.id) {
          const res = await jobService.getByEmployer(emp.id, { limit: 100 })
          setJobs((res.data.data || []).filter(j => !j.archived))
        }
      } catch {}
      setLoading(false)
    }
    load()
  }, [user.id])

  const loadApplicants = async (job) => {
    setSelectedJob(job)
    setLoadingApps(true)
    try {
      const res = await applicationService.getJobApplicants(job.id, { limit: 100 })
      setApplicants(res.data.data || [])
    } catch {}
    setLoadingApps(false)
  }

  const handleStatus = async (appId, status) => {
    const normalizedStatus = status === 'declined' ? 'rejected' : status
    try {
      await applicationService.updateStatus(appId, { status: normalizedStatus })
      setApplicants(apps => apps.map(a => a.id === appId ? { ...a, application_status: normalizedStatus } : a))
      toast.success(`Marked as ${status === 'declined' ? 'declined' : status}`)
    } catch { toast.error('Failed to update') }
  }

  const openMessages = async (app) => {
    setThreadOpen(app.id)
    setThreadLoading(true)
    setThreadMessages([])
    setMessageDraft('')
    try {
      const res = await messageService.getThread(app.id)
      const payload = res.data?.data
      setThreadMessages(payload?.messages || [])
      setCanSendMessage(Boolean(payload?.can_send))
    } catch {
      toast.error('Unable to load notes')
    }
    setThreadLoading(false)
  }

  const sendThreadMessage = async (app) => {
    const trimmed = messageDraft.trim()
    if (!trimmed) {
      toast.error('Enter a note before sending')
      return
    }
    try {
      await messageService.sendMessage(app.id, { message: trimmed })
      setMessageDraft('')
      await openMessages(app)
      toast.success('Note sent')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to send note')
    }
  }

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
      <div className="page-header">
        <h1 className="page-title">Applicants 👥</h1>
        <p className="page-subtitle">Review applications for your job postings</p>
      </div>

      {profileApplicant && (
        <Modal title={`${profileApplicant.first_name} ${profileApplicant.last_name}`} onClose={() => setProfileApplicant(null)} size="lg">
          {profileLoading ? (
            <LoadingSpinner text="Loading profile..." />
          ) : profileData ? (
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                <div><span className="font-semibold">Email:</span> {profileData.email}</div>
                <div><span className="font-semibold">Phone:</span> {profileData.phone || 'Not provided'}</div>
                <div><span className="font-semibold">Address:</span> {profileData.address || 'Not provided'}</div>
                <div><span className="font-semibold">City:</span> {profileData.city || 'Not provided'}</div>
                <div><span className="font-semibold">Education:</span> {profileData.education_level?.replace(/_/g, ' ') || 'Not provided'}</div>
                <div><span className="font-semibold">Employment status:</span> {profileData.employment_status?.replace(/_/g, ' ') || 'Not provided'}</div>
              </div>
              {profileData.bio && (
                <div>
                  <div className="font-semibold text-gray-800 mb-1">Bio</div>
                  <p className="text-sm text-gray-600 whitespace-pre-line">{profileData.bio}</p>
                </div>
              )}
              {profileData.skills?.length > 0 && (
                <div>
                  <div className="font-semibold text-gray-800 mb-2">Skills</div>
                  <div className="flex flex-wrap gap-2">
                    {profileData.skills.map(skill => (
                      <span key={skill.id} className="badge badge-blue">{skill.skill_name}</span>
                    ))}
                  </div>
                </div>
              )}
              <div className="space-y-3 pt-2 border-t border-gray-200">
                <div className="font-semibold text-gray-800">Resume Documents</div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div className="rounded-xl border border-blue-100 bg-blue-50 p-3">
                    <div className="text-xs font-semibold uppercase tracking-wide text-blue-700 mb-2">Uploaded Resume</div>
                    {profileData.resume_path ? (
                      <a href={`${BASE_URL}/${profileData.resume_path}`} target="_blank" rel="noopener noreferrer" className="btn-primary btn-sm inline-flex">
                        📄 View Uploaded Resume
                      </a>
                    ) : (
                      <p className="text-sm text-gray-500">No uploaded resume available.</p>
                    )}
                  </div>

                  <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-3">
                    <div className="text-xs font-semibold uppercase tracking-wide text-emerald-700 mb-2">Generated Resume</div>
                    {profileData.id ? (
                      <a href={`${BASE_URL}/uploads/resumes/generated/resume_${profileData.id}_generated.pdf`} target="_blank" rel="noopener noreferrer" className="btn-success btn-sm inline-flex">
                        🧾 View Generated Resume
                      </a>
                    ) : (
                      <p className="text-sm text-gray-500">No generated resume available.</p>
                    )}
                  </div>
                </div>
              </div>

              <div className="pt-2 border-t border-gray-200">
                <div className="font-semibold text-gray-800 mb-2">Other Documents</div>
                <p className="text-sm text-gray-500">No supporting documents have been attached to this applicant profile.</p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-gray-500">No profile information available.</p>
          )}
        </Modal>
      )}

      {jobs.length === 0 ? (
        <EmptyState icon="👥" title="No jobs posted yet" description="Your job postings will appear here once they are created" />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          {/* Job list */}
          <div className="space-y-2">
            <h3 className="font-bold text-gray-700 text-sm uppercase tracking-wide">Your Jobs</h3>
            {jobs.map(job => (
              <button key={job.id} onClick={() => loadApplicants(job)}
                className={`w-full text-left p-3 rounded-xl border-2 transition-all ${selectedJob?.id === job.id ? 'border-blue-600 bg-blue-50' : 'border-gray-100 bg-white hover:border-gray-300'}`}>
                <p className="font-semibold text-sm text-gray-800">{job.title}</p>
                <p className="text-xs text-gray-500 mt-0.5">{job.job_type} · {job.location}</p>
              </button>
            ))}
          </div>

          {/* Applicants */}
          <div className="md:col-span-2">
            {!selectedJob ? (
              <EmptyState icon="👈" title="Select a job" description="Click a job on the left to see its applicants" />
            ) : loadingApps ? <LoadingSpinner /> : applicants.length === 0 ? (
              <EmptyState icon="📭" title="No applicants yet" description="No one has applied to this job yet" />
            ) : (
              <div className="space-y-3">
                <h3 className="font-bold text-gray-700">
                  {selectedJob.title} — {applicants.length} applicant{applicants.length !== 1 ? 's' : ''}
                </h3>
                {applicants.map(app => (
                  <div key={app.id} className="card">
                    <div className="flex items-start justify-between gap-3 mb-2">
                      <div>
                        <p className="font-bold text-gray-800">{app.first_name} {app.last_name}</p>
                        <p className="text-xs text-gray-500">{app.email} · {app.phone}</p>
                        <p className="text-xs text-gray-500 capitalize">{app.education_level?.replace(/_/g,' ')}</p>
                      </div>
                      <span className={statusColors[normalizeApplicationStatus(app.application_status)]}>{normalizeApplicationStatus(app.application_status)}</span>
                    </div>
                    {app.cover_letter && (
                      <p className="text-xs text-gray-600 bg-gray-50 rounded-lg p-2 mb-3 line-clamp-2">
                        {app.cover_letter}
                      </p>
                    )}
                    <div className="flex flex-wrap gap-2">
                      {['reviewed','accepted','declined'].map(s => {
                        const currentStatus = normalizeApplicationStatus(app.application_status)
                        const isDisabled = currentStatus === s || (s === 'declined' && currentStatus === 'accepted') || (s === 'accepted' && currentStatus === 'rejected')
                        return (
                          <button key={s} onClick={() => handleStatus(app.id, s)}
                            disabled={isDisabled}
                            className={`btn-sm btn capitalize ${isDisabled ? 'opacity-40 cursor-default' : s === 'accepted' ? 'btn-success' : s === 'declined' ? 'btn-danger' : 'btn-secondary'}`}>
                            {actionLabels[s] || s}
                          </button>
                        )
                      })}
                      <button onClick={() => openProfile(app)} className="btn-secondary btn-sm">
                        👤 Profile
                      </button>
                      <button onClick={() => openMessages(app)} className="btn-secondary btn-sm">
                        💬 Notes
                      </button>
                    </div>

                    {threadOpen === app.id && (
                      <div className="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <div className="text-sm font-semibold text-gray-700 mb-2">Applicant notes</div>
                        {threadLoading ? (
                          <LoadingSpinner text="Loading notes..." />
                        ) : threadMessages.length === 0 ? (
                          <p className="text-sm text-gray-500">No notes yet. Send the first note to this applicant.</p>
                        ) : (
                          <div className="space-y-2 max-h-56 overflow-y-auto">
                            {threadMessages.map(msg => (
                              <div key={msg.id} className={`rounded-lg p-2 text-sm ${msg.sender_role === 'employer' ? 'bg-blue-100 text-blue-800 ml-auto' : 'bg-white text-gray-700'}`}>
                                <div className="font-semibold mb-1">{msg.sender_role === 'employer' ? 'You' : `${msg.first_name || ''} ${msg.last_name || ''}`.trim() || 'Applicant'}</div>
                                <div>{msg.message_text}</div>
                              </div>
                            ))}
                          </div>
                        )}
                        {canSendMessage && (
                          <div className="mt-3 flex flex-col gap-2">
                            <textarea
                              rows="3"
                              value={messageDraft}
                              onChange={(e) => setMessageDraft(e.target.value)}
                              className="w-full rounded-lg border border-gray-300 p-2 text-sm"
                              placeholder="Write a note to this applicant"
                            />
                            <button onClick={() => sendThreadMessage(app)} className="btn-primary btn-sm self-start">
                              Send Note
                            </button>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}