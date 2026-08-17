// CompanyProfile.jsx
import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import { employerService } from '../../services/index'
import { LoadingSpinner } from '../../components/index'
import toast from 'react-hot-toast'
import { FiSave, FiUpload } from 'react-icons/fi'
import { API_BASE_URL } from '../../config/api.js'

const BASE_URL = API_BASE_URL

export default function CompanyProfile() {
  const { user } = useAuth()
  const [employer, setEmployer] = useState(null)
  const [loading, setLoading]   = useState(true)
  const [saving, setSaving]     = useState(false)
  const [form, setForm] = useState({
    company_name:'', industry:'', company_size:'', website:'',
    company_address:'', contact_person:'', contact_email:'', contact_phone:'',
  })

  useEffect(() => {
    const load = async () => {
      try {
        const res = await employerService.getByUser(user.id)
        const emp = res.data.data
        setEmployer(emp)
        if (emp) setForm({
          company_name:    emp.company_name    || '',
          industry:        emp.industry        || '',
          company_size:    emp.company_size    || '',
          website:         emp.website         || '',
          company_address: emp.company_address || '',
          contact_person:  emp.contact_person  || '',
          contact_email:   emp.contact_email   || '',
          contact_phone:   emp.contact_phone   || '',
        })
      } catch {}
      setLoading(false)
    }
    load()
  }, [user.id])

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  const handleSave = async () => {
    setSaving(true)
    try {
      await employerService.update(employer.id, form)
      toast.success('Company profile updated!')
    } catch { toast.error('Update failed') }
    setSaving(false)
  }

  const handleDocument = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    const fd = new FormData()
    fd.append('document', file)
    try {
      await employerService.uploadDocument(employer.id, fd)
      toast.success('Business permit uploaded!')
    } catch { toast.error('Upload failed') }
  }

  if (loading) return <LoadingSpinner />

  const normalizeVerificationStatus = status => status === 'rejected' ? 'declined' : status
  const statusBadge = {
    pending:  'badge-yellow',
    approved: 'badge-green',
    declined: 'badge-red',
    rejected: 'badge-red',
  }

  return (
    <div className="animate-fade-in space-y-6 max-w-3xl">
      <div className="flex items-center justify-between">
        <div className="page-header mb-0">
          <h1 className="page-title">Company Profile 🏢</h1>
        </div>
        {employer?.verification_status && (
          <span className={statusBadge[normalizeVerificationStatus(employer.verification_status)]}>
            {normalizeVerificationStatus(employer.verification_status)}
          </span>
        )}
      </div>

      <div className="card-flat space-y-4">
        <h3 className="font-bold text-gray-800">Basic Information</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {[
            { label:'Company Name',     key:'company_name' },
            { label:'Industry',         key:'industry' },
            { label:'Contact Person',   key:'contact_person' },
            { label:'Contact Email',    key:'contact_email' },
            { label:'Contact Phone',    key:'contact_phone' },
            { label:'Website',          key:'website' },
          ].map(({ label, key }) => (
            <div key={key} className="form-group">
              <label className="label">{label}</label>
              <input className="input" value={form[key]} onChange={e => set(key, e.target.value)} />
            </div>
          ))}
        </div>
        <div className="form-group">
          <label className="label">Company Size</label>
          <select className="input" value={form.company_size} onChange={e => set('company_size', e.target.value)}>
            <option value="">Select size</option>
            <option value="small">Small (1–50)</option>
            <option value="medium">Medium (51–200)</option>
            <option value="large">Large (200+)</option>
          </select>
        </div>
        <div className="form-group">
          <label className="label">Company Address</label>
          <textarea className="input h-20 resize-none" value={form.company_address} onChange={e => set('company_address', e.target.value)} />
        </div>
        <button onClick={handleSave} disabled={saving} className="btn-primary">
          {saving ? <><div className="spinner w-4 h-4" /> Saving...</> : <><FiSave /> Save Profile</>}
        </button>
      </div>

      <div className="card-flat">
        <h3 className="font-bold text-gray-800 mb-3">Business Permit / Verification Document</h3>
        {employer?.business_permit && (
          <a href={`${BASE_URL}/${employer.business_permit}`} target="_blank" rel="noopener noreferrer" className="btn-success btn-sm inline-flex mb-3">
            📄 View Current Document
          </a>
        )}
        <label className="btn-outline cursor-pointer inline-flex items-center gap-2">
          <FiUpload /> Upload Business Permit (PDF/JPG)
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="hidden" onChange={handleDocument} />
        </label>
        <p className="text-xs text-gray-400 mt-2">Uploading will trigger re-verification by PESO</p>
      </div>
    </div>
  )
}