import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import toast from 'react-hot-toast'
import {
  FiUploadCloud, FiFileText, FiTrash2, FiEye, FiRefreshCw, FiCheckCircle, FiAlertTriangle,
  FiPlus, FiX, FiUser, FiBook, FiBriefcase, FiAward, FiInfo, FiCpu,
} from 'react-icons/fi'
import { useAuth } from '../../context/AuthContext'
import { resumeProfileService } from '../../services/index'
import { LoadingSpinner, Modal } from '../../components/index'
import ResumeRecommendations from '../../components/ResumeRecommendations'

const EDUCATION_LEVELS = [
  ['no_formal_education', 'No Formal Education'], ['elementary', 'Elementary'], ['high_school', 'High School'],
  ['senior_high', 'Senior High'], ['vocational', 'Vocational'], ['college', 'College'], ['postgraduate', 'Postgraduate'],
]
const SELECT_OPTIONS = {
  sex: [['male', 'Male'], ['female', 'Female'], ['other', 'Other']],
  civil_status: [['single', 'Single'], ['married', 'Married'], ['widowed', 'Widowed'], ['separated', 'Separated']],
  education_level: EDUCATION_LEVELS,
  employment_status: [['unemployed', 'Unemployed'], ['employed', 'Employed'], ['self_employed', 'Self-employed'], ['student', 'Student']],
}
const PROFILE_FIELDS = [
  ['first_name', 'First name', 'first_name'], ['middle_name', 'Middle name', 'middle_name'], ['last_name', 'Last name', 'last_name'],
  ['phone', 'Phone', 'phone'], ['alternate_phone', 'Alternate phone', 'alternate_phone'], ['address', 'Address', 'location'],
  ['city', 'City / Municipality', 'city'], ['province', 'Province', 'province'], ['zip_code', 'ZIP code', 'zip_code'],
  ['birth_date', 'Birth date', 'birth_date'], ['sex', 'Sex', 'sex'], ['civil_status', 'Civil status', 'civil_status'],
]
const CONFIDENCE_STYLE = { high: 'badge-green', medium: 'badge-yellow', low: 'badge-gray' }
const CONFIDENCE_LABEL = { high: 'Clearly stated', medium: 'Please verify', low: 'Uncertain' }
const STATUS_INFO = {
  needs_ocr: ['badge-yellow', 'Text recognition needed'],
  parsed: ['badge-blue', 'Waiting for your review'],
  confirmed: ['badge-green', 'Confirmed'],
}

const norm = (v) => String(v ?? '').trim().toLowerCase().replace(/\s+/g, ' ')
const formatBytes = (n) => (n > 1024 * 1024 ? `${(n / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`)
const listToText = (items) => (items || []).map((i) => (typeof i === 'string' ? i : i?.name)).filter(Boolean)

