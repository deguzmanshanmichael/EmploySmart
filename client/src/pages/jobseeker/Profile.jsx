import { useState, useRef } from 'react'
import { useAuth } from '../../context/AuthContext'
import PasswordField from '../../components/PasswordField'
import { Link } from 'react-router-dom'
import { userService, resumeProfileService } from '../../services/index'
import toast from 'react-hot-toast'
import { FiSave, FiLock, FiUpload, FiUser, FiAward, FiUploadCloud } from 'react-icons/fi'
import { API_BASE_URL } from '../../config/api.js'

const BASE_URL = API_BASE_URL

export default function Profile() {
  const { user, updateUser } = useAuth()
  const [tab, setTab] = useState('personal')
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState({})
  const [otherDocuments, setOtherDocuments] = useState([])
  const otherDocsInputRef = useRef(null)
  const [form, setForm] = useState({
    first_name: user?.first_name || '',
    middle_name: user?.middle_name || '',
    last_name: user?.last_name || '',
    suffix: user?.suffix || '',
    sex: user?.sex || '',
    birth_date: user?.birth_date || '',
    phone: user?.phone || '',
    alternate_phone: user?.alternate_phone || '',
    address: user?.address || '',
    city: user?.city || '',
    province: user?.province || '',
    zip_code: user?.zip_code || '',
    civil_status: user?.civil_status || '',
    education_level: user?.education_level || '',
    employment_status: user?.employment_status || '',
    bio: user?.bio || '',
  })
  const [passForm, setPassForm] = useState({ current_password: '', new_password: '', confirm: '' })

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  const validateProfile = () => {
    const e = {}
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (!form.first_name.trim()) e.first_name = 'Required'
    if (!form.last_name.trim()) e.last_name = 'Required'
    if (form.birth_date) {
      const birth = new Date(form.birth_date)
      if (birth > today) e.birth_date = 'Birth date cannot be in the future'
    }
    if (form.phone && !/^\+?[\d\s\-\(\)]+$/.test(form.phone)) e.phone = 'Invalid phone number'
    if (form.alternate_phone && !/^\+?[\d\s\-\(\)]+$/.test(form.alternate_phone)) e.alternate_phone = 'Invalid phone number'
    if (form.zip_code && !/^\d{4,10}$/.test(form.zip_code)) e.zip_code = 'Invalid ZIP code'

    setErrors(e)
    return Object.keys(e).length === 0
  }

  const ic = (name) => `input ${errors[name] ? 'input-error' : ''}`
  const em = (name) => errors[name] ? <p className="text-xs text-red-500 mt-1">{errors[name]}</p> : null

  const handleSaveProfile = async () => {
    if (!validateProfile()) { toast.error('Please fix the highlighted fields.'); return }
    setSaving(true)
    try {
      await userService.update(user.id, form)
      updateUser(form)
      setErrors({})
      toast.success('Profile updated!')
    } catch (err) {
      if (err.response?.data?.errors) setErrors(err.response.data.errors)
      toast.error(err.response?.data?.message || 'Update failed')
    }
    setSaving(false)
  }

  const handleChangePassword = async () => {
    if (passForm.new_password !== passForm.confirm) { toast.error('Passwords do not match'); return }
    if (passForm.new_password.length < 6) { toast.error('Password too short'); return }
    setSaving(true)
    try {
      await userService.updatePassword(user.id, { current_password: passForm.current_password, new_password: passForm.new_password })
      toast.success('Password changed!')
      setPassForm({ current_password: '', new_password: '', confirm: '' })
    } catch (err) { toast.error(err.response?.data?.message || 'Failed') }
    setSaving(false)
  }

  const handleViewResume = async () => {
    try {
      const res = await resumeProfileService.downloadFile(user.id)
      const url = window.URL.createObjectURL(res.data)
      window.open(url, '_blank', 'noopener')
      setTimeout(() => window.URL.revokeObjectURL(url), 60000)
    } catch (err) {
      toast.error('Unable to open your resume')
    }
  }

  const handleOtherDocumentUpload = (e) => {
    const files = Array.from(e.target.files || [])
    if (!files.length) return

    const mapped = files.map((file) => ({
      id: `${file.name}-${file.size}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
      name: file.name,
      size: file.size,
      type: file.type || 'application/octet-stream',
    }))

    setOtherDocuments(prev => [...prev, ...mapped])
    toast.success(`${files.length} document${files.length > 1 ? 's' : ''} added.`)

    if (otherDocsInputRef.current) otherDocsInputRef.current.value = ''
  }

  const handleAvatarUpload = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    const fd = new FormData()
    fd.append('photo', file)
    try {
      const res = await userService.uploadAvatar(user.id, fd)
      updateUser({ profile_picture: res.data.data?.profile_picture })
      toast.success('Photo updated!')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Upload failed')
    }
  }

  const handleGenerateResume = async () => {
    try {
      const res = await userService.generateResume(user.id)
      const blob = new Blob([res.data], { type: 'application/pdf' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `employsmart_resume_${user.id}.pdf`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
      toast.success('Resume generated and downloaded!')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to generate resume')
    }
  }

  const TABS = [
    { key: 'personal', label: 'Personal Info', icon: <FiUser /> },
    { key: 'security', label: 'Security', icon: <FiLock /> },
    { key: 'documents', label: 'Documents', icon: <FiUpload /> },
  ]

  const SelectField = ({ label, name, options }) => (
    <div className="form-group">
      <label className="label">{label}</label>
      <select className="input" value={form[name]} onChange={e => set(name, e.target.value)}>
        <option value="">Select...</option>
        {options.map(o => <option key={o.val} value={o.val}>{o.label}</option>)}
      </select>
    </div>
  )

  return (
    <div className="animate-fade-in space-y-6 max-w-3xl">
      <div className="page-header">
        <h1 className="page-title">My Profile</h1>
        <p className="page-subtitle">Manage your personal information</p>
      </div>

      {/* Avatar */}
      <div className="card-flat flex items-center gap-5">
        <div className="relative">
          <div className="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center overflow-hidden">
            {user?.profile_picture
              ? <img src={`${BASE_URL}/${user.profile_picture}`} className="w-full h-full object-cover" alt="avatar" />
              : <span className="text-white text-2xl font-bold">{user?.first_name?.charAt(0)}</span>
            }
          </div>
        </div>
        <div>
          <p className="font-bold text-gray-900 text-lg">{user?.first_name} {user?.last_name}</p>
          <p className="text-sm text-gray-500">{user?.email}</p>
          <label className="btn-secondary btn-sm mt-2 cursor-pointer inline-flex items-center gap-1.5">
            <FiUpload size={13} /> Change Photo
            <input type="file" accept="image/*" className="hidden" onChange={handleAvatarUpload} />
          </label>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 overflow-x-auto scrollbar-hide bg-gray-100 p-1 rounded-xl w-fit">
        {TABS.map(t => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap transition-all ${tab === t.key ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-800'}`}>
            {t.icon} {t.label}
          </button>
        ))}
      </div>

      {tab === 'personal' && (
        <div className="card-flat space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {['first_name','middle_name','last_name'].map(f => (
              <div key={f} className="form-group">
                <label className="label capitalize">{f.replace('_',' ')}</label>
                <input className={ic(f)} value={form[f]} onChange={e => set(f, e.target.value)} />
                {em(f)}
              </div>
            ))}
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SelectField label="Sex" name="sex" options={[{val:'male',label:'Male'},{val:'female',label:'Female'},{val:'other',label:'Other'}]} />
            <div className="form-group">
              <label className="label">Birth Date</label>
              <input type="date" className={ic('birth_date')} value={form.birth_date} onChange={e => set('birth_date', e.target.value)} />
              {em('birth_date')}
            </div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {['phone','alternate_phone'].map(f => (
              <div key={f} className="form-group">
                <label className="label capitalize">{f.replace('_',' ')}</label>
                <input className={ic(f)} value={form[f]} onChange={e => set(f, e.target.value)} />
                {em(f)}
              </div>
            ))}
          </div>
          <div className="form-group">
            <label className="label">Address</label>
            <input className="input" value={form.address} onChange={e => set('address', e.target.value)} />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {['city','province','zip_code'].map(f => (
              <div key={f} className="form-group">
                <label className="label capitalize">{f.replace('_',' ')}</label>
                <input className={ic(f)} value={form[f]} onChange={e => set(f, e.target.value)} />
                {em(f)}
              </div>
            ))}
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SelectField label="Civil Status" name="civil_status" options={[{val:'single',label:'Single'},{val:'married',label:'Married'},{val:'widowed',label:'Widowed'},{val:'separated',label:'Separated'}]} />
            <SelectField label="Education Level" name="education_level" options={[
              {val:'no_formal_education',label:'No Formal Education'},{val:'elementary',label:'Elementary'},
              {val:'high_school',label:'High School'},{val:'senior_high',label:'Senior High'},
              {val:'vocational',label:'Vocational'},{val:'college',label:'College'},{val:'postgraduate',label:'Postgraduate'}
            ]} />
          </div>
          <SelectField label="Employment Status" name="employment_status" options={[
            {val:'unemployed',label:'Unemployed'},{val:'employed',label:'Employed'},
            {val:'self_employed',label:'Self-employed'},{val:'student',label:'Student'}
          ]} />
          <div className="form-group">
            <label className="label">Bio</label>
            <textarea className="input h-24 resize-none" value={form.bio} onChange={e => set('bio', e.target.value)} placeholder="Tell employers about yourself..." />
          </div>
          <button onClick={handleSaveProfile} disabled={saving} className="btn-primary">
            {saving ? <><div className="spinner w-4 h-4" /> Saving...</> : <><FiSave /> Save Changes</>}
          </button>
        </div>
      )}

      {tab === 'security' && (
        <div className="card-flat space-y-4 max-w-md">
          <h3 className="font-bold text-gray-800">Change Password</h3>
          {['current_password','new_password','confirm'].map(f => (
            <div key={f} className="form-group">
              <label className="label capitalize">{f.replace('_',' ')}</label>
              <PasswordField value={passForm[f]} onChange={e => setPassForm(p => ({ ...p, [f]: e.target.value }))} autoComplete="new-password" />
            </div>
          ))}
          <button onClick={handleChangePassword} disabled={saving} className="btn-primary">
            {saving ? <><div className="spinner w-4 h-4" />Updating...</> : <><FiLock /> Change Password</>}
          </button>
        </div>
      )}

      {tab === 'documents' && (
        <div className="card-flat space-y-5">
          <div className="space-y-3">
            <h3 className="font-bold text-gray-800 flex items-center gap-2">
              <FiAward /> Resume
            </h3>
            <p className="text-sm text-gray-600">
              Upload your resume on the My Resume page. EmploySmart reads it, lets you review what it found, updates your profile and skills, and recommends jobs based on it.
            </p>
            <div className="flex flex-wrap gap-2">
              <Link to="/jobseeker/resume" className="btn-outline inline-flex items-center gap-2">
                <FiUploadCloud /> {user?.resume_path ? 'Manage Resume & Recommendations' : 'Upload & Analyze Resume'}
              </Link>
              <button onClick={handleGenerateResume} className="btn-primary btn-sm inline-flex items-center gap-2">
                🧾 Generate Resume
              </button>
              {user?.resume_path && (
                <button type="button" onClick={handleViewResume} className="btn-success btn-sm inline-flex">
                  📄 View Current Resume
                </button>
              )}
            </div>
          </div>

          <div className="border-t border-gray-200 pt-4 space-y-3">
            <h3 className="font-bold text-gray-800">Other Documents</h3>
            <p className="text-sm text-gray-600">Upload supporting files such as certificates, diplomas, transcripts, or training records.</p>

            <button type="button" onClick={() => otherDocsInputRef.current?.click()} className="btn-outline inline-flex items-center gap-2">
              <FiUpload /> Upload Other Documents
            </button>
            <input ref={otherDocsInputRef} type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple className="hidden" onChange={handleOtherDocumentUpload} />

            {otherDocuments.length === 0 ? (
              <div className="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                No supporting documents uploaded yet.
              </div>
            ) : (
              <div className="space-y-2">
                {otherDocuments.map((doc) => (
                  <div key={doc.id} className="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                    <span className="truncate text-gray-700">{doc.name}</span>
                    <span className="text-xs text-gray-500">{(doc.size / 1024).toFixed(1)} KB</span>
                  </div>
                ))}
              </div>
            )}
          </div>

        </div>
      )}


    </div>
  )
}