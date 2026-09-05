import { useState, useEffect, useCallback } from 'react'
import api from '../../services/api'
import { LoadingSpinner, EmptyState, Pagination } from '../../components/index'
import { format } from 'date-fns'
import { FiRefreshCw, FiPrinter } from 'react-icons/fi'
import toast from 'react-hot-toast'

export default function SystemLogs() {
  const [logs, setLogs]   = useState([])
  const [total, setTotal] = useState(0)
  const [pages, setPages] = useState(1)
  const [page, setPage]   = useState(1)
  const [loading, setLoading] = useState(true)
  const [search, setSearch]   = useState('')
  const [role, setRole] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get('/users/logs', { params: { page, limit: 20, search, role, date_from: dateFrom, date_to: dateTo } })
      setLogs(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch {
      // Fallback if logs endpoint not separately implemented
      setLogs([])
      setLoading(false)
    }
    setLoading(false)
  }, [page, search, role, dateFrom, dateTo])

  useEffect(() => { load() }, [load])

  const actionColor = (action) => {
    if (action?.includes('LOGIN'))    return 'badge-green'
    if (action?.includes('DELETE'))   return 'badge-red'
    if (action?.includes('REGISTER')) return 'badge-blue'
    return 'badge-gray'
  }

  const printLogs = () => {
    const printWindow = window.open('', '_blank', 'width=1000,height=700')
    if (!printWindow) return toast.error('Please allow pop-ups to print logs.')
    const rows = logs.map(log => `<tr><td>${log.first_name || 'System'} ${log.last_name || ''}</td><td>${log.role || 'system'}</td><td>${log.action}</td><td>${format(new Date(log.log_time), 'MMM d, yyyy h:mm a')}</td></tr>`).join('')
    printWindow.document.write(`<html><head><title>EmploySmart System Logs</title><style>body{font-family:Arial,sans-serif;padding:32px;color:#111}h1{font-size:20px}p{color:#555}table{border-collapse:collapse;width:100%;margin-top:20px}th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:12px}th{background:#f3f4f6}</style></head><body><h1>EmploySmart System Logs</h1><p>Filtered results: ${logs.length} entries</p><table><thead><tr><th>Username</th><th>Role</th><th>Activity</th><th>Date and time</th></tr></thead><tbody>${rows}</tbody></table></body></html>`)
    printWindow.document.close()
    printWindow.focus()
    printWindow.print()
  }

  return (
    <div className="animate-fade-in space-y-5">
      <div className="flex items-center justify-between">
        <div className="page-header mb-0">
          <h1 className="page-title">System Logs 📋</h1>
          <p className="page-subtitle">{total} log entries</p>
        </div>
        <div className="flex gap-2"><button onClick={printLogs} className="btn-secondary btn-sm"><FiPrinter size={14} /> Print</button><button onClick={load} className="btn-secondary btn-sm">
          <FiRefreshCw size={14} className={loading ? 'animate-spin' : ''} /> Refresh
        </button></div>
      </div>

      <div className="grid gap-3 md:grid-cols-4">
        <input className="input" placeholder="Filter by action..." value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} />
        <select className="input" value={role} onChange={e => { setRole(e.target.value); setPage(1) }}><option value="">All roles</option><option value="admin">Admin</option><option value="peso">PESO</option><option value="clcdo">CLCDO</option><option value="employer">Employer</option><option value="jobseeker">Jobseeker</option></select>
        <input type="date" className="input" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1) }} />
        <input type="date" className="input" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1) }} />
      </div>

      {loading ? <LoadingSpinner /> : logs.length === 0 ? (
        <EmptyState icon="📋" title="No logs found" description="System activity logs will appear here" />
      ) : (
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr><th>Username</th><th>Role</th><th>Action</th><th>Date and time</th></tr>
            </thead>
            <tbody>
              {logs.map(log => (
                <tr key={log.id}>
                  <td className="text-xs">{log.first_name ? `${log.first_name} ${log.last_name || ''}` : 'System'}</td>
                  <td><span className="badge-gray">{log.role || 'system'}</span></td>
                  <td><span className={actionColor(log.action)}>{log.action}</span></td>
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