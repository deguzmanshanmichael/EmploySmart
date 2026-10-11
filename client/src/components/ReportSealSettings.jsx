import { useState } from 'react'
import toast from 'react-hot-toast'
import { FiUploadCloud, FiTrash2, FiPrinter } from 'react-icons/fi'
import { settingsService } from '../services/index'
import { absoluteUrl, placeholderSealSvg, printReport, REPORT_COLORS } from '../utils/printReport'
import { refreshMunicipality } from '../hooks/useMunicipality'

const SEALS = [
  { key: 'lgu', label: 'Municipality / LGU seal', hint: 'Left side of every printed report.', fallbackKey: 'landing_logo_image', color: REPORT_COLORS.blue },
  { key: 'peso', label: 'PESO seal', hint: 'Right side of PESO reports.', fallbackKey: 'landing_office_image', color: REPORT_COLORS.navy },
  { key: 'clcdo', label: 'CLCDO seal', hint: 'Right side of CLCDO training reports.', fallbackKey: 'landing_office_image', color: REPORT_COLORS.navy },
]

const SAMPLE = {
  peso: { office: 'peso', title: 'Letterhead Preview', subtitle: 'PESO report layout', stats: [{ label: 'Sample figure', value: 12, icon: 'users' }, { label: 'Sample figure', value: 8, icon: 'briefcase' }, { label: 'Sample figure', value: 5, icon: 'file' }, { label: 'Sample figure', value: 3, icon: 'check' }], insight: 'This page previews the seals, letterhead, colors and footer used on every printed PESO report.' },
  clcdo: { office: 'clcdo', title: 'Letterhead Preview', subtitle: 'CLCDO report layout', stats: [{ label: 'Sample figure', value: 10, icon: 'book' }, { label: 'Sample figure', value: 24, icon: 'users' }, { label: 'Sample figure', value: 18, icon: 'award' }, { label: 'Sample figure', value: '75%', icon: 'percent' }], insight: 'This page previews the seals, letterhead, colors and footer used on every printed CLCDO report.' },
  admin: { office: 'admin', title: 'Letterhead Preview', subtitle: 'System administrator report layout', insight: 'Administrator reports show the municipality seal with the EmploySmart mark.' },
}

function initials(text) {
  const words = String(text || '').replace(/\b(city|municipality|of|the|and)\b/gi, ' ').split(/\s+/).filter(Boolean)
  return (words.map((w) => w[0]).join('').slice(0, 3) || 'LGU').toUpperCase()
}

