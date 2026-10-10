import { useCallback, useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { FiMessageSquare, FiSearch, FiStar, FiSend, FiLock, FiUnlock, FiUsers, FiCheckCircle, FiAlertCircle } from 'react-icons/fi'
import { useAuth } from '../../context/AuthContext'
import { feedbackService } from '../../services/index'
import { LoadingSpinner, StatCard } from '../../components/index'

const OUTCOME_STYLE = {
  performing_well: 'badge-green', retained: 'badge-green', needs_training: 'badge-yellow',
  adjustment_issues: 'badge-yellow', resigned: 'badge-gray', separated: 'badge-red', other: 'badge-gray',
}
const ROLE_LABEL = { peso: 'PESO', admin: 'Administrator', employer: 'Employer' }

function Stars({ value, onChange, size = 18 }) {
  return (
    <div className="flex items-center gap-1" role={onChange ? 'radiogroup' : undefined} aria-label="Rating">
      {[1, 2, 3, 4, 5].map((n) => (
        <button key={n} type="button" disabled={!onChange} onClick={() => onChange?.(n)} aria-label={`${n} star${n > 1 ? 's' : ''}`}
          className={`${onChange ? 'cursor-pointer' : 'cursor-default'} ${n <= (value || 0) ? 'text-amber-400' : 'text-gray-300'}`}>
          <FiStar size={size} fill={n <= (value || 0) ? 'currentColor' : 'none'} />
        </button>
      ))}
    </div>
  )
}

const fmt = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleString() : '')

/**
 * Two-way feedback between PESO and employers about job placements
 * (accepted applications): ratings, outcomes and replies.
 */
