import { useState, useEffect, useCallback } from 'react'
import { employerService } from '../../services/index'
import { LoadingSpinner, EmptyState, Pagination, ConfirmDialog } from '../../components/index'
import toast from 'react-hot-toast'
import { FiCheck, FiX } from 'react-icons/fi'
import { format } from 'date-fns'
import { API_BASE_URL } from '../../config/api.js'

const BASE_URL = API_BASE_URL
const normalizeVerificationStatus = status => status === 'rejected' ? 'declined' : status
const statusBadge = { pending:'badge-yellow', approved:'badge-green', declined:'badge-red', rejected:'badge-red' }

export default function EmployerVerification() {
  const [employers, setEmployers] = useState([])
  const [total, setTotal]   = useState(0)
  const [pages, setPages]   = useState(1)
  const [page, setPage]     = useState(1)
  const [tab, setTab]       = useState('pending')
  const [loading, setLoading] = useState(true)
  const [confirm, setConfirm] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await employerService.getAll({ status: tab, page, limit: 10 })
      setEmployers(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load') }
    setLoading(false)
  }, [tab, page])

  useEffect(() => { load() }, [load])

  const handleVerify = async (id, status) => {
    try {
      await employerService.verify(id, status)
      toast.success(`Employer ${status}`)
      setConfirm(null)
      load()
    } catch { toast.error('Action failed') }
  }

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Employer Verification 🛡️</h1>
        <p className="page-subtitle">Review and verify employer registrations</p>
      </div>

      <div className="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
        {['pending','approved','declined'].map(t => (
          <button key={t} onClick={() => { setTab(t); setPage(1) }}
            className={`px-4 py-2 rounded-lg text-sm font-semibold capitalize transition-all ${tab === t ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'}`}>
            {t}
          </button>
        ))}
      </div>

      {loading ? <LoadingSpinner /> : employers.length === 0 ? (
        <EmptyState icon="🏢" title={`No ${tab} employers`} description={`No employers with ${tab} status`} />
      ) : (
        <div className="space-y-3">
          {employers.map(emp => (
            <div key={emp.id} className="card">
              <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <p className="font-bold text-gray-900">{emp.company_name}</p>
                    <span className={statusBadge[normalizeVerificationStatus(emp.verification_status)]}>{normalizeVerificationStatus(emp.verification_status)}</span>
                  </div>
                  <p className="text-sm text-gray-600">{emp.industry}</p>
                  <div className="text-xs text-gray-500 mt-1 space-y-0.5">
                    <p>👤 {emp.first_name} {emp.last_name} — {emp.email}</p>
                    <p>📧 {emp.contact_email} · 📞 {emp.contact_phone}</p>
                    <p>📍 {emp.company_address}</p>
                    <p>📅 Registered: {format(new Date(emp.created_at), 'MMM d, yyyy')}</p>
                  </div>
                  {emp.business_permit && (
                    <a href={`${BASE_URL}/${emp.business_permit}`} target="_blank" rel="noopener noreferrer"
                      className="btn-secondary btn-sm mt-2 inline-flex">
                      📄 View Business Permit
                    </a>
                  )}
                </div>
                {emp.verification_status === 'pending' && (
                  <div className="flex gap-2 flex-shrink-0">
                    <button onClick={() => setConfirm({ id: emp.id, status: 'approved', name: emp.company_name })} className="btn-success btn-sm">
                      <FiCheck /> Approve
                    </button>
                    <button onClick={() => setConfirm({ id: emp.id, status: 'declined', name: emp.company_name })} className="btn-danger btn-sm">
                      <FiX /> Decline
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <Pagination page={page} pages={pages} onPageChange={setPage} />

      {confirm && (
        <ConfirmDialog
          title={`${confirm.status === 'approved' ? 'Approve' : 'Decline'} Employer`}
          message={`${confirm.status === 'approved' ? 'Approve' : 'Decline'} "${confirm.name}"? ${confirm.status === 'approved' ? 'They will be able to post jobs.' : 'They will be notified of the decline.'}`}
          onConfirm={() => handleVerify(confirm.id, confirm.status)}
          onCancel={() => setConfirm(null)}
          danger={confirm.status === 'declined'}
        />
      )}
    </div>
  )
}