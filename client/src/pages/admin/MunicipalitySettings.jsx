import { useEffect, useState } from 'react'
import { settingsService } from '../../services/index'
import toast from 'react-hot-toast'

const initialState = {
  municipality_name: '',
  municipality_code: '',
  municipality_region: '',
  contact_office: '',
  contact_email: '',
  contact_phone: '',
  service_scope: '',
  welcome_message: '',
}

export default function MunicipalitySettings() {
  const [form, setForm] = useState(initialState)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    const load = async () => {
      try {
        const res = await settingsService.getMunicipality()
        setForm({ ...initialState, ...(res.data?.data || {}) })
      } catch (error) {
        console.error(error)
      }
    }

    load()
  }, [])

  const setValue = (key, value) => setForm((prev) => ({ ...prev, [key]: value }))

  const handleSave = async () => {
    setSaving(true)
    try {
      await settingsService.updateMunicipality(form)
      toast.success('Municipality settings updated')
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to save settings')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="page-header">
        <h1 className="page-title">Municipality Configuration</h1>
        <p className="page-subtitle">Configure public-facing service details for municipal deployment.</p>
      </div>

      <div className="card-flat space-y-4 max-w-3xl">
        <div className="grid md:grid-cols-2 gap-4">
          <div className="form-group">
            <label className="label">Municipality Name</label>
            <input className="input" value={form.municipality_name} onChange={(e) => setValue('municipality_name', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">Municipality Code</label>
            <input className="input" value={form.municipality_code} onChange={(e) => setValue('municipality_code', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">Region</label>
            <input className="input" value={form.municipality_region} onChange={(e) => setValue('municipality_region', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">Contact Office</label>
            <input className="input" value={form.contact_office} onChange={(e) => setValue('contact_office', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">Email</label>
            <input className="input" value={form.contact_email} onChange={(e) => setValue('contact_email', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">Phone</label>
            <input className="input" value={form.contact_phone} onChange={(e) => setValue('contact_phone', e.target.value)} />
          </div>
        </div>
        <div className="form-group">
          <label className="label">Service Scope</label>
          <textarea className="input h-24 resize-none" value={form.service_scope} onChange={(e) => setValue('service_scope', e.target.value)} />
        </div>
        <div className="form-group">
          <label className="label">Welcome Message</label>
          <textarea className="input h-24 resize-none" value={form.welcome_message} onChange={(e) => setValue('welcome_message', e.target.value)} />
        </div>
        <button className="btn-primary" onClick={handleSave} disabled={saving}>
          {saving ? 'Saving...' : 'Save Configuration'}
        </button>
      </div>
    </div>
  )
}
