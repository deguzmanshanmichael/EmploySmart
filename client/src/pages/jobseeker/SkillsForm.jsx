import { useState, useEffect } from 'react'
import { skillService } from '../../services/index'
import { useAuth } from '../../context/AuthContext'
import { SkillTag, LoadingSpinner } from '../../components/index'
import toast from 'react-hot-toast'
import { FiPlus, FiSave } from 'react-icons/fi'

export default function SkillsForm() {
  const { user } = useAuth()
  const [allSkills, setAllSkills]   = useState([])
  const [mySkills, setMySkills]     = useState([])
  const [selected, setSelected]     = useState(new Set())
  const [loading, setLoading]       = useState(true)
  const [saving, setSaving]         = useState(false)
  const [search, setSearch]         = useState('')
  const [newSkill, setNewSkill]     = useState('')
  const [adding, setAdding]         = useState(false)

  useEffect(() => {
    const load = async () => {
      if (!user?.id) {
        setLoading(false)
        toast.error('Please sign in again to manage your skills.')
        return
      }

      try {
        const [allRes, myRes] = await Promise.all([
          skillService.getAll(),
          skillService.getUserSkills(user.id),
        ])
        setAllSkills(allRes.data.data || [])
        const my = myRes.data.data || []
        setMySkills(my)
        setSelected(new Set(my.map(s => s.id)))
      } catch { toast.error('Failed to load skills') }
      setLoading(false)
    }
    load()
  }, [user.id])

  const toggle = (id) => {
    setSelected(s => {
      const next = new Set(s)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }

  const handleSave = async () => {
    if (!user?.id) {
      toast.error('Please sign in again to save your skills.')
      return
    }

    setSaving(true)
    try {
      await skillService.updateUserSkills(user.id, [...selected])
      toast.success('Skills updated successfully!')
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || 'Failed to save skills'
      toast.error(message)
    }
    setSaving(false)
  }

  const addCustomSkill = async (rawName) => {
    const name = rawName.trim().replace(/\s+/g, ' ')
    if (name.length < 2) { toast.error('Please type a skill name (at least 2 characters).'); return }
    const existing = allSkills.find(s => s.skill_name.toLowerCase() === name.toLowerCase())
    if (existing) {
      setSelected(s => new Set([...s, existing.id]))
      toast.success(`"${existing.skill_name}" selected. Remember to save.`)
      setNewSkill(''); setSearch('')
      return
    }
    setAdding(true)
    try {
      const res = await skillService.addCustom(user.id, name)
      const skill = res.data.data
      setAllSkills(list => list.some(s => s.id === skill.id) ? list : [...list, { id: skill.id, skill_name: skill.skill_name }].sort((a, b) => a.skill_name.localeCompare(b.skill_name)))
      setSelected(s => new Set([...s, skill.id]))
      toast.success(skill.skill_name.toLowerCase() === name.toLowerCase() ? `"${skill.skill_name}" added to your skills.` : `Added as "${skill.skill_name}" (same skill in the list).`)
      setNewSkill(''); setSearch('')
    } catch (err) {
      toast.error(err?.response?.data?.message || 'Could not add the skill')
    } finally {
      setAdding(false)
    }
  }

  const filtered = allSkills.filter(s =>
    s.skill_name.toLowerCase().includes(search.toLowerCase())
  )

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-6 max-w-3xl">
      <div className="page-header">
        <h1 className="page-title">My Skills 🎓</h1>
        <p className="page-subtitle">Select the skills you have — this improves your job match score</p>
      </div>

      {/* Currently selected */}
      <div className="card-flat">
        <h3 className="font-bold text-gray-800 mb-3">
          Currently Selected ({selected.size})
        </h3>
        {selected.size === 0 ? (
          <p className="text-sm text-gray-400 italic">No skills selected yet</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {allSkills.filter(s => selected.has(s.id)).map(s => (
              <SkillTag key={s.id} name={s.skill_name} onRemove={() => toggle(s.id)} />
            ))}
          </div>
        )}
      </div>

      {/* Skill picker */}
      <div className="card-flat">
        <div className="flex items-center justify-between mb-3">
          <h3 className="font-bold text-gray-800">All Skills</h3>
          <input
            className="input w-48 text-sm"
            placeholder="Search skills..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
        </div>
        {search.trim() && filtered.length === 0 && (
          <div className="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
            No skill named "{search.trim()}" in the list.
            <button onClick={() => addCustomSkill(search)} disabled={adding} className="btn-primary btn-sm"><FiPlus /> Add "{search.trim()}" as a new skill</button>
          </div>
        )}
        <div className="flex flex-wrap gap-2">
          {filtered.map(s => {
            const active = selected.has(s.id)
            return (
              <button
                key={s.id}
                onClick={() => toggle(s.id)}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border-2 transition-all ${
                  active
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-gray-200 text-gray-600 hover:border-blue-400 hover:text-blue-600'
                }`}
              >
                {active ? '✓' : <FiPlus size={12} />}
                {s.skill_name}
              </button>
            )
          })}
        </div>
      </div>

      {/* Skills not in the list */}
      <div className="card-flat space-y-2">
        <h3 className="font-bold text-gray-800">Can't find your skill?</h3>
        <p className="text-sm text-gray-500">Type it below to add it to your profile, e.g. "Forklift Operation" or "Canva".</p>
        <div className="flex flex-col gap-2 sm:flex-row">
          <input className="input" maxLength={60} placeholder="New skill name" value={newSkill}
            onChange={e => setNewSkill(e.target.value)} onKeyDown={e => e.key === 'Enter' && addCustomSkill(newSkill)} />
          <button onClick={() => addCustomSkill(newSkill)} disabled={adding || !newSkill.trim()} className="btn-outline flex-shrink-0">
            {adding ? <><div className="spinner w-4 h-4" /> Adding...</> : <><FiPlus /> Add Skill</>}
          </button>
        </div>
      </div>

      <button onClick={handleSave} disabled={saving} className="btn-primary btn-lg">
        {saving ? <><div className="spinner w-4 h-4" /> Saving...</> : <><FiSave /> Save Skills</>}
      </button>
    </div>
  )
}