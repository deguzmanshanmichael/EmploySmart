// CreateJob.jsx
import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { jobService } from '../../services/jobService'
import { skillService, employerService } from '../../services/index'
import toast from 'react-hot-toast'
import { FiSend } from 'react-icons/fi'

export default function CreateJob() {
  const { user }   = useAuth()
  const navigate   = useNavigate()
  const [skills, setSkills]       = useState([])
  const [selected, setSelected]   = useState([])
  const [loading, setLoading]     = useState(false)
  const [employer, setEmployer]   = useState(null)
  const [form, setForm] = useState({
    title: '', description: '', requirements: '', location: 'Cabanatuan City',
    salary_range: '', vacancies: 1, deadline: '', job_type: 'fulltime',
    experience_level: 'entry', education_required: '',
  })

  useEffect(() => {
    const load = async () => {
      try {
        const [sRes, eRes] = await Promise.all([
          skillService.getAll(),
          employerService.getByUser(user.id),
        ])
        setSkills(sRes.data.data || [])
        setEmployer(eRes.data.data)
        if (eRes.data.data?.verification_status !== 'approved') {
          toast.error('Your account is not verified yet')
          navigate('/employer')
        }
      } catch { navigate('/employer') }
    }
    load()
  }, [user.id, navigate])

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  const toggleSkill = (id) => setSelected(s => s.includes(id) ? s.filter(x => x !== id) : [...s, id])

  const handleSubmit = async (e) => {
    e.preventDefault()
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (!form.title.trim() || !form.description.trim()) {
      toast.error('Title and description required')
      return
    }
    if (form.vacancies === '' || Number(form.vacancies) < 1) {
      toast.error('Vacancies must be at least 1')
      return
    }
    if (form.deadline) {
      const deadline = new Date(form.deadline)
      deadline.setHours(0, 0, 0, 0)
      if (deadline < today) {
        toast.error('Deadline cannot be in the past')
        return
      }
    }

    setLoading(true)
    try {
      await jobService.create({ ...form, skills: selected })
      toast.success('Job posted! Waiting for PESO approval.')
      navigate('/employer/jobs')
    } catch (err) { toast.error(err.response?.data?.message || 'Failed to post job') }
    setLoading(false)
  }

  return (
    <div className="animate-fade-in space-y-6 max-w-3xl">
      <div className="page-header">
        <h1 className="page-title">Post a Job</h1>
        <p className="page-subtitle">Fill in the details — your job will be reviewed by PESO before going live</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="card-flat space-y-4">
          <h3 className="font-bold text-gray-800">Job Details</h3>
          <div className="form-group">
            <label className="label">Job Title *</label>
            <input className="input" value={form.title} onChange={e => set('title', e.target.value)} placeholder="e.g. Customer Service Representative" required />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="form-group">
              <label className="label">Job Type *</label>
              <select className="input" value={form.job_type} onChange={e => set('job_type', e.target.value)}>
                <option value="fulltime">Full-time</option>
                <option value="parttime">Part-time</option>
                <option value="contract">Contract</option>
                <option value="internship">Internship</option>
              </select>
            </div>
            <div className="form-group">
              <label className="label">Experience Level</label>
              <select className="input" value={form.experience_level} onChange={e => set('experience_level', e.target.value)}>
                <option value="entry">Entry Level</option>
                <option value="mid">Mid Level</option>
                <option value="senior">Senior Level</option>
              </select>
            </div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="form-group">
              <label className="label">Location *</label>
              <input className="input" value={form.location} onChange={e => set('location', e.target.value)} />
            </div>
            <div className="form-group">
              <label className="label">Salary Range</label>
              <input className="input" value={form.salary_range} onChange={e => set('salary_range', e.target.value)} placeholder="e.g. ₱15,000 – ₱20,000" />
            </div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="form-group">
              <label className="label">Number of Vacancies</label>
              <input type="number" min="1" className="input" value={form.vacancies} onChange={e => set('vacancies', e.target.value)} />
            </div>
            <div className="form-group">
              <label className="label">Application Deadline</label>
              <input type="date" className="input" value={form.deadline} onChange={e => set('deadline', e.target.value)} />
            </div>
          </div>
          <div className="form-group">
            <label className="label">Education Required</label>
            <input className="input" value={form.education_required} onChange={e => set('education_required', e.target.value)} placeholder="e.g. College Graduate" />
          </div>
        </div>

        <div className="card-flat space-y-4">
          <h3 className="font-bold text-gray-800">Job Description</h3>
          <div className="form-group">
            <label className="label">Description *</label>
            <textarea className="input h-32 resize-none" value={form.description} onChange={e => set('description', e.target.value)} placeholder="Describe the job role, responsibilities..." required />
          </div>
          <div className="form-group">
            <label className="label">Requirements</label>
            <textarea className="input h-24 resize-none" value={form.requirements} onChange={e => set('requirements', e.target.value)} placeholder="List qualifications, experience needed..." />
          </div>
        </div>

        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-3">Required Skills ({selected.length} selected)</h3>
          <div className="flex flex-wrap gap-2">
            {skills.map(s => (
              <button key={s.id} type="button" onClick={() => toggleSkill(s.id)}
                className={`px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-all ${selected.includes(s.id) ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 text-gray-600 hover:border-blue-400'}`}>
                {selected.includes(s.id) ? '✓ ' : ''}{s.skill_name}
              </button>
            ))}
          </div>
        </div>

        <div className="flex gap-3">
          <button type="button" onClick={() => navigate('/employer/jobs')} className="btn-secondary">Cancel</button>
          <button type="submit" disabled={loading} className="btn-primary">
            {loading ? <><div className="spinner w-4 h-4" /> Posting...</> : <><FiSend /> Post Job</>}
          </button>
        </div>
      </form>
    </div>
  )
}