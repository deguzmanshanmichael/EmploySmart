import { useState, useEffect, useCallback } from 'react'
import { applicationService, messageService } from '../../services/index'
import { ApplicationCard, Pagination, LoadingSpinner, EmptyState, ConfirmDialog, Modal } from '../../components/index'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'

const normalizeApplicationStatus = status => status === 'rejected' ? 'declined' : status
const STATUS_TABS = ['all', 'pending', 'reviewed', 'accepted', 'declined']

export default function MyApplications() {
  const [apps, setApps]   = useState([])
  const [total, setTotal] = useState(0)
  const [pages, setPages] = useState(1)
  const [page, setPage]   = useState(1)
  const [loading, setLoading] = useState(true)
  const [tab, setTab]     = useState('all')
  const [confirm, setConfirm] = useState(null)
  const [threadApp, setThreadApp] = useState(null)
  const [threadMessages, setThreadMessages] = useState([])
  const [threadLoading, setThreadLoading] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await applicationService.getMyApplications({ page, limit: 10 })
      let data = res.data.data || []
      if (tab !== 'all') data = data.filter(a => a.application_status === (tab === 'declined' ? 'rejected' : tab))
      setApps(data)
      setTotal(res.data.pagination?.total || data.length)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load applications') }
    setLoading(false)
  }, [page, tab])

  useEffect(() => { load() }, [load])

  const handleWithdraw = async (id) => {
    try {
      await applicationService.withdraw(id)
      toast.success('Application withdrawn')
      setConfirm(null)
      load()
    } catch (err) { toast.error(err.response?.data?.message || 'Failed') }
  }

  const openMessages = async (app) => {
    setThreadApp(app)
    setThreadLoading(true)
    setThreadMessages([])
    try {
      const res = await messageService.getThread(app.id)
      setThreadMessages(res.data?.data?.messages || [])
    } catch {
      toast.error('Unable to load messages')
    }
    setThreadLoading(false)
  }

  const tabCounts = {}
  apps.forEach(a => { 
    const normalizedStatus = normalizeApplicationStatus(a.application_status)
    tabCounts[normalizedStatus] = (tabCounts[normalizedStatus] || 0) + 1 
  })

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">My Applications</h1>
        <p className="page-subtitle">{total} total applications</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 overflow-x-auto scrollbar-hide bg-gray-100 p-1 rounded-xl w-fit">
        {STATUS_TABS.map(t => (
          <button
            key={t}
            onClick={() => { setTab(t); setPage(1) }}
            className={`px-4 py-2 rounded-lg text-sm font-semibold capitalize whitespace-nowrap transition-all ${tab === t ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-800'}`}
          >
            {t} {tabCounts[t] ? `(${tabCounts[t]})` : ''}
          </button>
        ))}
      </div>

      {loading ? <LoadingSpinner /> : apps.length === 0 ? (
        <EmptyState
          icon="📄" title="No applications yet"
          description="Start applying to jobs that match your skills"
          action={<Link to="/jobseeker/jobs" className="btn-primary btn-sm">Browse Jobs</Link>}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {apps.map(app => (
            <ApplicationCard
              key={app.id}
              app={app}
              onWithdraw={(id) => setConfirm(id)}
              onOpenMessages={(selectedApp) => openMessages(selectedApp)}
            />
          ))}
        </div>
      )}

      <Pagination page={page} pages={pages} onPageChange={setPage} />

      {confirm && (
        <ConfirmDialog
          title="Withdraw Application"
          message="Are you sure you want to withdraw this application? This cannot be undone."
          onConfirm={() => handleWithdraw(confirm)}
          onCancel={() => setConfirm(null)}
        />
      )}

      {threadApp && (
        <Modal title={`Employer's Notes for ${threadApp.title}`} onClose={() => setThreadApp(null)} size="md">
          {threadLoading ? (
            <LoadingSpinner text="Loading notes..." />
          ) : threadMessages.length === 0 ? (
            <p className="text-sm text-gray-500">The employer has not added any notes yet.</p>
          ) : (
            <div className="space-y-2 max-h-80 overflow-y-auto">
              {threadMessages.map(msg => (
                <div key={msg.id} className={`rounded-lg p-3 text-sm ${msg.sender_role === 'employer' ? 'bg-blue-50 text-blue-800' : 'bg-gray-100 text-gray-700'}`}>
                  <div>{msg.message_text}</div>
                </div>
              ))}
            </div>
          )}
        </Modal>
      )}
    </div>
  )
}