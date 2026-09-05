import { useState, useEffect } from 'react'
import { userService } from '../../services/index'
import { LoadingSpinner, Modal, ConfirmDialog } from '../../components/index'
import { authService } from '../../services/authService'
import api from '../../services/api'
import toast from 'react-hot-toast'
import { FiPlus, FiRefreshCw, FiArchive } from 'react-icons/fi'

const STAFF_ROLES = ['peso', 'clcdo', 'admin']
const roleBadge   = { peso:'badge-green', clcdo:'badge-yellow', admin:'badge-red' }
const roleLabel   = { peso:'PESO Officer', clcdo:'CLCDO Staff', admin:'Administrator' }

export default function RoleManagement() {
  const [staff, setStaff]   = useState([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [creating, setCreating] = useState(false)
  const [archiving, setArchiving] = useState(false)
  const [confirmArchive, setConfirmArchive] = useState(null)
  const [form, setForm] = useState({
    first_name:'', last_name:'', email:'', password:'', sex:'male', role:'peso'
  })

  const load = async () => {
    setLoading(true)
    try {
      const results = await Promise.all(
        STAFF_ROLES.map(r => userService.getAll({ role: r, limit: 100 }))
      )
      const all = results.flatMap(r => r.data.data || [])
      setStaff(all)
    } catch {}
    setLoading(false)
  }

  useEffect(() => { load() }, [])

  const handleCreate = async (e) => {
    if (e?.preventDefault) e.preventDefault()
    if (!form.first_name.trim() || !form.last_name.trim()) {
      toast.error('First name and last name are required')
      return
    }
    if (!form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      toast.error('Valid email is required')
      return
    }
    if (!form.password || form.password.length < 6) {
      toast.error('Password must be at least 6 characters')
      return
    }

    setCreating(true)
    try {
      // Admin creates staff directly via API — bypass verification
      await api.post('/auth/register', { ...form, is_verified: true })
      toast.success('Staff account created!')
      setShowModal(false)
      setForm({ first_name:'', last_name:'', email:'', password:'', sex:'male', role:'peso' })
      load()
    } catch (err) { toast.error(err.response?.data?.message || 'Failed to create') }
    setCreating(false)
  }

  const handleVerify = async (id) => {
    try {
      await userService.verify(id)
      toast.success('Staff verified')
      load()
    } catch { toast.error('Failed') }
  }

  const handleArchive = async () => {
    if (!confirmArchive) return
    setArchiving(true)
    try {
      await userService.delete(confirmArchive.id)
      toast.success('Staff account archived')
      setConfirmArchive(null)
      load()
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to archive this account')
    } finally {
      setArchiving(false)
    }
  }

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-5">
      <div className="flex items-center justify-between">
        <div className="page-header mb-0">
          <h1 className="page-title">Role Management 🛡️</h1>
          <p className="page-subtitle">Manage PESO, CLCDO, and Admin accounts</p>
        </div>
        <div className="flex gap-2">
          <button onClick={load} className="btn-secondary btn-sm"><FiRefreshCw size={14} /></button>
          <button onClick={() => setShowModal(true)} className="btn-primary btn-sm"><FiPlus /> Add Staff</button>
        </div>
      </div>

      {STAFF_ROLES.map(r => {
        const members = staff.filter(s => s.role === r)
        return (
          <div key={r} className="card-flat">
            <div className="flex items-center gap-2 mb-3">
              <span className={roleBadge[r]}>{roleLabel[r]}</span>
              <span className="text-sm text-gray-500">({members.length} {members.length === 1 ? 'account' : 'accounts'})</span>
            </div>
            {members.length === 0 ? (
              <p className="text-sm text-gray-400 italic">No {roleLabel[r]} accounts</p>
            ) : (
              <div className="table-wrapper">
                <table className="table">
                  <thead><tr><th>Name</th><th>Email</th><th>Verified</th><th>Actions</th></tr></thead>
                  <tbody>
                    {members.map(u => (
                      <tr key={u.id}>
                        <td className="font-semibold">{u.first_name} {u.last_name}</td>
                        <td className="text-xs text-gray-500">{u.email}</td>
                        <td>
                          {u.is_verified
                            ? <span className="badge-green">✓ Active</span>
                            : <button onClick={() => handleVerify(u.id)} className="btn-success btn-sm">Activate</button>}
                        </td>
                          <td><button onClick={() => setConfirmArchive(u)} title="Archive account" className="btn-danger btn-sm"><FiArchive size={14} /> Archive</button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )
      })}

      {confirmArchive && (
        <ConfirmDialog
          title="Archive Staff Account"
          message={`Archive ${confirmArchive.first_name} ${confirmArchive.last_name}? Accounts with pending job postings or applications cannot be archived.`}
          onConfirm={handleArchive}
          onCancel={() => setConfirmArchive(null)}
        />
      )}

      {showModal && (
        <Modal title="Create Staff Account" onClose={() => setShowModal(false)}
          footer={
            <>
              <button onClick={() => setShowModal(false)} className="btn-secondary">Cancel</button>
              <button onClick={handleCreate} disabled={creating} className="btn-primary">
                {creating ? <><div className="spinner w-4 h-4" /> Creating...</> : 'Create Account'}
              </button>
            </>
          }
        >
          <form onSubmit={handleCreate} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div className="form-group">
                <label className="label">First Name *</label>
                <input className="input" value={form.first_name} onChange={e => set('first_name', e.target.value)} required />
              </div>
              <div className="form-group">
                <label className="label">Last Name *</label>
                <input className="input" value={form.last_name} onChange={e => set('last_name', e.target.value)} required />
              </div>
            </div>
            <div className="form-group">
              <label className="label">Email *</label>
              <input type="email" className="input" value={form.email} onChange={e => set('email', e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="label">Password *</label>
              <input type="password" className="input" value={form.password} onChange={e => set('password', e.target.value)} required />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="form-group">
                <label className="label">Sex</label>
                <select className="input" value={form.sex} onChange={e => set('sex', e.target.value)}>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div className="form-group">
                <label className="label">Role *</label>
                <select className="input" value={form.role} onChange={e => set('role', e.target.value)}>
                  <option value="peso">PESO</option>
                  <option value="clcdo">CLCDO</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}