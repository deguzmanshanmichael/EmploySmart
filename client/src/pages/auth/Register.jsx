import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { authService } from '../../services/authService'
import { Modal } from '../../components/index'
import toast from 'react-hot-toast'
import { FiEye, FiEyeOff } from 'react-icons/fi'

export default function Register() {
  const navigate  = useNavigate()
  const [loading, setLoading]   = useState(false)
  const [showPass, setShowPass] = useState(false)
  const [errors, setErrors]     = useState({})
  const [showTerms, setShowTerms] = useState(false)
  const [form, setForm] = useState({
    role: 'jobseeker', first_name: '', last_name: '', middle_name: '',
    sex: '', birth_date: '', email: '', password: '', confirm_password: '',
    phone: '', address: '', city: '', province: '', zip_code: '',
    education_level: '', company_name: '', industry: '', company_address: '',
    accepted_terms: false,
  })

  // useCallback keeps `set` referentially stable so no child re-mounts occur
  const set = useCallback((k, v) => {
    setForm(f => ({ ...f, [k]: v }))
  }, [])

  const validate = () => {
    const e = {}
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (!form.first_name.trim()) e.first_name = 'Required'
    if (!form.last_name.trim())  e.last_name  = 'Required'
    if (!form.sex)              e.sex        = 'Required'
    if (!form.email.trim())     e.email      = 'Required'
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = 'Invalid email'
    if (!form.password)         e.password   = 'Required'
    else if (form.password.length < 6) e.password = 'Min 6 characters'
    if (!form.confirm_password) e.confirm_password = 'Required'
    else if (form.password !== form.confirm_password) e.confirm_password = 'Passwords do not match'
    if (form.birth_date) {
      const birth = new Date(form.birth_date)
      if (birth > today) e.birth_date = 'Birth date cannot be in the future'
    }
    if (!form.accepted_terms)   e.accepted_terms = 'You must agree to the terms and conditions'
    if (form.role === 'employer' && !form.company_name.trim()) e.company_name = 'Required'
    if (form.phone && !/^\+?[\d\s\-\(\)]+$/.test(form.phone)) e.phone = 'Invalid phone number'
    if (form.zip_code && !/^\d{4,10}$/.test(form.zip_code)) e.zip_code = 'Invalid ZIP code'
    setErrors(e)
    return Object.keys(e).length === 0
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!validate()) return
    setLoading(true)
    try {
      await authService.register(form)
      toast.success('Registration submitted! Please wait for account verification.')
      navigate('/login')
    } catch (err) {
      const msg = err.response?.data?.message || 'Registration failed'
      toast.error(msg)
      if (err.response?.data?.errors) setErrors(err.response.data.errors)
    } finally {
      setLoading(false)
    }
  }

  // Helper: CSS class for an input based on error state
  const ic = (name) => `input ${errors[name] ? 'input-error' : ''}`

  // Helper: error message paragraph
  const em = (name) =>
    errors[name] ? <p className="text-xs text-red-500 mt-1">{errors[name]}</p> : null

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex items-center justify-center p-4">
      <div className="w-full max-w-2xl">

        {/* Header */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 shadow-lg mb-3">
            <span className="text-white text-xl font-bold font-display">ES</span>
          </div>
          <h1 className="text-2xl font-bold font-display text-gray-900">Create Your Account</h1>
          <p className="text-sm text-gray-500 mt-1">Join EmploySmart — Nueva Ecija's Job Portal</p>
        </div>

        <div className="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 sm:p-8">

          {/* Role selector */}
          <div className="mb-6">
            <label className="label">I am registering as:</label>
            <div className="grid grid-cols-2 gap-3">
              {[
                { val: 'jobseeker', label: '🧑‍💼 Job Seeker', desc: 'Find employment opportunities' },
                { val: 'employer',  label: '🏢 Employer',    desc: 'Post jobs and hire talent' },
              ].map(opt => (
                <button
                  key={opt.val}
                  type="button"
                  onClick={() => set('role', opt.val)}
                  className={`p-4 rounded-xl border-2 text-left transition-all ${
                    form.role === opt.val
                      ? 'border-blue-600 bg-blue-50'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  <div className="font-bold text-sm text-gray-800">{opt.label}</div>
                  <div className="text-xs text-gray-500 mt-0.5">{opt.desc}</div>
                </button>
              ))}
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4" noValidate>

            {/* Personal info */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div className="form-group">
                <label className="label">First Name <span className="text-red-500">*</span></label>
                <input type="text" className={ic('first_name')} placeholder="Juan"
                  value={form.first_name} onChange={e => set('first_name', e.target.value)}
                  autoComplete="given-name" />
                {em('first_name')}
              </div>
              <div className="form-group">
                <label className="label">Middle Name</label>
                <input type="text" className="input" placeholder="(optional)"
                  value={form.middle_name} onChange={e => set('middle_name', e.target.value)}
                  autoComplete="additional-name" />
              </div>
              <div className="form-group">
                <label className="label">Last Name <span className="text-red-500">*</span></label>
                <input type="text" className={ic('last_name')} placeholder="Dela Cruz"
                  value={form.last_name} onChange={e => set('last_name', e.target.value)}
                  autoComplete="family-name" />
                {em('last_name')}
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="form-group">
                <label className="label">Sex <span className="text-red-500">*</span></label>
                <select className={ic('sex')} value={form.sex} onChange={e => set('sex', e.target.value)}>
                  <option value="">Select sex</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
                {em('sex')}
              </div>
              <div className="form-group">
                <label className="label">Birth Date</label>
                <input type="date" className={ic('birth_date')}
                  value={form.birth_date} onChange={e => set('birth_date', e.target.value)}
                  autoComplete="bday" />
                {em('birth_date')}
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="form-group">
                <label className="label">Phone Number</label>
                <input type="tel" className="input" placeholder="09XXXXXXXXX"
                  value={form.phone} onChange={e => set('phone', e.target.value)}
                  autoComplete="tel" inputMode="tel" />
              </div>
              <div className="form-group">
                <label className="label">Education Level</label>
                <select className="input" value={form.education_level} onChange={e => set('education_level', e.target.value)}>
                  <option value="">Select education</option>
                  <option value="no_formal_education">No Formal Education</option>
                  <option value="elementary">Elementary</option>
                  <option value="high_school">High School</option>
                  <option value="senior_high">Senior High School</option>
                  <option value="vocational">Vocational / TESDA</option>
                  <option value="college">College</option>
                  <option value="postgraduate">Post-graduate</option>
                </select>
              </div>
            </div>

            <div className="form-group">
              <label className="label">Address</label>
              <input type="text" className="input" placeholder="Street / Barangay"
                value={form.address} onChange={e => set('address', e.target.value)}
                autoComplete="street-address" />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div className="form-group">
                <label className="label">City</label>
                <input type="text" className="input" placeholder="Cabanatuan City"
                  value={form.city} onChange={e => set('city', e.target.value)}
                  autoComplete="address-level2" />
              </div>
              <div className="form-group">
                <label className="label">Province</label>
                <input type="text" className="input" placeholder="Nueva Ecija"
                  value={form.province} onChange={e => set('province', e.target.value)}
                  autoComplete="address-level1" />
              </div>
              <div className="form-group">
                <label className="label">ZIP Code</label>
                <input type="text" className="input" placeholder="3100"
                  value={form.zip_code} onChange={e => set('zip_code', e.target.value)}
                  autoComplete="postal-code" inputMode="numeric" />
              </div>
            </div>

            {/* Employer-only fields */}
            {form.role === 'employer' && (
              <div className="border-t border-gray-100 pt-4 space-y-4">
                <p className="text-sm font-bold text-gray-700">Company Information</p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="form-group">
                    <label className="label">Company Name <span className="text-red-500">*</span></label>
                    <input type="text" className={ic('company_name')} placeholder="Your Company Inc."
                      value={form.company_name} onChange={e => set('company_name', e.target.value)}
                      autoComplete="organization" />
                    {em('company_name')}
                  </div>
                  <div className="form-group">
                    <label className="label">Industry</label>
                    <input type="text" className="input" placeholder="e.g. Retail, BPO, Construction"
                      value={form.industry} onChange={e => set('industry', e.target.value)} />
                  </div>
                </div>
                <div className="form-group">
                  <label className="label">Company Address</label>
                  <input type="text" className="input" placeholder="Complete company address"
                    value={form.company_address} onChange={e => set('company_address', e.target.value)}
                    autoComplete="street-address" />
                </div>
              </div>
            )}

            {/* Login credentials */}
            <div className="border-t border-gray-100 pt-4 space-y-4">
              <p className="text-sm font-bold text-gray-700">Login Credentials</p>

              <div className="form-group">
                <label className="label">Email Address <span className="text-red-500">*</span></label>
                <input type="email" className={ic('email')} placeholder="you@email.com"
                  value={form.email} onChange={e => set('email', e.target.value)}
                  autoComplete="email" inputMode="email" />
                {em('email')}
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="form-group">
                  <label className="label">Password <span className="text-red-500">*</span></label>
                  <div className="relative">
                    <input
                      type={showPass ? 'text' : 'password'}
                      className={`input pr-10 ${errors.password ? 'input-error' : ''}`}
                      placeholder="Min. 6 characters"
                      value={form.password}
                      onChange={e => set('password', e.target.value)}
                      autoComplete="new-password"
                    />
                    <button
                      type="button"
                      tabIndex={-1}
                      onClick={() => setShowPass(s => !s)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                    >
                      {showPass ? <FiEyeOff size={16} /> : <FiEye size={16} />}
                    </button>
                  </div>
                  {em('password')}
                </div>

                <div className="form-group">
                  <label className="label">Confirm Password <span className="text-red-500">*</span></label>
                  <input
                    type="password"
                    className={ic('confirm_password')}
                    placeholder="Repeat password"
                    value={form.confirm_password}
                    onChange={e => set('confirm_password', e.target.value)}
                    autoComplete="new-password"
                  />
                  {em('confirm_password')}
                </div>
              </div>
            </div>

            <div className="alert-info text-xs mt-2">
              ℹ️ Your account will be reviewed before you can log in. Job seekers are verified by the PESO office.
            </div>

            <div className="form-group mt-4">
              <div className="flex items-start gap-3">
                <input
                  id="accepted_terms"
                  type="checkbox"
                  checked={form.accepted_terms}
                  onChange={e => set('accepted_terms', e.target.checked)}
                  className="checkbox checkbox-primary mt-1"
                />
                <label htmlFor="accepted_terms" className="text-sm text-gray-700">
                  I agree to EmploySmart's{' '}
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); setShowTerms(true) }}
                    className="text-blue-700 underline hover:text-blue-600"
                  >
                    Terms and Conditions
                  </button>
                  .
                </label>
              </div>
              {em('accepted_terms')}
            </div>

            {showTerms && (
              <Modal title="Terms and Conditions" onClose={() => setShowTerms(false)} size="lg">
                <div className="space-y-4 text-sm text-gray-700">
                  <p>By using EmploySmart, you agree to the terms below. EmploySmart is a job portal for job seekers and employers in Nueva Ecija, and these terms govern account registration, service use, and communications.</p>
                  <div>
                    <p className="font-semibold">Account Registration</p>
                    <p>Users must provide accurate information and may be required to verify their identity before the account becomes active. EmploySmart reserves the right to approve, suspend, or decline any registration.</p>
                  </div>
                  <div>
                    <p className="font-semibold">Use of the Service</p>
                    <p>EmploySmart connects job seekers with employers and facilitates hiring opportunities. The platform does not guarantee job placement, and users are responsible for how they use the service.</p>
                  </div>
                  <div>
                    <p className="font-semibold">Data and Privacy</p>
                    <p>EmploySmart may collect and use information submitted during registration and while using the platform to provide and improve services. Personal information will be handled in accordance with our privacy practices.</p>
                  </div>
                  <div>
                    <p className="font-semibold">Communication</p>
                    <p>By registering, you consent to receiving important service messages, verification notices, and system-related updates from EmploySmart.</p>
                  </div>
                  <div>
                    <p className="font-semibold">Acceptable Use</p>
                    <p>Users must not post false, misleading, or inappropriate content and must comply with applicable laws and platform guidelines.</p>
                  </div>
                  <div className="text-right">
                    <button
                      type="button"
                      onClick={() => setShowTerms(false)}
                      className="btn-primary btn-sm"
                    >
                      Close
                    </button>
                  </div>
                </div>
              </Modal>
            )}

            <button type="submit" disabled={loading} className="btn-primary w-full btn-lg mt-2">
              {loading
                ? <><div className="spinner w-4 h-4" /> Submitting...</>
                : 'Create Account'}
            </button>
          </form>

          <p className="text-center text-sm text-gray-500 mt-5">
            Already have an account?{' '}
            <Link to="/login" className="text-blue-700 font-semibold hover:underline">Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  )
}