import { useState, useEffect, useCallback } from 'react'
import { jobService } from '../../services/jobService'
import { Pagination, LoadingSpinner, EmptyState, ConfirmDialog, Modal } from '../../components/index'
import toast from 'react-hot-toast'
import { FiCheck, FiX, FiEye } from 'react-icons/fi'
import { format } from 'date-fns'

const normalizeApprovalStatus = status => status === 'rejected' ? 'declined' : status

export default function ApproveJobs() {
  const [jobs, setJobs]     = useState([])
  const [total, setTotal]   = useState(0)
  const [pages, setPages]   = useState(1)
  const [page, setPage]     = useState(1)
  const [loading, setLoading] = useState(true)
  const [confirm, setConfirm] = useState(null)
  const [preview, setPreview] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await jobService.getPending({ page, limit: 10 })
      setJobs(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load') }
    setLoading(false)
  }, [page])

  useEffect(() => { load() }, [load])

  const handleApprove = async (id, status) => {
    try {
      await jobService.approve(id, status)
      toast.success(`Job ${status}`)
      setConfirm(null)
      load()
    } catch { toast.error('Action failed') }
  }

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Approve Jobs ✅</h1>
        <p className="page-subtitle">{total} job{total !== 1 ? 's' : ''} pending review</p>
      </div>

      {jobs.length === 0 ? (
        <EmptyState icon="✅" title="All caught up!" description="No pending jobs to review right now" />
      ) : (
        <div className="space-y-3">
          {jobs.map(job => (
            <div key={job.id} className="card flex flex-col sm:flex-row sm:items-center gap-4">
              <div className="flex-1 min-w-0">
                <p className="font-bold text-gray-900">{job.title}</p>
                <p className="text-sm text-blue-700 font-semibold">{job.company_name}</p>
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 mt-1">
                  <span>📍 {job.location}</span>
                  <span>💼 {job.job_type}</span>
                  {job.salary_range && <span>💰 {job.salary_range}</span>}
                  <span>📅 Posted {format(new Date(job.created_at), 'MMM d, yyyy')}</span>
                </div>
              </div>
              <div className="flex gap-2 flex-shrink-0">
                <button onClick={() => setPreview(job)} className="btn-secondary btn-sm">
                  <FiEye size={14} /> View
                </button>
                <button onClick={() => setConfirm({ id: job.id, status: 'approved', title: job.title })} className="btn-success btn-sm">
                  <FiCheck size={14} /> Approve
                </button>
                <button onClick={() => setConfirm({ id: job.id, status: 'declined', title: job.title })} className="btn-danger btn-sm">
                  <FiX size={14} /> Decline
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Pagination page={page} pages={pages} onPageChange={setPage} />

      {confirm && (
        <ConfirmDialog
          title={`${confirm.status === 'approved' ? 'Approve' : 'Decline'} Job`}
          message={`Are you sure you want to ${confirm.status === 'approved' ? 'approve' : 'decline'} "${confirm.title}"?`}
          onConfirm={() => handleApprove(confirm.id, confirm.status)}
          onCancel={() => setConfirm(null)}
          danger={confirm.status === 'declined'}
        />
      )}

      {preview && (
        <Modal title={preview.title} onClose={() => setPreview(null)} size="lg"
          footer={
            <div className="flex gap-3">
              <button onClick={() => { setConfirm({ id: preview.id, status: 'declined', title: preview.title }); setPreview(null) }} className="btn-danger btn-sm"><FiX /> Decline</button>
              <button onClick={() => { setConfirm({ id: preview.id, status: 'approved', title: preview.title }); setPreview(null) }} className="btn-success btn-sm"><FiCheck /> Approve</button>
            </div>
          }
        >
          <div className="space-y-3 text-sm">
            <div className="grid grid-cols-2 gap-3">
              <div><span className="font-semibold">Company:</span> {preview.company_name}</div>
              <div><span className="font-semibold">Type:</span> <span className="capitalize">{preview.job_type}</span></div>
              <div><span className="font-semibold">Location:</span> {preview.location}</div>
              <div><span className="font-semibold">Salary:</span> {preview.salary_range || '—'}</div>
              <div><span className="font-semibold">Vacancies:</span> {preview.vacancies}</div>
              <div><span className="font-semibold">Deadline:</span> {preview.deadline ? format(new Date(preview.deadline), 'MMM d, yyyy') : '—'}</div>
            </div>
            {preview.description && (
              <div>
                <p className="font-semibold mb-1">Description:</p>
                <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{preview.description}</p>
              </div>
            )}
            {preview.requirements && (
              <div>
                <p className="font-semibold mb-1">Requirements:</p>
                <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{preview.requirements}</p>
              </div>
            )}
          </div>
        </Modal>
      )}
    </div>
  )
}