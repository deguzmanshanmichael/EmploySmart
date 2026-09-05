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
  landing_hero_title: '',
  landing_hero_subtitle: '',
  landing_about: '',
  landing_mission: '',
  landing_vision: '',
  landing_peso: '',
  landing_clcdo: '',
  landing_hero_image: '',
  landing_logo_image: '',
  landing_primary_color: '#047857',
  landing_accent_color: '#f59e0b',
  landing_footer_text: '',
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
      window.dispatchEvent(new Event('employsmart-theme-updated'))
      toast.success('Municipality settings updated')
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to save settings')
    } finally {
      setSaving(false)
    }
  }

  const handleReset = async () => {
    if (!window.confirm('Restore municipality, landing page, and theme settings to their defaults?')) return
    setSaving(true)
    try {
      const res = await settingsService.resetMunicipality()
      setForm({ ...initialState, ...(res.data?.data || {}) })
      window.dispatchEvent(new Event('employsmart-theme-updated'))
      toast.success('Settings restored to defaults')
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to restore defaults')
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
      </div>

      <div className="card-flat space-y-4 max-w-3xl">
        <div>
          <h2 className="text-lg font-bold text-gray-900">Public Landing Page</h2>
          <p className="mt-1 text-sm text-gray-500">These fields appear on the public home page and can be updated without rebuilding the frontend.</p>
        </div>
        <div className="form-group">
          <label className="label">Hero Title</label>
          <input className="input" value={form.landing_hero_title} onChange={(e) => setValue('landing_hero_title', e.target.value)} />
        </div>
        <div className="form-group">
          <label className="label">Hero Subtitle</label>
          <textarea className="input h-24 resize-none" value={form.landing_hero_subtitle} onChange={(e) => setValue('landing_hero_subtitle', e.target.value)} />
        </div>
        <div className="form-group">
          <label className="label">About EmploySmart</label>
          <textarea className="input h-28 resize-none" value={form.landing_about} onChange={(e) => setValue('landing_about', e.target.value)} />
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <div className="form-group">
            <label className="label">Mission</label>
            <textarea className="input h-32 resize-none" value={form.landing_mission} onChange={(e) => setValue('landing_mission', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">Vision</label>
            <textarea className="input h-32 resize-none" value={form.landing_vision} onChange={(e) => setValue('landing_vision', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">PESO Description</label>
            <textarea className="input h-32 resize-none" value={form.landing_peso} onChange={(e) => setValue('landing_peso', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="label">CLCDO Description</label>
            <textarea className="input h-32 resize-none" value={form.landing_clcdo} onChange={(e) => setValue('landing_clcdo', e.target.value)} />
          </div>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <div className="form-group"><label className="label">Hero Image URL</label><input className="input" placeholder="https://..." value={form.landing_hero_image} onChange={(e) => setValue('landing_hero_image', e.target.value)} /></div>
          <div className="form-group"><label className="label">Logo Image URL</label><input className="input" placeholder="https://..." value={form.landing_logo_image} onChange={(e) => setValue('landing_logo_image', e.target.value)} /></div>
          <div className="form-group"><label className="label">Primary Theme Color</label><input type="color" className="h-11 w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-1" value={form.landing_primary_color} onChange={(e) => setValue('landing_primary_color', e.target.value)} /></div>
          <div className="form-group"><label className="label">Accent Theme Color</label><input type="color" className="h-11 w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-1" value={form.landing_accent_color} onChange={(e) => setValue('landing_accent_color', e.target.value)} /></div>
        </div>
        <div className="form-group"><label className="label">Footer Text</label><input className="input" value={form.landing_footer_text} onChange={(e) => setValue('landing_footer_text', e.target.value)} /></div>
        <div className="flex flex-wrap gap-3">
          <button className="btn-primary" onClick={handleSave} disabled={saving}>{saving ? 'Saving...' : 'Save Configuration'}</button>
          <button className="btn-secondary" onClick={handleReset} disabled={saving}>Back to Defaults</button>
        </div>
      </div>
    </div>
  )
}
