import { useState, useEffect, useCallback } from 'react'
import { userService } from '../../services/index'
import { Pagination, LoadingSpinner, EmptyState, ConfirmDialog } from '../../components/index'
import toast from 'react-hot-toast'
import { FiCheck, FiTrash2, FiSearch } from 'react-icons/fi'
import { format } from 'date-fns'

const roleBadge = { admin:'badge-red', peso:'badge-green', clcdo:'badge-yellow', employer:'badge-purple', jobseeker:'badge-blue' }

export default function UserManagement() {
  const [users, setUsers]   = useState([])
  const [total, setTotal]   = useState(0)
  const [pages, setPages]   = useState(1)
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [role, setRole]     = useState('')
  const [loading, setLoading] = useState(true)
  const [confirm, setConfirm] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await userService.getAll({ search, role, page, limit: 15 })
      setUsers(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load') }
    setLoading(false)
  }, [search, role, page])

  useEffect(() => { load() }, [load])

  const handleVerify = async (id) => {
    try {
      await userService.verify(id)
      toast.success('User verified')
      load()
    } catch { toast.error('Failed') }
  }

  const handleArchive = async (id) => {
    try {
      await userService.delete(id)
      toast.success('User archived')
      setConfirm(null)
      load()
    } catch { toast.error('Archive failed') }
  }

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">User Management 👥</h1>
        <p className="page-subtitle">{total} total users</p>
      </div>

      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" size={15} />
          <input className="input pl-10" placeholder="Search by name or email..." value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }} />
        </div>
        <select className="input sm:w-44" value={role} onChange={e => { setRole(e.target.value); setPage(1) }}>
          <option value="">All roles</option>
          <option value="jobseeker">Job Seeker</option>
          <option value="employer">Employer</option>
          <option value="peso">PESO</option>
          <option value="clcdo">CLCDO</option>
          <option value="admin">Admin</option>
        </select>
      </div>

      {loading ? <LoadingSpinner /> : users.length === 0 ? (
        <EmptyState icon="👥" title="No users found" />
      ) : (
        <div className="table-wrapper">
          <table className="table">
            <thead>
              <tr><th>Name</th><th>Email</th><th>Role</th><th>Location</th><th>Verified</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.id}>
                  <td className="font-semibold">{u.first_name} {u.last_name}</td>
                  <td className="text-gray-500 text-xs">{u.email}</td>
                  <td><span className={roleBadge[u.role] || 'badge-gray'}>{u.role}</span></td>
                  <td className="text-xs text-gray-500">{u.city || '—'}</td>
                  <td>
                    {u.is_verified
                      ? <span className="badge-green">✓ Verified</span>
                      : <span className="badge-yellow">Pending</span>}
                  </td>
                  <td className="text-xs text-gray-500">{format(new Date(u.created_at), 'MMM d, yyyy')}</td>
                  <td>
                    <div className="flex gap-1">
                      {!u.is_verified && (
                        <button onClick={() => handleVerify(u.id)} title="Verify" className="btn-success btn-sm px-2">
                          <FiCheck size={13} />
                        </button>
                      )}
                      <button onClick={() => setConfirm(u)} title="Archive" className="btn-danger btn-sm px-2">
                        <FiTrash2 size={13} />
                      </button>
                    </div>
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
          title="Archive User"
          message={`Archive "${confirm.first_name} ${confirm.last_name}"? The user will be hidden from the system but their data will be preserved.`}
          onConfirm={() => handleArchive(confirm.id)}
          onCancel={() => setConfirm(null)}
        />
      )}
    </div>
  )
}