export default function PlacementFeedback() {
  const { user } = useAuth()
  const isStaff = ['peso', 'admin'].includes(user?.role)
  const [summary, setSummary] = useState(null)
  const [placements, setPlacements] = useState([])
  const [outcomes, setOutcomes] = useState({})
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('')
  const [selected, setSelected] = useState(null)
  const [detail, setDetail] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [form, setForm] = useState({ rating: 0, outcome: '', feedback: '' })
  const [replies, setReplies] = useState({})
  const [busy, setBusy] = useState(false)

  const loadList = useCallback(async () => {
    setLoading(true)
    try {
      const [listRes, summaryRes] = await Promise.all([
        feedbackService.getPlacements({ search: search || undefined, filter: filter || undefined }),
        isStaff ? feedbackService.getSummary() : Promise.resolve(null),
      ])
      setPlacements(listRes.data.data.placements || [])
      setOutcomes(listRes.data.data.outcomes || {})
      if (summaryRes) setSummary(summaryRes.data.data)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to load placements')
    } finally {
      setLoading(false)
    }
  }, [search, filter, isStaff])

  useEffect(() => {
    const t = setTimeout(loadList, 250)
    return () => clearTimeout(t)
  }, [loadList])

  const openPlacement = async (applicationId) => {
    setSelected(applicationId)
    setDetailLoading(true)
    try {
      const res = await feedbackService.getPlacement(applicationId)
      setDetail(res.data.data)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to load feedback')
      setDetail(null)
    } finally {
      setDetailLoading(false)
    }
  }

  const refreshDetail = async () => {
    if (selected) await openPlacement(selected)
    loadList()
  }

  const submitFeedback = async () => {
    if (!form.rating) { toast.error('Please choose a rating.'); return }
    if (form.feedback.trim().length < 3) { toast.error('Please write your feedback.'); return }
    setBusy(true)
    try {
      await feedbackService.createForPlacement(selected, { rating: form.rating, outcome: form.outcome || null, feedback: form.feedback.trim() })
      toast.success('Feedback sent.')
      setForm({ rating: 0, outcome: '', feedback: '' })
      await refreshDetail()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to send feedback')
    } finally {
      setBusy(false)
    }
  }

  const sendReply = async (threadId) => {
    const message = (replies[threadId] || '').trim()
    if (message.length < 2) return
    setBusy(true)
    try {
      await feedbackService.reply(threadId, message)
      setReplies((r) => ({ ...r, [threadId]: '' }))
      await refreshDetail()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to send reply')
    } finally {
      setBusy(false)
    }
  }

  const toggleStatus = async (thread) => {
    setBusy(true)
    try {
      await feedbackService.setStatus(thread.id, thread.status === 'open' ? 'closed' : 'open')
      await refreshDetail()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to update thread')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Placement Feedback</h1>
        <p className="page-subtitle">
          {isStaff
            ? 'Communicate with employers about hired applicants and evaluate placement outcomes.'
            : 'Tell PESO how your hired applicants are doing and continue the conversation.'}
        </p>
      </div>

      {isStaff && summary && (
        <>
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard label="Placements" value={summary.placements} icon={<FiUsers />} color="bg-blue-50 text-blue-600" />
            <StatCard label={`With feedback (${summary.feedback_coverage}%)`} value={summary.placements_with_feedback} icon={<FiCheckCircle />} color="bg-green-50 text-green-600" />
            <StatCard label="Open conversations" value={summary.open_threads} icon={<FiMessageSquare />} color="bg-yellow-50 text-yellow-600" />
            <StatCard label="Average rating" value={summary.average_rating ?? '—'} icon={<FiStar />} color="bg-purple-50 text-purple-600" />
          </div>
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="card-flat">
              <h2 className="font-bold text-gray-800">Placement outcomes</h2>
              {summary.outcomes.length === 0 ? <p className="py-10 text-center text-sm text-gray-400">No outcomes reported yet</p> : (
                <ResponsiveContainer width="100%" height={220}>
                  <BarChart data={summary.outcomes} margin={{ top: 16, right: 8, left: -20, bottom: 4 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="label" tick={{ fontSize: 11 }} interval={0} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                    <Tooltip formatter={(v) => [v, 'Reports']} />
                    <Bar dataKey="count" fill="#0f766e" radius={[5, 5, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
            <div className="card-flat">
              <h2 className="mb-3 font-bold text-gray-800">By employer</h2>
              <div className="table-wrapper max-h-64 overflow-y-auto">
                <table className="table">
                  <thead><tr><th>Employer</th><th>Placements</th><th>Feedback</th><th>Avg rating</th><th>Open</th></tr></thead>
                  <tbody>
                    {summary.by_employer.length === 0 && <tr><td colSpan={5} className="text-center text-gray-400">No placements yet</td></tr>}
                    {summary.by_employer.map((e) => (
                      <tr key={e.employer_id}><td>{e.company_name}</td><td>{e.placements}</td><td>{e.feedback_count}</td><td>{e.avg_rating ?? '—'}</td><td>{e.open_threads || 0}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </>
      )}

      <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <section className="card-flat space-y-3">
          <div className="flex flex-col gap-2 sm:flex-row">
            <div className="relative flex-1">
              <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input className="input pl-9" placeholder="Search applicant, job or employer" value={search} onChange={(e) => setSearch(e.target.value)} />
            </div>
            <select className="input sm:w-40" value={filter} onChange={(e) => setFilter(e.target.value)} aria-label="Filter">
              <option value="">All placements</option>
              <option value="none">No feedback yet</option>
              <option value="open">Open conversations</option>
            </select>
          </div>
          {loading ? <LoadingSpinner /> : placements.length === 0 ? (
            <p className="py-10 text-center text-sm text-gray-400">No placements found. Placements appear here when an application is accepted.</p>
          ) : (
            <ul className="max-h-[32rem] space-y-2 overflow-y-auto pr-1">
              {placements.map((p) => (
                <li key={p.application_id}>
                  <button onClick={() => openPlacement(p.application_id)}
                    className={`w-full rounded-xl border p-3 text-left transition-colors ${selected === p.application_id ? 'border-blue-300 bg-blue-50' : 'border-gray-100 hover:border-blue-200'}`}>
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <p className="truncate font-semibold text-gray-900">{p.first_name} {p.last_name}</p>
                        <p className="truncate text-xs text-gray-500">{p.job_title}{isStaff ? ` · ${p.company_name}` : ''}</p>
                      </div>
                      {Number(p.open_count) > 0 && <span className="badge badge-yellow flex-shrink-0">{p.open_count} open</span>}
                    </div>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                      {Number(p.thread_count) === 0 ? <span className="badge badge-gray">No feedback yet</span> : <Stars value={Math.round(p.avg_rating)} size={13} />}
                      {p.outcome_label && <span className={`badge ${OUTCOME_STYLE[p.latest_outcome] || 'badge-gray'}`}>{p.outcome_label}</span>}
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="card-flat">
          {!selected ? (
            <div className="flex h-full flex-col items-center justify-center py-16 text-center text-gray-400">
              <FiMessageSquare size={32} className="mb-2" />
              <p className="text-sm">Select a placement to view or give feedback.</p>
            </div>
          ) : detailLoading || !detail ? <LoadingSpinner /> : (
            <div className="space-y-5">
              <div>
                <h2 className="text-lg font-bold text-gray-900">{detail.placement.jobseeker_name}</h2>
                <p className="text-sm text-gray-500">{detail.placement.job_title} · {detail.placement.company_name}{detail.placement.location ? ` · ${detail.placement.location}` : ''}</p>
              </div>

              <div className="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <h3 className="font-semibold text-gray-800">{isStaff ? 'Send feedback to the employer' : 'Send feedback to PESO'}</h3>
                <div className="flex flex-wrap items-center gap-4">
                  <Stars value={form.rating} onChange={(rating) => setForm((f) => ({ ...f, rating }))} />
                  <select className="input max-w-xs" value={form.outcome} onChange={(e) => setForm((f) => ({ ...f, outcome: e.target.value }))} aria-label="Placement outcome">
                    <option value="">Outcome (optional)</option>
                    {Object.entries(outcomes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                  </select>
                </div>
                <textarea className="input min-h-[90px]" maxLength={2000} placeholder={isStaff ? 'e.g. How is the applicant adjusting? Is any support needed?' : 'e.g. Performance, attendance, skills that need improvement...'}
                  value={form.feedback} onChange={(e) => setForm((f) => ({ ...f, feedback: e.target.value }))} />
                <button onClick={submitFeedback} disabled={busy} className="btn-primary btn-sm"><FiSend /> Send Feedback</button>
              </div>

              {detail.threads.length === 0 ? <p className="text-sm text-gray-400">No feedback for this placement yet.</p> : detail.threads.map((t) => (
                <article key={t.id} className="rounded-xl border border-gray-100 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="badge badge-blue">{ROLE_LABEL[t.sender_role] || t.sender_role}</span>
                      <Stars value={t.rating} size={14} />
                      {t.outcome_label && <span className={`badge ${OUTCOME_STYLE[t.outcome] || 'badge-gray'}`}>{t.outcome_label}</span>}
                      <span className={`badge ${t.status === 'open' ? 'badge-yellow' : 'badge-gray'}`}>{t.status === 'open' ? 'Open' : 'Closed'}</span>
                    </div>
                    <button onClick={() => toggleStatus(t)} disabled={busy} className="btn-secondary btn-sm">
                      {t.status === 'open' ? <><FiLock /> Close</> : <><FiUnlock /> Reopen</>}
                    </button>
                  </div>
                  <p className="mt-2 whitespace-pre-line text-sm text-gray-700">{t.feedback}</p>
                  <p className="mt-1 text-xs text-gray-400">{t.first_name} {t.last_name} · {fmt(t.created_at)}</p>
                  {t.replies.length > 0 && (
                    <div className="mt-3 space-y-2 border-l-2 border-blue-100 pl-3">
                      {t.replies.map((r) => (
                        <div key={r.id}>
                          <p className="whitespace-pre-line text-sm text-gray-700">{r.message}</p>
                          <p className="text-xs text-gray-400">{ROLE_LABEL[r.sender_role] || r.sender_role} · {r.first_name} {r.last_name} · {fmt(r.created_at)}</p>
                        </div>
                      ))}
                    </div>
                  )}
                  {t.status === 'open' ? (
                    <div className="mt-3 flex gap-2">
                      <input className="input" placeholder="Write a reply" maxLength={2000} value={replies[t.id] || ''}
                        onChange={(e) => setReplies((r) => ({ ...r, [t.id]: e.target.value }))}
                        onKeyDown={(e) => e.key === 'Enter' && sendReply(t.id)} />
                      <button onClick={() => sendReply(t.id)} disabled={busy} className="btn-primary btn-sm" aria-label="Send reply"><FiSend /></button>
                    </div>
                  ) : <p className="mt-3 flex items-center gap-1 text-xs text-gray-400"><FiAlertCircle /> Closed — reopen to continue the conversation.</p>}
                </article>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  )
}