function buildReview(payload) {
  const ex = payload.extracted || {}
  const confirmed = payload.confirmed
  const cur = payload.current_profile || {}
  const p = ex.personal || {}

  const sources = {
    ...Object.fromEntries(Object.entries(p).map(([k, v]) => [k, v])),
    education_level: ex.highest_education_level,
    employment_status: ex.employment_status_hint,
    bio: ex.objective,
  }
  const fieldDefs = [...PROFILE_FIELDS, ['education_level', 'Highest education completed', 'education_level'], ['employment_status', 'Employment status', 'employment_status'], ['bio', 'Bio / career objective', 'bio']]
  const fields = fieldDefs.map(([key, label, sourceKey]) => {
    const src = sources[sourceKey]
    const resume = src?.value ?? ''
    const current = cur[key] ?? ''
    const same = resume !== '' && norm(resume) === norm(current)
    const conflict = resume !== '' && current !== '' && !same
    const locked = ['first_name', 'middle_name', 'last_name'].includes(key) && cur.is_verified && current !== ''
    return {
      key, label, resume: String(resume), current: String(current), confidence: src?.confidence, evidence: src?.evidence,
      same, conflict, locked,
      apply: resume !== '' && !same && !conflict && !locked && src?.confidence !== 'low',
    }
  }).filter((f) => f.resume !== '' || f.current !== '')

  const base = confirmed || {}
  const profileSkillNames = new Set((payload.current_skills || []).map((s) => norm(s.skill_name)))
  const extractedSkills = (ex.skills || []).map((s) => ({
    name: s.name, category: s.category, confidence: s.confidence, sources: s.sources || [], evidence: s.evidence,
    catalog: s.catalog_skill_name, selected: s.confidence !== 'low',
  }))
  let skills = extractedSkills
  if (confirmed?.skills) {
    const chosen = new Set(confirmed.skills.map((s) => norm(s.name)))
    const known = new Set(extractedSkills.map((s) => norm(s.name)))
    skills = [
      ...extractedSkills.map((s) => ({ ...s, selected: chosen.has(norm(s.name)) })),
      ...confirmed.skills.filter((s) => !known.has(norm(s.name))).map((s) => ({ name: s.name, category: s.category, confidence: 'high', sources: ['added_by_you'], selected: true })),
    ]
  }
  skills = skills.map((s) => ({ ...s, inProfile: profileSkillNames.has(norm(s.catalog || s.name)) }))

  const education = (base.education || ex.education || []).map((e) => ({
    degree: e.degree || '', field_of_study: e.field_of_study || '', institution: e.institution || '',
    start_date: e.start_date || '', end_date: e.end_date || '', status: e.status || '', level: e.level || '', honors: e.honors || '', confidence: e.confidence,
  }))
  const experience = (base.experience || ex.experience || []).map((e) => ({
    title: e.title || '', company: e.company || '', location: e.location || '', start_date: e.start_date || '', end_date: e.end_date || '',
    is_current: !!e.is_current, responsibilities: (e.responsibilities || []).join('\n'), confidence: e.confidence, warnings: e.warnings || [],
  }))
  return {
    fields,
    education,
    experience,
    skills,
    certifications: listToText(base.certifications || ex.certifications),
    trainings: listToText(base.trainings || ex.trainings),
    awards: listToText(base.awards || ex.awards),
    projects: listToText(base.projects || ex.projects),
    volunteer: listToText(base.volunteer || ex.volunteer),
    languages: (base.languages || ex.languages || []).map((l) => (l.proficiency ? `${l.name} (${l.proficiency})` : l.name)),
    email: p.email?.value || '',
    fullName: p.full_name?.value || '',
    warnings: ex.warnings || [],
    certTypes: Object.fromEntries((ex.certifications || []).map((c) => [c.name, c])),
  }
}

function SectionCard({ icon, title, subtitle, children, action }) {
  return (
    <section className="card-flat space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="flex items-center gap-2 font-bold text-gray-900">{icon} {title}</h3>
          {subtitle && <p className="mt-0.5 text-sm text-gray-500">{subtitle}</p>}
        </div>
        {action}
      </div>
      {children}
    </section>
  )
}

function StringListEditor({ items, onChange, placeholder }) {
  const [draft, setDraft] = useState('')
  const add = () => {
    const v = draft.trim()
    if (!v) return
    if (!items.some((i) => norm(i) === norm(v))) onChange([...items, v])
    setDraft('')
  }
  return (
    <div className="space-y-2">
      {items.length === 0 && <p className="text-sm text-gray-400">None found in your resume.</p>}
      {items.map((item, i) => (
        <div key={`${item}-${i}`} className="flex items-center gap-2">
          <input className="input" value={item} onChange={(e) => onChange(items.map((x, j) => (j === i ? e.target.value : x)))} aria-label={placeholder} />
          <button type="button" onClick={() => onChange(items.filter((_, j) => j !== i))} className="btn-secondary btn-sm" aria-label="Remove"><FiX /></button>
        </div>
      ))}
      <div className="flex gap-2">
        <input className="input" value={draft} placeholder={placeholder} onChange={(e) => setDraft(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), add())} />
        <button type="button" onClick={add} className="btn-outline btn-sm"><FiPlus /> Add</button>
      </div>
    </div>
  )
}

