import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'
import { FiFileText, FiMapPin, FiBriefcase, FiCheckCircle, FiAlertCircle, FiRefreshCw, FiInfo } from 'react-icons/fi'
import { resumeRecommendationService, applicationService } from '../services/index'
import { Modal } from './index'

const LABEL = 'Based on Your Uploaded Resume'

function scoreTone(score) {
  if (score >= 75) return 'bg-green-100 text-green-800'
  if (score >= 55) return 'bg-blue-100 text-blue-800'
  return 'bg-yellow-100 text-yellow-800'
}

function ResumeBadge() {
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white">
      <FiFileText size={12} /> {LABEL}
    </span>
  )
}

function RecommendationCard({ job, compact, onView, onApply }) {
  const missing = job.missing_qualifications || []
  return (
    <article className="card flex flex-col gap-3 animate-fade-in">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <ResumeBadge />
        <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${scoreTone(job.match_score)}`} title="Estimated compatibility, not a probability of being hired">
          {Math.round(job.match_score)}% estimated match
        </span>
      </div>

      <div className="min-w-0">
        <h3 className="text-base font-bold leading-snug text-gray-900">{job.title}</h3>
        <p className="text-sm font-semibold text-blue-700">{job.company_name}</p>
        <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
          {job.location && <span className="flex items-center gap-1"><FiMapPin size={12} /> {job.location}</span>}
          {job.job_type && <span className="flex items-center gap-1 capitalize"><FiBriefcase size={12} /> {job.job_type}</span>}
          {job.salary_range && <span>💰 {job.salary_range}</span>}
        </div>
      </div>

      {!compact && job.summary && <p className="text-sm text-gray-600">{job.summary}</p>}

      <div className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-sm text-indigo-900">
        <p className="mb-1 text-xs font-bold uppercase tracking-wide text-indigo-700">Why this is recommended</p>
        <p>{job.explanation}</p>
      </div>

      {job.matched_skills?.length > 0 && (
        <div>
          <p className="mb-1.5 text-xs font-semibold text-gray-500">Matching skills from your resume</p>
          <div className="flex flex-wrap gap-1.5">
            {job.matched_skills.map((s) => (
              <span key={s} className="badge badge-green inline-flex items-center gap-1"><FiCheckCircle size={11} /> {s}</span>
            ))}
          </div>
        </div>
      )}

      {!compact && job.transferable_skills?.length > 0 && (
        <div>
          <p className="mb-1.5 text-xs font-semibold text-gray-500">Related skills that may transfer</p>
          <div className="flex flex-wrap gap-1.5">
            {job.transferable_skills.map((t) => (
              <span key={`${t.resume_skill}-${t.job_skill}`} className="badge badge-yellow">{t.resume_skill} → {t.job_skill}</span>
            ))}
          </div>
        </div>
      )}

      {missing.length > 0 && (
        <div>
          <p className="mb-1.5 text-xs font-semibold text-gray-500">Missing or not verified in your resume</p>
          <ul className="space-y-1 text-xs text-gray-600">
            {(compact ? missing.slice(0, 3) : missing).map((m, i) => (
              <li key={`${m.type}-${m.label}-${i}`} className="flex items-start gap-1.5">
                <FiAlertCircle className="mt-0.5 flex-shrink-0 text-amber-500" size={12} />
                <span><span className="font-semibold text-gray-700">{m.label}</span> — {m.detail}</span>
              </li>
            ))}
            {compact && missing.length > 3 && <li className="text-gray-400">+{missing.length - 3} more (see details)</li>}
          </ul>
        </div>
      )}

      <div className="mt-auto flex gap-2 pt-1">
        <button onClick={() => onView(job)} className="btn-secondary btn-sm flex-1">View Details</button>
        <button onClick={() => onApply(job)} disabled={job.applied}
          className={`flex-1 ${job.applied ? 'btn btn-sm bg-green-100 text-green-700 cursor-default' : 'btn-primary btn-sm'}`}>
          {job.applied ? '✓ Applied' : 'Apply Now'}
        </button>
      </div>
    </article>
  )
}

/**
 * Resume-driven recommendations. Every card carries the "Based on Your Uploaded Resume"
 * label, the evidence it is based on, and what could not be verified.
 */
export default function ResumeRecommendations({ limit = 10, compact = false, title = 'Recommended From Your Resume', refreshKey = 0 }) {
  const [state, setState] = useState({ loading: true, error: null, data: null })
  const [selected, setSelected] = useState(null)
  const [applyJob, setApplyJob] = useState(null)
  const [message, setMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [appliedIds, setAppliedIds] = useState(new Set())

  const load = useCallback(async () => {
    setState((s) => ({ ...s, loading: true, error: null }))
    try {
      const res = await resumeRecommendationService.getMine({ limit })
      setState({ loading: false, error: null, data: res.data.data })
    } catch (err) {
      setState({ loading: false, error: err.response?.data?.message || err.message || 'Unable to load recommendations.', data: null })
    }
  }, [limit])

  useEffect(() => { load() }, [load, refreshKey])

  const submitApplication = async () => {
    const note = message.trim()
    if (!note) { toast.error('Please enter a short note for the employer.'); return }
    setSubmitting(true)
    try {
      await applicationService.apply({ job_id: applyJob.id, message: note })
      setAppliedIds((s) => new Set([...s, applyJob.id]))
      toast.success('Your application has been sent to the hiring team.')
      setApplyJob(null)
      setSelected(null)
      setMessage('')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to apply')
    } finally {
      setSubmitting(false)
    }
  }

  const data = state.data
  const jobs = (data?.recommendations || []).map((j) => ({ ...j, applied: appliedIds.has(j.id) }))

  return (
    <section className="space-y-4" aria-labelledby="resume-recs-title">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="resume-recs-title" className="text-lg font-bold text-gray-900">{title}</h2>
          <div className="mt-1.5"><ResumeBadge /></div>
        </div>
        {data?.status === 'ok' && (
          <button onClick={load} className="btn-secondary btn-sm" disabled={state.loading}>
            <FiRefreshCw size={14} className={state.loading ? 'animate-spin' : ''} /> Refresh
          </button>
        )}
      </div>

      <div className="alert-info">
        <FiInfo className="mt-0.5 flex-shrink-0" />
        <div>
          <p>{data?.basis || 'Recommended based on the skills, qualifications, education, and work experience identified in your uploaded resume.'}</p>
          <p className="mt-1 text-xs font-normal opacity-80">{data?.disclaimer || 'Match percentages are estimates, not the probability of being hired.'}</p>
        </div>
      </div>

      {state.loading ? (
        <div className={`grid gap-4 ${compact ? 'md:grid-cols-2 xl:grid-cols-3' : 'md:grid-cols-2'}`}>
          {Array.from({ length: compact ? 3 : 4 }).map((_, i) => <div key={i} className="skeleton h-64 rounded-2xl" />)}
        </div>
      ) : state.error ? (
        <div className="alert-danger">
          <FiAlertCircle className="mt-0.5 flex-shrink-0" />
          <div className="flex-1">{state.error}</div>
          <button onClick={load} className="btn-secondary btn-sm">Try again</button>
        </div>
      ) : data?.status === 'no_resume' ? (
        <div className="card-flat text-center">
          <p className="text-sm text-gray-600">{data.message}</p>
          <Link to="/jobseeker/resume" className="btn-primary btn-sm mt-3 inline-flex">Upload Resume</Link>
        </div>
      ) : data?.status === 'needs_review' ? (
        <div className="card-flat text-center">
          <p className="text-sm text-gray-600">{data.message}</p>
          <Link to="/jobseeker/resume" className="btn-primary btn-sm mt-3 inline-flex">Review Resume Details</Link>
        </div>
      ) : jobs.length === 0 ? (
        <div className="card-flat text-center text-sm text-gray-600">
          {data?.message || 'No open job postings currently match the information in your resume.'}
        </div>
      ) : (
        <div className={`grid gap-4 ${compact ? 'md:grid-cols-2 xl:grid-cols-3' : 'md:grid-cols-2'}`}>
          {jobs.map((job) => (
            <RecommendationCard key={job.id} job={job} compact={compact} onView={setSelected} onApply={(j) => { setApplyJob(j); setMessage('') }} />
          ))}
        </div>
      )}

      {selected && (
        <Modal title={selected.title} onClose={() => setSelected(null)} size="lg"
          footer={
            <div className="flex gap-3">
              <button onClick={() => setSelected(null)} className="btn-secondary">Close</button>
              <button onClick={() => { setApplyJob(selected); setMessage('') }} disabled={appliedIds.has(selected.id)}
                className={appliedIds.has(selected.id) ? 'btn bg-green-100 text-green-700' : 'btn-primary'}>
                {appliedIds.has(selected.id) ? '✓ Applied' : 'Apply Now'}
              </button>
            </div>
          }>
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <ResumeBadge />
              <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${scoreTone(selected.match_score)}`}>{Math.round(selected.match_score)}% estimated match</span>
            </div>
            <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <div><span className="font-semibold text-gray-600">Company:</span> {selected.company_name}</div>
              {selected.location && <div><span className="font-semibold text-gray-600">Location:</span> {selected.location}</div>}
              {selected.vacancies && <div><span className="font-semibold text-gray-600">Vacancies:</span> {selected.vacancies}</div>}
              {selected.deadline && <div><span className="font-semibold text-gray-600">Deadline:</span> {new Date(selected.deadline).toLocaleDateString()}</div>}
            </div>
            <div className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-sm text-indigo-900">
              <p className="mb-1 text-xs font-bold uppercase tracking-wide text-indigo-700">Why this is recommended</p>
              <p>{selected.explanation}</p>
            </div>
            {selected.missing_qualifications?.length > 0 && (
              <div>
                <h4 className="mb-1 font-bold text-gray-800">Missing or not verified</h4>
                <ul className="list-disc space-y-1 pl-5 text-sm text-gray-600">
                  {selected.missing_qualifications.map((m, i) => <li key={i}><strong>{m.label}</strong> — {m.detail}</li>)}
                </ul>
              </div>
            )}
            {selected.description && (
              <div>
                <h4 className="mb-1 font-bold text-gray-800">Description</h4>
                <p className="whitespace-pre-line text-sm text-gray-600">{selected.description}</p>
              </div>
            )}
            {selected.requirements && (
              <div>
                <h4 className="mb-1 font-bold text-gray-800">Requirements</h4>
                <p className="whitespace-pre-line text-sm text-gray-600">{selected.requirements}</p>
              </div>
            )}
            <p className="text-xs text-gray-400">{data?.disclaimer}</p>
          </div>
        </Modal>
      )}

      {applyJob && (
        <Modal title={`Apply to ${applyJob.title}`} onClose={() => setApplyJob(null)} size="md"
          footer={
            <div className="flex gap-3">
              <button onClick={() => setApplyJob(null)} className="btn-secondary">Cancel</button>
              <button onClick={submitApplication} className="btn-primary" disabled={submitting}>{submitting ? 'Submitting...' : 'Submit Application'}</button>
            </div>
          }>
          <div className="space-y-3">
            <div className="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
              Share a short note with the employer before you submit your application.
            </div>
            <label className="label" htmlFor="resume-apply-note">Employer's Note</label>
            <textarea id="resume-apply-note" className="input min-h-[120px]" value={message} onChange={(e) => setMessage(e.target.value)}
              placeholder="Introduce yourself and explain why you are a strong fit for this role..." />
          </div>
        </Modal>
      )}
    </section>
  )
}
