import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { trainingService, skillService } from '../../services/index'
import toast from 'react-hot-toast'
import { FiSave } from 'react-icons/fi'

export default function CreateTraining() {
  const navigate = useNavigate()
  const [skills, setSkills]   = useState([])
  const [selected, setSelected] = useState([])
  const [loading, setLoading] = useState(false)
  const [form, setForm] = useState({
    program_name: '', description: '', start_date: '', end_date: '',
    max_participants: '', location: 'Cabanatuan City', status: 'upcoming',
  })

  useEffect(() => {
    skillService.getAll().then(res => setSkills(res.data.data || []))
  }, [])

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))
  const toggleSkill = (id) => setSelected(s => s.includes(id) ? s.filter(x => x !== id) : [...s, id])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.program_name.trim() || !form.location.trim()) {
      toast.error('Program name and location required')
      return
    }
    if (form.max_participants !== '' && Number(form.max_participants) < 1) {
      toast.error('Maximum participants must be at least 1')
      return
    }
    if (form.start_date && form.end_date) {
      const start = new Date(form.start_date)
      const end = new Date(form.end_date)
      if (end < start) {
        toast.error('End date cannot be before start date')
        return
      }
    }

    setLoading(true)
    try {
      await trainingService.create({ ...form, skills: selected })
      toast.success('Training program created!')
      window.location.href = '/clcdo/programs'
    } catch (err) { toast.error(err.response?.data?.message || 'Failed') }
    setLoading(false)
  }

  return (
    <div className="animate-fade-in space-y-6 max-w-3xl">
      <div className="page-header">
        <h1 className="page-title">Create Training Program</h1>
        <p className="page-subtitle">Set up a new livelihood or skills training</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="card-flat space-y-4">
          <h3 className="font-bold text-gray-800">Program Details</h3>
          <div className="form-group">
            <label className="label">Program Name *</label>
            <input className="input" value={form.program_name} onChange={e => set('program_name', e.target.value)} placeholder="e.g. Basic Cooking Training" required />
          </div>
          <div className="form-group">
            <label className="label">Description</label>
            <textarea className="input h-28 resize-none" value={form.description} onChange={e => set('description', e.target.value)} placeholder="Describe the training program..." />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="form-group">
              <label className="label">Location *</label>
              <input className="input" value={form.location} onChange={e => set('location', e.target.value)} />
            </div>
            <div className="form-group">
              <label className="label">Max Participants</label>
              <input type="number" min="1" className="input" value={form.max_participants} onChange={e => set('max_participants', e.target.value)} placeholder="Leave blank for unlimited" />
            </div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="form-group">
              <label className="label">Start Date</label>
              <input type="date" className="input" value={form.start_date} onChange={e => set('start_date', e.target.value)} />
            </div>
            <div className="form-group">
              <label className="label">End Date</label>
              <input type="date" className="input" value={form.end_date} onChange={e => set('end_date', e.target.value)} />
            </div>
          </div>
          <div className="form-group">
            <label className="label">Status</label>
            <select className="input" value={form.status} onChange={e => set('status', e.target.value)}>
              <option value="upcoming">Upcoming</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>

        <div className="card-flat">
          <h3 className="font-bold text-gray-800 mb-3">Skills Covered ({selected.length} selected)</h3>
          <p className="text-xs text-gray-500 mb-3">Participants who complete this training will automatically gain these skills</p>
          <div className="flex flex-wrap gap-2">
            {skills.map(s => (
              <button key={s.id} type="button" onClick={() => toggleSkill(s.id)}
                className={`px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-all ${selected.includes(s.id) ? 'border-yellow-500 bg-yellow-500 text-white' : 'border-gray-200 text-gray-600 hover:border-yellow-400'}`}>
                {selected.includes(s.id) ? '✓ ' : ''}{s.skill_name}
              </button>
            ))}
          </div>
        </div>

        <div className="flex gap-3">
          <button type="button" onClick={() => navigate('/clcdo/programs')} className="btn-secondary">Cancel</button>
          <button type="submit" disabled={loading} className="btn-primary">
            {loading ? <><div className="spinner w-4 h-4" /> Creating...</> : <><FiSave /> Create Program</>}
          </button>
        </div>
      </form>
    </div>
  )
}