function FieldInput({ field, onChange }) {
  const options = SELECT_OPTIONS[field.key]
  if (options) {
    return (
      <select className="input" value={field.resume} onChange={(e) => onChange(e.target.value)} aria-label={`${field.label} from resume`}>
        <option value="">—</option>
        {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
      </select>
    )
  }
  if (field.key === 'bio') return <textarea className="input min-h-[90px]" value={field.resume} onChange={(e) => onChange(e.target.value)} aria-label="Bio from resume" />
  return <input className="input" type={field.key === 'birth_date' ? 'date' : 'text'} value={field.resume} onChange={(e) => onChange(e.target.value)} aria-label={`${field.label} from resume`} />
}

function displayValue(key, value) {
  if (!value) return <span className="italic text-gray-400">Empty</span>
  const opt = SELECT_OPTIONS[key]?.find(([v]) => v === value)
  return opt ? opt[1] : value
}

export default function ResumeCenter() {
  const { user, updateUser } = useAuth()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [upload, setUpload] = useState({ busy: false, progress: 0, stage: '' })
  const [ocr, setOcr] = useState({ busy: false, stage: '', progress: 0 })
  const [review, setReview] = useState(null)
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [removeSkills, setRemoveSkills] = useState(false)
  const [dragOver, setDragOver] = useState(false)
  const [newSkill, setNewSkill] = useState('')
  const [recsKey, setRecsKey] = useState(0)
  const fileRef = useRef(null)
  const lastFile = useRef(null)

  const applyPayload = useCallback((payload) => {
    setData(payload)
    if (payload?.extracted && payload.resume?.status !== 'needs_ocr') {
      setReview(buildReview(payload))
    } else {
      setReview(null)
    }
    setEditing(payload?.resume?.status === 'parsed')
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setLoadError(null)
    try {
      const res = await resumeProfileService.get()
      applyPayload(res.data.data)
    } catch (err) {
      setLoadError(err.response?.data?.message || err.message || 'Unable to load your resume.')
    } finally {
      setLoading(false)
    }
  }, [applyPayload])

  useEffect(() => { load() }, [load])

  const limits = data?.limits || { max_size_mb: 5, max_pages: 10, types: ['pdf', 'docx'] }

  const handleFile = async (file) => {
    if (!file) return
    const ext = file.name.split('.').pop().toLowerCase()
    if (!limits.types.includes(ext)) { toast.error('Please upload a PDF (recommended) or DOCX file.'); return }
    if (file.size === 0) { toast.error('This file is empty.'); return }
    if (file.size > limits.max_size_mb * 1024 * 1024) { toast.error(`The file is larger than ${limits.max_size_mb} MB.`); return }
    lastFile.current = file
    setUpload({ busy: true, progress: 0, stage: 'Uploading' })
    try {
      const res = await resumeProfileService.upload(file, (e) => {
        const pct = e.total ? Math.round((e.loaded / e.total) * 100) : 0
        setUpload({ busy: true, progress: pct, stage: pct >= 100 ? 'Reading your resume' : 'Uploading' })
      })
      applyPayload(res.data.data)
      updateUser({ resume_path: res.data.data.resume_path })
      setRecsKey((k) => k + 1)
      toast.success(res.data.message)
    } catch (err) {
      toast.error(err.response?.data?.message || err.message || 'Upload failed. Please try again.', { duration: 6000 })
    } finally {
      setUpload({ busy: false, progress: 0, stage: '' })
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  const runOcr = async () => {
    setOcr({ busy: true, stage: 'Preparing', progress: 0 })
    try {
      let source = lastFile.current && lastFile.current.name.toLowerCase().endsWith('.pdf') ? lastFile.current : null
      if (!source) source = (await resumeProfileService.downloadFile(user.id)).data
      const { recognizeResumePdf } = await import('../../utils/resumeOcr')
      const result = await recognizeResumePdf(source, {
        maxPages: limits.max_pages,
        onProgress: ({ stage, value }) => setOcr({ busy: true, stage, progress: value }),
      })
      setOcr({ busy: true, stage: 'Analyzing text', progress: 100 })
      const res = await resumeProfileService.submitOcrText(result.text)
      applyPayload(res.data.data)
      toast.success(res.data.message)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Text recognition failed. Please upload a clearer scan or a text-based PDF.', { duration: 6000 })
    } finally {
      setOcr({ busy: false, stage: '', progress: 0 })
    }
  }

  const viewResume = async () => {
    try {
      const res = await resumeProfileService.downloadFile(user.id)
      const url = URL.createObjectURL(res.data)
      window.open(url, '_blank', 'noopener')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } catch (err) {
      toast.error('Unable to open your resume.')
    }
  }

  const deleteResume = async () => {
    try {
      const res = await resumeProfileService.remove(removeSkills)
      applyPayload(res.data.data)
      setDeleteOpen(false)
      setRemoveSkills(false)
      setRecsKey((k) => k + 1)
      updateUser({ resume_path: null })
      toast.success(res.data.message)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to delete your resume.')
    }
  }

  const setField = (key, patch) => setReview((r) => ({ ...r, fields: r.fields.map((f) => (f.key === key ? { ...f, ...patch } : f)) }))
  const setList = (key, value) => setReview((r) => ({ ...r, [key]: value }))
  const updateEntry = (key, i, patch) => setReview((r) => ({ ...r, [key]: r[key].map((e, j) => (j === i ? { ...e, ...patch } : e)) }))

  const selectedSkills = useMemo(() => (review?.skills || []).filter((s) => s.selected), [review])

  const addSkill = () => {
    const v = newSkill.trim()
    if (!v || v.length > 60) return
    if (review.skills.some((s) => norm(s.name) === norm(v))) {
      setReview((r) => ({ ...r, skills: r.skills.map((s) => (norm(s.name) === norm(v) ? { ...s, selected: true } : s)) }))
    } else {
      setReview((r) => ({ ...r, skills: [...r.skills, { name: v, confidence: 'high', sources: ['added_by_you'], selected: true }] }))
    }
    setNewSkill('')
  }

  const confirm = async () => {
    const invalidDates = [...review.education, ...review.experience].some((e) => e.start_date && e.end_date && e.end_date < e.start_date)
    if (invalidDates) { toast.error('Some entries have an end date earlier than the start date. Please fix them first.'); return }
    const field = (k) => review.fields.find((f) => f.key === k)
    const profileUpdates = Object.fromEntries(review.fields.filter((f) => f.apply && f.resume.trim() !== '').map((f) => [f.key, f.resume.trim()]))
    const certTypes = review.certTypes || {}
    const body = {
      profile_updates: profileUpdates,
      resume: {
        full_name: review.fullName,
        email: review.email,
        phone: field('phone')?.resume || null,
        location: field('address')?.resume || null,
        city: field('city')?.resume || null,
        province: field('province')?.resume || null,
        objective: field('bio')?.resume || null,
        education_level: field('education_level')?.resume || null,
        education: review.education,
        experience: review.experience.map((e) => ({ ...e, responsibilities: e.responsibilities.split('\n').map((s) => s.trim()).filter(Boolean) })),
        skills: selectedSkills.map((s) => ({ name: s.name })),
        certifications: review.certifications.map((name) => ({ name, type: certTypes[name]?.type, issuer: certTypes[name]?.issuer, date: certTypes[name]?.date })),
        trainings: review.trainings, awards: review.awards, projects: review.projects, volunteer: review.volunteer,
        languages: review.languages.map((l) => { const m = l.match(/^(.*?)\s*\((.*)\)$/); return m ? { name: m[1], proficiency: m[2] } : { name: l } }),
      },
    }
    setSaving(true)
    try {
      const res = await resumeProfileService.confirm(body)
      const applied = res.data.data.applied || {}
      const updatedFields = Object.fromEntries((applied.profile_fields || []).map((k) => [k, profileUpdates[k]]))
      if (Object.keys(updatedFields).length) updateUser(updatedFields)
      applyPayload(res.data.data)
      setRecsKey((k) => k + 1)
      const parts = []
      if (applied.profile_fields?.length) parts.push(`${applied.profile_fields.length} profile field${applied.profile_fields.length > 1 ? 's' : ''} updated`)
      if (applied.skills_added) parts.push(`${applied.skills_added} skill${applied.skills_added > 1 ? 's' : ''} added`)
      toast.success(parts.length ? `Saved: ${parts.join(', ')}.` : 'Your resume information was confirmed.')
      Object.values(applied.skipped || {}).forEach((msg) => toast(msg, { icon: 'ℹ️' }))
    } catch (err) {
      const errors = err.response?.data?.errors
      toast.error(errors ? Object.values(errors).join(' ') : (err.response?.data?.message || 'Unable to save. Please try again.'), { duration: 7000 })
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <LoadingSpinner text="Loading your resume..." />
  if (loadError) {
    return (
      <div className="alert-danger max-w-3xl">
        <FiAlertTriangle className="mt-0.5" /><div className="flex-1">{loadError}</div>
        <button onClick={load} className="btn-secondary btn-sm">Try again</button>
      </div>
    )
  }

  const resume = data?.resume
  const status = resume?.status
  const confirmed = status === 'confirmed'

  return (
    <div className="animate-fade-in space-y-6">
      <div className="page-header">
        <h1 className="page-title">My Resume</h1>
        <p className="page-subtitle">Upload your resume, check what we found, and get job recommendations based on it.</p>
      </div>

      {/* Upload / current file */}
      <section className="card-flat space-y-4">
        {resume && (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-center gap-3">
              <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"><FiFileText size={20} /></div>
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{resume.original_name}</p>
                <p className="text-xs text-gray-500">
                  {formatBytes(resume.file_size)} · {resume.page_count} page{resume.page_count === 1 ? '' : 's'} · uploaded {new Date(resume.uploaded_at.replace(' ', 'T')).toLocaleString()}
                </p>
              </div>
              <span className={`badge ${STATUS_INFO[status]?.[0] || 'badge-gray'} flex-shrink-0`}>{STATUS_INFO[status]?.[1] || status}</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <button onClick={viewResume} className="btn-secondary btn-sm"><FiEye /> View</button>
              <button onClick={() => fileRef.current?.click()} className="btn-secondary btn-sm" disabled={upload.busy}><FiRefreshCw /> Replace</button>
              <button onClick={() => setDeleteOpen(true)} className="btn-sm btn-danger"><FiTrash2 /> Delete</button>
            </div>
          </div>
        )}

        {(!resume || upload.busy) && (
          <div
            onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(e) => { e.preventDefault(); setDragOver(false); handleFile(e.dataTransfer.files?.[0]) }}
            className={`rounded-2xl border-2 border-dashed p-6 text-center transition-colors sm:p-10 ${dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50'}`}
          >
            {upload.busy ? (
              <div className="mx-auto max-w-sm space-y-3" role="status" aria-live="polite">
                <div className="spinner mx-auto h-8 w-8" />
                <p className="font-semibold text-gray-800">{upload.stage}...</p>
                {upload.stage === 'Uploading' ? (
                  <div className="h-2 rounded-full bg-gray-200"><div className="h-2 rounded-full bg-blue-600 transition-all" style={{ width: `${upload.progress}%` }} /></div>
                ) : (
                  <p className="text-sm text-gray-500">Finding your experience, education, skills and qualifications. This can take up to a minute.</p>
                )}
              </div>
            ) : (
              <>
                <FiUploadCloud className="mx-auto mb-3 text-blue-500" size={36} />
                <p className="font-semibold text-gray-800">Drag and drop your resume here</p>
                <p className="mt-1 text-sm text-gray-500">PDF (recommended) or DOCX · up to {limits.max_size_mb} MB · up to {limits.max_pages} pages · any layout or template</p>
                <button onClick={() => fileRef.current?.click()} className="btn-primary mt-4"><FiUploadCloud /> Choose File</button>
                {data?.has_legacy_resume && <p className="mt-3 text-xs text-gray-500">You uploaded a resume before this feature existed. Upload it again to extract its information.</p>}
              </>
            )}
          </div>
        )}
        <input ref={fileRef} type="file" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" className="hidden" onChange={(e) => handleFile(e.target.files?.[0])} />
        <p className="flex items-start gap-2 text-xs text-gray-500">
          <FiInfo className="mt-0.5 flex-shrink-0" />
          Your resume is stored privately. Only you, PESO/admin staff, and employers you apply to can open it. You can replace or delete it at any time.
        </p>
      </section>

      {/* Scanned resume */}
      {status === 'needs_ocr' && (
        <section className="card-flat space-y-3">
          <h3 className="flex items-center gap-2 font-bold text-gray-900"><FiCpu /> Your resume looks like a scanned image</h3>
          <p className="text-sm text-gray-600">
            We could not find selectable text in this file. We can recognise the text on your device (nothing extra is uploaded), or you can upload a text-based PDF exported from Word, Google Docs or Canva for the most accurate results.
          </p>
          {ocr.busy ? (
            <div className="space-y-2" role="status" aria-live="polite">
              <p className="text-sm font-semibold capitalize text-gray-700">{ocr.stage}... {ocr.progress}%</p>
              <div className="h-2 rounded-full bg-gray-200"><div className="h-2 rounded-full bg-blue-600 transition-all" style={{ width: `${ocr.progress}%` }} /></div>
              <p className="text-xs text-gray-500">The first run downloads the text-recognition data (about 10 MB).</p>
            </div>
          ) : (
            <div className="flex flex-wrap gap-2">
              <button onClick={runOcr} className="btn-primary"><FiCpu /> Recognise Text</button>
              <button onClick={() => fileRef.current?.click()} className="btn-secondary"><FiUploadCloud /> Upload a Different File</button>
            </div>
          )}
        </section>
      )}

      {/* Confirmed summary */}
      {confirmed && !editing && (
        <div className="alert-success">
          <FiCheckCircle className="mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            Your resume information was confirmed on {new Date(resume.confirmed_at.replace(' ', 'T')).toLocaleString()}.
            {' '}{data.confirmed?.skills?.length || 0} skills, {data.confirmed?.experience?.length || 0} work entries and {data.confirmed?.education?.length || 0} education entries are used for your recommendations.
          </div>
          <button onClick={() => setEditing(true)} className="btn-secondary btn-sm">Review Again</button>
        </div>
      )}

      {/* Review */}
      {review && editing && (
        <div className="space-y-6">
          <div className="alert-warning">
            <FiAlertTriangle className="mt-0.5 flex-shrink-0" />
            <div>
              <p className="font-semibold">Please review before anything is saved to your profile.</p>
              <p className="mt-1 font-normal">We only show information found in your resume. Correct anything that is wrong, untick anything you do not want saved, and choose which value to keep when your profile and resume differ.</p>
              {resume?.ai_used && <p className="mt-1 text-xs font-normal">AI assistance was used to read the layout; every value was checked against the text of your resume.</p>}
              {review.warnings.length > 0 && (
                <ul className="mt-2 list-disc pl-5 text-xs font-normal">{review.warnings.map((w) => <li key={w}>{w}</li>)}</ul>
              )}
            </div>
          </div>

          <SectionCard icon={<FiUser />} title="Personal information" subtitle="Tick “Update” to copy the resume value into your profile. Empty resume values never erase your profile.">
            {review.email && (
              <p className="text-xs text-gray-500">Email found in resume: <span className="font-medium text-gray-700">{review.email}</span>{norm(review.email) !== norm(data.current_profile?.email) && ' — your login email is not changed automatically.'}</p>
            )}
            <div className="space-y-3">
              {review.fields.map((f) => (
                <div key={f.key} className={`rounded-xl border p-3 ${f.conflict ? 'border-amber-200 bg-amber-50/50' : 'border-gray-100'}`}>
                  <div className="mb-2 flex flex-wrap items-center gap-2">
                    <span className="text-sm font-semibold text-gray-800">{f.label}</span>
                    {f.confidence && <span className={`badge ${CONFIDENCE_STYLE[f.confidence]}`} title={f.evidence}>{CONFIDENCE_LABEL[f.confidence]}</span>}
                    {f.conflict && <span className="badge badge-yellow">Differs from your profile</span>}
                    {f.same && <span className="badge badge-gray">Same as profile</span>}
                  </div>
                  <div className="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-center">
                    <div className="text-sm"><p className="text-xs text-gray-400">Current profile</p><p className="break-words text-gray-700">{displayValue(f.key, f.current)}</p></div>
                    <div><p className="mb-1 text-xs text-gray-400">From resume (editable)</p><FieldInput field={f} onChange={(v) => setField(f.key, { resume: v, same: norm(v) === norm(f.current) })} /></div>
                    <label className={`flex items-center gap-2 text-sm font-semibold ${f.locked || f.same || !f.resume ? 'text-gray-300' : 'text-gray-700'}`}>
                      <input type="checkbox" className="h-4 w-4" checked={f.apply && !f.locked && !f.same && !!f.resume}
                        disabled={f.locked || f.same || !f.resume} onChange={(e) => setField(f.key, { apply: e.target.checked })} />
                      {f.conflict ? 'Replace profile value' : 'Update profile'}
                    </label>
                  </div>
                  {f.locked && f.conflict && <p className="mt-2 text-xs text-gray-500">Your account is verified, so your name can only be changed by an administrator.</p>}
                </div>
              ))}
            </div>
          </SectionCard>

          <SectionCard icon={<FiAward />} title="Skills" subtitle={`${selectedSkills.length} selected. Skills that match the EmploySmart skill list are added to your profile; others are kept with your resume and still used for matching.`}>
            <div className="flex flex-wrap gap-2">
              {review.skills.map((s, i) => (
                <label key={`${s.name}-${i}`} title={s.evidence || ''}
                  className={`flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm transition-colors ${s.selected ? 'border-indigo-200 bg-indigo-50 text-indigo-900' : 'border-gray-200 bg-white text-gray-500'}`}>
                  <input type="checkbox" className="h-4 w-4" checked={s.selected} onChange={(e) => setReview((r) => ({ ...r, skills: r.skills.map((x, j) => (j === i ? { ...x, selected: e.target.checked } : x)) }))} />
                  <span className="font-medium">{s.name}</span>
                  {s.catalog && norm(s.catalog) !== norm(s.name) && <span className="text-xs text-gray-500">→ {s.catalog}</span>}
                  {s.inProfile && <span className="badge badge-gray">In profile</span>}
                  {s.confidence === 'low' && <span className="badge badge-gray">Mentioned only</span>}
                  {s.confidence === 'medium' && s.sources?.includes('experience') && <span className="badge badge-yellow">From experience</span>}
                </label>
              ))}
            </div>
            <div className="flex max-w-md gap-2">
              <input className="input" placeholder="Add a skill from your resume" value={newSkill} maxLength={60} onChange={(e) => setNewSkill(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addSkill())} />
              <button onClick={addSkill} className="btn-outline btn-sm"><FiPlus /> Add</button>
            </div>
          </SectionCard>

          <SectionCard icon={<FiBriefcase />} title="Work experience" subtitle="Job titles, employers and dates are used to estimate relevant experience."
            action={<button onClick={() => setList('experience', [...review.experience, { title: '', company: '', location: '', start_date: '', end_date: '', is_current: false, responsibilities: '' }])} className="btn-outline btn-sm"><FiPlus /> Add</button>}>
            {review.experience.length === 0 && <p className="text-sm text-gray-400">No work experience was found in your resume.</p>}
            {review.experience.map((e, i) => (
              <div key={i} className="space-y-3 rounded-xl border border-gray-100 p-3">
                <div className="flex items-center justify-between gap-2">
                  <div className="flex flex-wrap gap-2">
                    {e.confidence && <span className={`badge ${CONFIDENCE_STYLE[e.confidence]}`}>{CONFIDENCE_LABEL[e.confidence]}</span>}
                    {(e.warnings || []).map((w) => <span key={w} className="badge badge-red">{w}</span>)}
                    {e.start_date && e.end_date && e.end_date < e.start_date && <span className="badge badge-red">End date is before start date</span>}
                  </div>
                  <button onClick={() => setList('experience', review.experience.filter((_, j) => j !== i))} className="btn-secondary btn-sm" aria-label="Remove entry"><FiTrash2 /></button>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="form-group"><label className="label">Job title</label><input className="input" value={e.title} onChange={(ev) => updateEntry('experience', i, { title: ev.target.value })} /></div>
                  <div className="form-group"><label className="label">Employer</label><input className="input" value={e.company} onChange={(ev) => updateEntry('experience', i, { company: ev.target.value })} /></div>
                  <div className="form-group"><label className="label">Start (YYYY-MM)</label><input className="input" type="month" value={e.start_date} onChange={(ev) => updateEntry('experience', i, { start_date: ev.target.value })} /></div>
                  <div className="form-group">
                    <label className="label">End (YYYY-MM)</label>
                    <input className="input" type="month" value={e.end_date} disabled={e.is_current} onChange={(ev) => updateEntry('experience', i, { end_date: ev.target.value })} />
                    <label className="mt-1 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" checked={e.is_current} onChange={(ev) => updateEntry('experience', i, { is_current: ev.target.checked, end_date: ev.target.checked ? '' : e.end_date })} /> I currently work here</label>
                  </div>
                </div>
                <div className="form-group"><label className="label">Responsibilities and achievements (one per line)</label>
                  <textarea className="input min-h-[90px]" value={e.responsibilities} onChange={(ev) => updateEntry('experience', i, { responsibilities: ev.target.value })} /></div>
              </div>
            ))}
          </SectionCard>

          <SectionCard icon={<FiBook />} title="Education"
            action={<button onClick={() => setList('education', [...review.education, { degree: '', field_of_study: '', institution: '', start_date: '', end_date: '', status: '', level: '' }])} className="btn-outline btn-sm"><FiPlus /> Add</button>}>
            {review.education.length === 0 && <p className="text-sm text-gray-400">No education was found in your resume.</p>}
            {review.education.map((e, i) => (
              <div key={i} className="grid gap-3 rounded-xl border border-gray-100 p-3 sm:grid-cols-2">
                <div className="form-group"><label className="label">Degree / course / level</label><input className="input" value={e.degree} onChange={(ev) => updateEntry('education', i, { degree: ev.target.value })} /></div>
                <div className="form-group"><label className="label">School / institution</label><input className="input" value={e.institution} onChange={(ev) => updateEntry('education', i, { institution: ev.target.value })} /></div>
                <div className="form-group"><label className="label">Field of study</label><input className="input" value={e.field_of_study} onChange={(ev) => updateEntry('education', i, { field_of_study: ev.target.value })} /></div>
                <div className="form-group"><label className="label">Level</label>
                  <select className="input" value={e.level} onChange={(ev) => updateEntry('education', i, { level: ev.target.value })}>
                    <option value="">—</option>{EDUCATION_LEVELS.slice(1).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select></div>
                <div className="form-group"><label className="label">Graduated / end (YYYY or YYYY-MM)</label><input className="input" value={e.end_date} placeholder="e.g. 2022" onChange={(ev) => updateEntry('education', i, { end_date: ev.target.value })} /></div>
                <div className="form-group"><label className="label">Status</label>
                  <select className="input" value={e.status} onChange={(ev) => updateEntry('education', i, { status: ev.target.value })}>
                    <option value="">Not stated</option><option value="completed">Completed</option><option value="ongoing">Ongoing</option><option value="incomplete">Not completed</option><option value="expected">Expected</option>
                  </select></div>
                <div className="sm:col-span-2 flex justify-end"><button onClick={() => setList('education', review.education.filter((_, j) => j !== i))} className="btn-secondary btn-sm"><FiTrash2 /> Remove</button></div>
              </div>
            ))}
          </SectionCard>

          <div className="grid gap-6 lg:grid-cols-2">
            <SectionCard icon={<FiAward />} title="Certifications & licenses"><StringListEditor items={review.certifications} onChange={(v) => setList('certifications', v)} placeholder="e.g. NC II Computer Systems Servicing" /></SectionCard>
            <SectionCard icon={<FiBook />} title="Trainings & seminars"><StringListEditor items={review.trainings} onChange={(v) => setList('trainings', v)} placeholder="Add a training or seminar" /></SectionCard>
            <SectionCard icon={<FiAward />} title="Awards & achievements"><StringListEditor items={review.awards} onChange={(v) => setList('awards', v)} placeholder="Add an award" /></SectionCard>
            <SectionCard icon={<FiFileText />} title="Projects"><StringListEditor items={review.projects} onChange={(v) => setList('projects', v)} placeholder="Add a project" /></SectionCard>
            <SectionCard icon={<FiUser />} title="Languages"><StringListEditor items={review.languages} onChange={(v) => setList('languages', v)} placeholder="e.g. English (fluent)" /></SectionCard>
            <SectionCard icon={<FiUser />} title="Volunteer work & organizations"><StringListEditor items={review.volunteer} onChange={(v) => setList('volunteer', v)} placeholder="Add an activity" /></SectionCard>
          </div>

          <div className="sticky bottom-0 z-10 -mx-4 flex flex-col gap-3 border-t border-gray-100 bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between md:mx-0 md:rounded-xl md:border">
            <p className="text-sm text-gray-600">
              {review.fields.filter((f) => f.apply && !f.same && !f.locked && f.resume).length} profile field(s) and {selectedSkills.length} skill(s) will be saved.
            </p>
            <div className="flex gap-2">
              {confirmed && <button onClick={() => applyPayload(data)} className="btn-secondary" disabled={saving}>Cancel</button>}
              <button onClick={confirm} className="btn-primary" disabled={saving}>
                {saving ? <><div className="spinner h-4 w-4" /> Saving...</> : <><FiCheckCircle /> Confirm & Update Profile</>}
              </button>
            </div>
          </div>
        </div>
      )}

      {confirmed && !editing && <ResumeRecommendations limit={12} refreshKey={recsKey} />}

      {deleteOpen && (
        <Modal title="Delete your resume?" onClose={() => setDeleteOpen(false)} size="sm"
          footer={<><button onClick={() => setDeleteOpen(false)} className="btn-secondary">Cancel</button><button onClick={deleteResume} className="btn-danger">Delete</button></>}>
          <div className="space-y-3 text-sm text-gray-600">
            <p>The uploaded file and all information extracted from it will be permanently deleted. Resume-based recommendations will stop until you upload a new resume.</p>
            <p>Profile fields you already confirmed stay in your profile; you can edit them on the My Profile page.</p>
            {data?.confirmed?.added_skill_ids?.length > 0 && (
              <label className="flex items-start gap-2">
                <input type="checkbox" className="mt-0.5 h-4 w-4" checked={removeSkills} onChange={(e) => setRemoveSkills(e.target.checked)} />
                <span>Also remove the {data.confirmed.added_skill_ids.length} skill(s) that were added to my profile from this resume.</span>
              </label>
            )}
          </div>
        </Modal>
      )}
    </div>
  )
}