/** Admin controls for the seals printed on PESO, CLCDO and municipality reports. */
export default function ReportSealSettings({ form, setValue, onSave }) {
  const [busy, setBusy] = useState('')

  const imageOf = (seal) => form[`landing_seal_${seal.key}_image`] || form[seal.fallbackKey] || ''
  const placeholderLabel = (seal) => (seal.key === 'lgu' ? initials(form.municipality_name) : seal.key.toUpperCase())

  const upload = async (seal, event) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    if (file.size > 5 * 1024 * 1024) { toast.error('Seal images must be smaller than 5 MB.'); return }
    setBusy(seal.key)
    try {
      const res = await settingsService.uploadLandingImage(`seal_${seal.key}`, file)
      const field = `landing_seal_${seal.key}_image`
      setValue(field, res.data?.data?.[field] || '')
      refreshMunicipality()
      toast.success(`${seal.label} updated`)
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to upload the seal')
    } finally {
      setBusy('')
    }
  }

  const remove = async (seal) => {
    const field = `landing_seal_${seal.key}_image`
    setBusy(seal.key)
    try {
      // Removing also clears the older shared office/LGU logo fallback for this seal.
      const changes = { [field]: '' }
      if (seal.key === 'lgu' && !form[field]) changes.landing_logo_image = ''
      if (seal.key !== 'lgu' && !form[field]) changes.landing_office_image = ''
      await settingsService.updateMunicipality(changes)
      Object.entries(changes).forEach(([k, v]) => setValue(k, v))
      refreshMunicipality()
      toast.success(`${seal.label} removed — the placeholder seal will be printed`)
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to remove the seal')
    } finally {
      setBusy('')
    }
  }

  const preview = (office) => {
    // The window opens immediately; captions and URLs are saved before it renders.
    const opened = printReport(async () => {
      const saved = await onSave({ silent: true })
      if (saved === false) throw new Error('The settings could not be saved')
      return SAMPLE[office]
    })
    if (!opened) toast.error('Please allow pop-ups to preview the letterhead.')
  }

  return (
    <div className="card-flat space-y-4 max-w-3xl">
      <div>
        <h2 className="text-lg font-bold text-gray-900">Report Seals</h2>
        <p className="text-sm text-gray-500">Seals printed on the letterhead of every report. Use square PNG or WebP images with a transparent background (at least 300 × 300 px, maximum 5 MB).</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        {SEALS.map((seal) => {
          const image = imageOf(seal)
          const usingFallback = image && !form[`landing_seal_${seal.key}_image`]
          return (
            <div key={seal.key} className="rounded-xl border border-gray-100 p-4 space-y-3">
              <div>
                <p className="font-semibold text-gray-800">{seal.label}</p>
                <p className="text-xs text-gray-500">{seal.hint}</p>
              </div>
              <div className="flex flex-col items-center gap-1 rounded-lg bg-gray-50 p-3">
                {image
                  ? <img src={absoluteUrl(image)} alt={`${seal.label} preview`} className="h-24 w-24 object-contain" />
                  : <div className="h-24 w-24" dangerouslySetInnerHTML={{ __html: placeholderSealSvg(placeholderLabel(seal), seal.color) }} />}
                {form[`report_seal_${seal.key}_caption`] && <span className="text-[10px] font-bold uppercase tracking-wider text-gray-500 text-center">{form[`report_seal_${seal.key}_caption`]}</span>}
                <span className="text-[11px] text-gray-400">{image ? (usingFallback ? 'Using the shared logo' : 'Custom seal') : 'Placeholder (no image yet)'}</span>
              </div>
              <div className="flex flex-wrap gap-2">
                <label className={`btn-outline btn-sm cursor-pointer ${busy === seal.key ? 'pointer-events-none opacity-50' : ''}`}>
                  <FiUploadCloud /> {busy === seal.key ? 'Saving…' : image ? 'Replace' : 'Upload'}
                  <input type="file" accept="image/png,image/webp,image/jpeg" className="sr-only" onChange={(e) => upload(seal, e)} disabled={!!busy} />
                </label>
                {image && <button type="button" onClick={() => remove(seal)} disabled={!!busy} className="btn-secondary btn-sm"><FiTrash2 /> Remove</button>}
              </div>
              <div className="form-group">
                <label className="label text-xs" htmlFor={`seal-url-${seal.key}`}>Image URL (optional)</label>
                <input id={`seal-url-${seal.key}`} className="input text-xs" placeholder="https://…" value={form[`landing_seal_${seal.key}_image`] || ''} onChange={(e) => setValue(`landing_seal_${seal.key}_image`, e.target.value)} />
              </div>
              <div className="form-group">
                <label className="label text-xs" htmlFor={`seal-caption-${seal.key}`}>Caption under the seal (optional)</label>
                <input id={`seal-caption-${seal.key}`} className="input text-xs" maxLength={40} placeholder={seal.key === 'lgu' ? 'e.g. City of Gapan' : `e.g. ${seal.key.toUpperCase()} Gapan`} value={form[`report_seal_${seal.key}_caption`] || ''} onChange={(e) => setValue(`report_seal_${seal.key}_caption`, e.target.value)} />
              </div>
            </div>
          )
        })}
      </div>
      <div className="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
        <span className="text-sm text-gray-500">Preview printed letterhead:</span>
        <button type="button" onClick={() => preview('peso')} className="btn-secondary btn-sm"><FiPrinter /> PESO</button>
        <button type="button" onClick={() => preview('clcdo')} className="btn-secondary btn-sm"><FiPrinter /> CLCDO</button>
        <button type="button" onClick={() => preview('admin')} className="btn-secondary btn-sm"><FiPrinter /> Administrator</button>
        <span className="text-xs text-gray-400">Captions and URLs are saved before previewing.</span>
      </div>
    </div>
  )
}
