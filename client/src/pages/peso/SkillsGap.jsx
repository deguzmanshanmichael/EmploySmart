import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, CartesianGrid } from 'recharts'
import { FiAlertTriangle, FiBookOpen, FiBriefcase, FiTrendingUp, FiUsers, FiSend, FiFlag } from 'react-icons/fi'
import { skillGapService } from '../../services/index'
import { LoadingSpinner, StatCard, Modal } from '../../components/index'

const monthLabel = (m) => new Date(`${m}-01T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: '2-digit' })

/**
 * Skills progression and gap tracking: which skills open jobs need, how many jobseekers
 * have them, which trainings close the gap, and who needs targeted support.
 */
export default function SkillsGap() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [applicantId, setApplicantId] = useState(null)
  const [applicant, setApplicant] = useState(null)
  const [note, setNote] = useState('')
  const [sending, setSending] = useState(null)

  const load = async () => {
    setLoading(true)
    try {
      const res = await skillGapService.getOverview()
      setData(res.data.data)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to load skills gap analysis')
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => { load() }, [])

  const openApplicant = async (id) => {
    setApplicantId(id)
    setApplicant(null)
    setNote('')
    try {
      const res = await skillGapService.getApplicant(id)
      setApplicant(res.data.data)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to load applicant')
      setApplicantId(null)
    }
  }

  const recommend = async (training) => {
    setSending(training.id)
    try {
      await skillGapService.recommendTraining(applicantId, training.id, note.trim() || undefined)
      toast.success(`Recommendation for "${training.program_name}" sent.`)
      const res = await skillGapService.getApplicant(applicantId)
      setApplicant(res.data.data)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to send recommendation')
    } finally {
      setSending(null)
    }
  }

  if (loading) return <LoadingSpinner text="Analysing skills demand and supply..." />
  if (!data) return <div className="alert-danger">Skills gap analysis is unavailable. <button onClick={load} className="btn-secondary btn-sm ml-auto">Retry</button></div>

  const top = data.skills.slice(0, 10).map((s) => ({ name: s.skill_name, 'Open jobs': s.open_jobs, Vacancies: s.vacancies, 'Jobseekers with skill': s.jobseekers_with_skill }))
  const trend = data.trend.map((t) => ({ ...t, label: monthLabel(t.month) }))
  const trainingsForMissing = applicant ? Object.values(applicant.missing_in_demand.flatMap((m) => m.trainings).reduce((acc, t) => ({ ...acc, [t.id]: t }), {})) : []

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">Skills Gap & Progression</h1>
        <p className="page-subtitle">Compare the skills employers need with the skills of registered jobseekers, track training progress, and act on gaps.</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Open job postings" value={data.totals.open_jobs} icon={<FiBriefcase />} color="bg-blue-50 text-blue-600" />
        <StatCard label="Active jobseekers" value={data.totals.jobseekers} icon={<FiUsers />} color="bg-green-50 text-green-600" />
        <StatCard label="Skills in demand" value={data.totals.skills_in_demand} icon={<FiTrendingUp />} color="bg-purple-50 text-purple-600" />
        <StatCard label="In-demand skills without training" value={data.totals.skills_without_training} icon={<FiAlertTriangle />} color="bg-yellow-50 text-yellow-600" />
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <section className="card-flat">
          <h2 className="font-bold text-gray-800">Top skills: demand vs. supply</h2>
          <p className="text-sm text-gray-500">Vacancies needing each skill compared with jobseekers who have it.</p>
          {top.length === 0 ? <p className="py-12 text-center text-sm text-gray-400">No open job postings with identifiable skills.</p> : (
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={top} layout="vertical" margin={{ top: 10, right: 16, left: 30, bottom: 4 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" allowDecimals={false} tick={{ fontSize: 12 }} />
                <YAxis type="category" dataKey="name" width={120} tick={{ fontSize: 11 }} />
                <Tooltip />
                <Legend />
                <Bar dataKey="Vacancies" fill="#2563eb" radius={[0, 4, 4, 0]} />
                <Bar dataKey="Jobseekers with skill" fill="#0f766e" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </section>
        <section className="card-flat">
          <h2 className="font-bold text-gray-800">Skills progression (last 12 months)</h2>
          <p className="text-sm text-gray-500">Completed trainings and skills gained by jobseekers each month.</p>
          <ResponsiveContainer width="100%" height={300}>
            <LineChart data={trend} margin={{ top: 10, right: 16, left: -16, bottom: 4 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="completions" name="Training completions" stroke="#2563eb" strokeWidth={2} />
              <Line type="monotone" dataKey="skills_gained" name="Skills gained" stroke="#f59e0b" strokeWidth={2} />
            </LineChart>
          </ResponsiveContainer>
        </section>
      </div>

      <section className="card-flat">
        <h2 className="mb-3 font-bold text-gray-800">Skill gaps and available trainings</h2>
        <div className="table-wrapper">
          <table className="table">
            <thead><tr><th>Skill</th><th>Open jobs</th><th>Vacancies</th><th>Jobseekers with skill</th><th>Shortage</th><th>Trainings that teach it</th></tr></thead>
            <tbody>
              {data.skills.length === 0 && <tr><td colSpan={6} className="text-center text-gray-400">No skills demand found in open postings.</td></tr>}
              {data.skills.map((s) => (
                <tr key={s.skill_id}>
                  <td><span className="font-semibold text-gray-800">{s.skill_name}</span><p className="text-xs text-gray-400">{s.sample_jobs.join(', ')}</p></td>
                  <td>{s.open_jobs}</td>
                  <td>{s.vacancies}</td>
                  <td>{s.jobseekers_with_skill} <span className="text-xs text-gray-400">({s.coverage_pct}%)</span></td>
                  <td>{s.shortage > 0 ? <span className="badge badge-red">{s.shortage}</span> : <span className="badge badge-green">0</span>}</td>
                  <td>{s.trainings.length === 0 ? <span className="badge badge-yellow">None scheduled — consider a CLCDO program</span> : s.trainings.map((t) => <span key={t.id} className="badge badge-blue mb-1 mr-1 inline-block">{t.program_name}</span>)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="card-flat">
        <h2 className="font-bold text-gray-800">Applicants who may need support</h2>
        <p className="mb-3 text-sm text-gray-500">Jobseekers missing many in-demand skills and not yet placed, or flagged by an employer as needing training.</p>
        {data.applicants_needing_support.length === 0 ? <p className="py-6 text-center text-sm text-gray-400">No applicants currently need targeted support.</p> : (
          <div className="table-wrapper">
            <table className="table">
              <thead><tr><th>Applicant</th><th>Skills</th><th>Missing in-demand skills</th><th>Status</th><th></th></tr></thead>
              <tbody>
                {data.applicants_needing_support.map((a) => (
                  <tr key={a.id}>
                    <td><span className="font-semibold text-gray-800">{a.name}</span><p className="text-xs text-gray-400">{a.city || ''}</p></td>
                    <td>{a.skill_count}</td>
                    <td className="text-sm">{a.missing_top_skills.join(', ')}{a.missing_count > a.missing_top_skills.length ? ` +${a.missing_count - a.missing_top_skills.length}` : ''}</td>
                    <td className="space-x-1">
                      {a.flagged_by_employer && <span className="badge badge-yellow inline-flex items-center gap-1"><FiFlag size={11} /> Employer: needs training</span>}
                      {a.placed ? <span className="badge badge-green">Placed</span> : <span className="badge badge-gray">Not placed</span>}
                      {a.active_trainings > 0 && <span className="badge badge-blue">{a.active_trainings} training(s) in progress</span>}
                    </td>
                    <td><button onClick={() => openApplicant(a.id)} className="btn-secondary btn-sm">View & act</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {applicantId && (
        <Modal title={applicant ? `${applicant.user.first_name} ${applicant.user.last_name}` : 'Applicant'} onClose={() => setApplicantId(null)} size="xl">
          {!applicant ? <LoadingSpinner /> : (
            <div className="space-y-5">
              <div className="flex flex-wrap gap-2 text-sm">
                {applicant.demand_coverage_pct !== null && <span className="badge badge-blue">Covers {applicant.demand_coverage_pct}% of in-demand skills</span>}
                <span className="badge badge-gray">{applicant.skills.length} skills in profile</span>
                {applicant.user.employment_status && <span className="badge badge-gray capitalize">{applicant.user.employment_status.replace('_', ' ')}</span>}
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <h3 className="mb-2 font-semibold text-gray-800">Missing in-demand skills</h3>
                  {applicant.missing_in_demand.length === 0 ? <p className="text-sm text-gray-400">None — this applicant has every in-demand skill.</p> : (
                    <ul className="space-y-1 text-sm">{applicant.missing_in_demand.slice(0, 10).map((m) => <li key={m.skill_id}><span className="font-medium">{m.skill_name}</span> <span className="text-gray-400">· {m.open_jobs} open job(s)</span></li>)}</ul>
                  )}
                </div>
                <div>
                  <h3 className="mb-2 font-semibold text-gray-800">Skills progression</h3>
                  {applicant.timeline.length === 0 ? <p className="text-sm text-gray-400">No trainings yet.</p> : (
                    <ol className="space-y-2 border-l-2 border-blue-100 pl-3 text-sm">
                      {applicant.timeline.map((t, i) => (
                        <li key={i}>
                          <span className="font-medium">{t.program_name}</span> <span className={`badge ${t.status === 'completed' ? 'badge-green' : 'badge-gray'}`}>{t.status}</span>
                          <p className="text-xs text-gray-400">{t.date || 'No date'}{t.skills.length ? ` · ${t.skills.join(', ')}` : ''}</p>
                        </li>
                      ))}
                    </ol>
                  )}
                </div>
              </div>
              {applicant.placement_feedback?.length > 0 && (
                <div>
                  <h3 className="mb-2 font-semibold text-gray-800">Employer feedback on placements</h3>
                  <ul className="space-y-1 text-sm">{applicant.placement_feedback.map((f, i) => <li key={i}>{f.job_title} · {f.company_name} — {f.rating}/5{f.outcome_label ? `, ${f.outcome_label}` : ''}</li>)}</ul>
                </div>
              )}
              <div className="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <h3 className="mb-2 flex items-center gap-2 font-semibold text-blue-900"><FiBookOpen /> Recommend a training (targeted intervention)</h3>
                {trainingsForMissing.length === 0 ? <p className="text-sm text-blue-800">No open training programs teach this applicant's missing skills. Coordinate with CLCDO to schedule one.</p> : (
                  <>
                    <input className="input mb-3" maxLength={500} placeholder="Optional note to the applicant" value={note} onChange={(e) => setNote(e.target.value)} />
                    <div className="space-y-2">
                      {trainingsForMissing.map((t) => (
                        <div key={t.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white p-2">
                          <span className="text-sm"><span className="font-medium">{t.program_name}</span> <span className="text-gray-400">· {t.status}{t.start_date ? ` · starts ${t.start_date}` : ''}</span></span>
                          <button onClick={() => recommend(t)} disabled={sending === t.id} className="btn-primary btn-sm"><FiSend /> {sending === t.id ? 'Sending...' : 'Recommend'}</button>
                        </div>
                      ))}
                    </div>
                  </>
                )}
                {applicant.interventions?.length > 0 && (
                  <div className="mt-3 text-xs text-blue-900">
                    <p className="font-semibold">Previous recommendations</p>
                    <ul className="list-disc pl-5">{applicant.interventions.map((n, i) => <li key={i}>{n.created_at}: {n.message.slice(0, 120)}{n.message.length > 120 ? '…' : ''} {Number(n.is_read) ? '(read)' : '(unread)'}</li>)}</ul>
                  </div>
                )}
              </div>
            </div>
          )}
        </Modal>
      )}
    </div>
  )
}
