import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import toast from 'react-hot-toast'
import { FiMail, FiLock, FiEye, FiEyeOff } from 'react-icons/fi'

export default function StaffLogin() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [showPass, setShowPass] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const roleRoutes = {
    peso: '/peso',
    clcdo: '/clcdo',
    admin: '/admin',
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const user = await login({ ...form, portal: 'staff' })
      if (user.role === 'jobseeker' || user.role === 'employer') {
        throw new Error('This portal is for staff only. Please use the user login page.')
      }
      toast.success(`Welcome back, ${user.first_name}!`)
      navigate(roleRoutes[user.role] || '/')
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Login failed. Please try again.'
      setError(msg)
      toast.error(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-cyan-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-cyan-700 shadow-lg mb-4">
            <span className="text-white text-2xl font-bold">ES</span>
          </div>
          <h1 className="text-3xl font-bold text-gray-900">EmploySmart</h1>
          <p className="text-gray-500 mt-1 text-sm">Staff Portal • Sign in for PESO, CLCDO, or admin access</p>
        </div>

        <div className="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
          {error && (
            <div className="alert-danger mb-4 text-sm">
              <span>⚠</span> {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="form-group">
              <label className="label">Email Address</label>
              <div className="relative">
                <FiMail className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
                <input type="email" className="input pl-10" placeholder="staff@example.com" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} required autoComplete="email" />
              </div>
            </div>
            <div className="form-group">
              <label className="label">Password</label>
              <div className="relative">
                <FiLock className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
                <input type={showPass ? 'text' : 'password'} className="input pl-10 pr-10" placeholder="Enter your password" value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} required autoComplete="current-password" />
                <button type="button" onClick={() => setShowPass((s) => !s)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  {showPass ? <FiEyeOff size={16} /> : <FiEye size={16} />}
                </button>
              </div>
            </div>
            <button type="submit" disabled={loading} className="btn-primary w-full btn-lg mt-2">
              {loading ? <><div className="spinner w-4 h-4" /> Signing in...</> : 'Sign In'}
            </button>
          </form>

          <p className="text-center text-sm text-gray-500 mt-5">
            User account? <Link to="/login/user" className="font-semibold text-emerald-700 hover:underline">Use user login</Link>
          </p>
        </div>
      </div>
    </div>
  )
}
