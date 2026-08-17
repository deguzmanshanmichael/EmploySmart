import { useState, useEffect, useCallback } from 'react'
import { useAuth } from '../../context/AuthContext'
import { jobService } from '../../services/jobService'
import { employerService } from '../../services/index'
import { Pagination, LoadingSpinner, EmptyState, ConfirmDialog } from '../../components/index'
import { Link } from 'react-router-dom'
import { FiPlus, FiTrash2, FiEdit } from 'react-icons/fi'
import toast from 'react-hot-toast'
import { format } from 'date-fns'

const normalizeApprovalStatus = status => status === 'rejected' ? 'declined' : status
const statusBadge = { pending: 'badge-yellow', approved: 'badge-green', declined: 'badge-red', rejected: 'badge-red' }

export default function ManageJobs() {
  const { user } = useAuth()
  const [jobs, setJobs]         = useState([])
  const [employer, setEmployer] = useState(null)
  const [total, setTotal]       = useState(0)
  const [pages, setPages]       = useState(1)
  const [page, setPage]         = useState(1)
  const [loading, setLoading]   = useState(true)
  const [confirm, setConfirm]   = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const eRes = await employerService.getByUser(user.id)
      const emp = eRes.data.data
      setEmployer(emp)
      if (emp?.id) {
        const res = await jobService.getByEmployer(emp.id, { page, limit: 10 })
        setJobs(res.data.data || [])
        setTotal(res.data.pagination?.total || 0)
        setPages(res.data.pagination?.pages || 1)
      }
    } catch { toast.error('Failed to load jobs') }
    setLoading(false)
  }, [user.id, page])

  useEffect(() => { load() }, [load])

  const handleArchive = async (id) => {
    try {
      await jobService.delete(id)
      toast.success('Job archived')
      setConfirm(null)
      load()
    } catch { toast.error('Failed to archive') }
  }

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-5">
      <div className="flex items-center justify-between">
        <div className="page-header mb-0">
          <h1 className="page-title">Manage Jobs</h1>
          <p className="page-subtitle">{total} jobs posted</p>
        </div>
        <Link to="/employer/create-job" className="btn-primary btn-sm">
          <FiPlus /> Post Job
        </Link>
      </div>

      {jobs.length === 0 ? (
        <EmptyState icon="📋" title="No jobs yet" description="Post your first job opening"
          action={<Link to="/employer/create-job" className="btn-primary btn-sm"><FiPlus /> Post a Job</Link>} />
      ) : (
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr>
                <th>Job Title</th>
                <th>Type</th>
                <th>Location</th>
                <th>Deadline</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {jobs.map(job => (
                <tr key={job.id}>
                  <td className="font-semibold text-gray-800">{job.title}</td>
                  <td className="capitalize">{job.job_type}</td>
                  <td>{job.location}</td>
                  <td>{job.deadline ? format(new Date(job.deadline), 'MMM d, yyyy') : '—'}</td>
                  <td><span className={statusBadge[normalizeApprovalStatus(job.approval_status)]}>{normalizeApprovalStatus(job.approval_status)}</span></td>
                  <td>
                    <button onClick={() => setConfirm(job.id)} title="Archive Job" className="btn-danger btn-sm">
                      <FiTrash2 size={13} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Pagination page={page} pages={pages} onPageChange={setPage} />

      {confirm && (
        <ConfirmDialog
          title="Archive Job"
          message="Are you sure you want to archive this job? All applications will also be removed."
          onConfirm={() => handleArchive(confirm)}
          onCancel={() => setConfirm(null)}
        />
      )}
    </div>
  )
}