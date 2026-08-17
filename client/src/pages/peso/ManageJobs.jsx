// ManageJobs.jsx — PESO
import { useState, useEffect, useCallback } from 'react'
import { jobService } from '../../services/jobService'
import { Pagination, LoadingSpinner, EmptyState } from '../../components/index'
import { format } from 'date-fns'
import toast from 'react-hot-toast'

const normalizeApprovalStatus = status => status === 'rejected' ? 'declined' : status
const statusBadge = { pending:'badge-yellow', approved:'badge-green', declined:'badge-red', rejected:'badge-red' }

export default function ManageJobs() {
  const [jobs, setJobs]   = useState([])
  const [total, setTotal] = useState(0)
  const [pages, setPages] = useState(1)
  const [page, setPage]   = useState(1)
  const [status, setStatus] = useState('approved')
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await jobService.getAll({ approval_status: status, search, page, limit: 15 })
      setJobs(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load') }
    setLoading(false)
  }, [status, search, page])

  useEffect(() => { load() }, [load])

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">All Jobs</h1>
        <p className="page-subtitle">{total} jobs</p>
      </div>
      <div className="flex flex-col sm:flex-row gap-3">
        <input className="input flex-1" placeholder="Search jobs..." value={search}
          onChange={e => { setSearch(e.target.value); setPage(1) }} />
        <div className="flex gap-1 bg-gray-100 p-1 rounded-xl">
          {['approved','pending','declined'].map(s => (
            <button key={s} onClick={() => { setStatus(s === 'declined' ? 'rejected' : s); setPage(1) }}
              className={`px-3 py-1.5 rounded-lg text-sm font-semibold capitalize transition-all ${status === (s === 'declined' ? 'rejected' : s) ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'}`}>
              {s}
            </button>
          ))}
        </div>
      </div>

      {loading ? <LoadingSpinner /> : jobs.length === 0 ? (
        <EmptyState icon="📋" title="No jobs found" />
      ) : (
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr><th>Title</th><th>Company</th><th>Location</th><th>Type</th><th>Posted</th><th>Status</th></tr>
            </thead>
            <tbody>
              {jobs.map(job => (
                <tr key={job.id}>
                  <td className="font-semibold">{job.title}</td>
                  <td>{job.company_name}</td>
                  <td>{job.location}</td>
                  <td className="capitalize">{job.job_type}</td>
                  <td>{format(new Date(job.created_at), 'MMM d')}</td>
                  <td><span className={statusBadge[normalizeApprovalStatus(job.approval_status)]}>{normalizeApprovalStatus(job.approval_status)}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pagination page={page} pages={pages} onPageChange={setPage} />
    </div>
  )
}