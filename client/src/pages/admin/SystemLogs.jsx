import { useState, useEffect, useCallback } from 'react'
import api from '../../services/api'
import { LoadingSpinner, EmptyState, Pagination } from '../../components/index'
import { format } from 'date-fns'
import { FiRefreshCw } from 'react-icons/fi'
import toast from 'react-hot-toast'

export default function SystemLogs() {
  const [logs, setLogs]   = useState([])
  const [total, setTotal] = useState(0)
  const [pages, setPages] = useState(1)
  const [page, setPage]   = useState(1)
  const [loading, setLoading] = useState(true)
  const [search, setSearch]   = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get('/users/logs', { params: { page, limit: 20, search } })
      setLogs(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch {
      // Fallback if logs endpoint not separately implemented
      setLogs([])
      setLoading(false)
    }
    setLoading(false)
  }, [page, search])

  useEffect(() => { load() }, [load])

  const actionColor = (action) => {
    if (action?.includes('LOGIN'))    return 'badge-green'
    if (action?.includes('DELETE'))   return 'badge-red'
    if (action?.includes('REGISTER')) return 'badge-blue'
    return 'badge-gray'
  }

  return (
    <div className="animate-fade-in space-y-5">
      <div className="flex items-center justify-between">
        <div className="page-header mb-0">
          <h1 className="page-title">System Logs 📋</h1>
          <p className="page-subtitle">{total} log entries</p>
        </div>
        <button onClick={load} className="btn-secondary btn-sm">
          <FiRefreshCw size={14} className={loading ? 'animate-spin' : ''} /> Refresh
        </button>
      </div>

      <input className="input max-w-sm" placeholder="Filter by action..." value={search}
        onChange={e => { setSearch(e.target.value); setPage(1) }} />

      {loading ? <LoadingSpinner /> : logs.length === 0 ? (
        <EmptyState icon="📋" title="No logs found" description="System activity logs will appear here" />
      ) : (
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr><th>User</th><th>Action</th><th>IP Address</th><th>Time</th></tr>
            </thead>
            <tbody>
              {logs.map(log => (
                <tr key={log.id}>
                  <td className="text-xs">{log.user_id || '—'}</td>
                  <td><span className={actionColor(log.action)}>{log.action}</span></td>
                  <td className="text-xs text-gray-500 font-mono">{log.ip_address}</td>
                  <td className="text-xs text-gray-500">{format(new Date(log.log_time), 'MMM d, yyyy h:mm a')}</td>
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