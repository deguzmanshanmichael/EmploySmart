import { useState, useEffect, useMemo } from 'react'
import { trainingService, userService } from '../../services/index'
import { LoadingSpinner, EmptyState } from '../../components/index'
import toast from 'react-hot-toast'
import { FiUserPlus } from 'react-icons/fi'

export default function EnrollParticipants() {
  const [programs, setPrograms] = useState([])
  const [users, setUsers] = useState([])
  const [selectedProg, setSelectedProg] = useState(null)
  const [search, setSearch] = useState('')
  const [programSearch, setProgramSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [enrolling, setEnrolling] = useState(null)
  const [enrolled, setEnrolled] = useState(new Set())

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true)
        const [upcomingRes, ongoingRes, usersRes] = await Promise.all([
          trainingService.getAll({ status: 'upcoming', limit: 100 }),
          trainingService.getAll({ status: 'ongoing', limit: 100 }),
          userService.getAll({ role: 'jobseeker', limit: 200 }),
        ])

        setPrograms([
          ...(upcomingRes.data.data || []),
          ...(ongoingRes.data.data || []),
        ])
        setUsers(usersRes.data.data || [])
      } catch (err) {
        console.error('Failed to load data:', err)
        toast.error('Failed to load data. Please refresh the page.')
      } finally {
        setLoading(false)
      }
    }

    load()
  }, [])

  useEffect(() => {
    if (!selectedProg) {
      setEnrolled(new Set())
      return
    }

    const loadEnrolled = async () => {
      try {
        const res = await trainingService.getOne(selectedProg)
        const participants = res.data.data?.participants || []
        setEnrolled(new Set(participants.filter((p) => p.status !== 'dropped').map((p) => p.user_id)))
      } catch (err) {
        console.error('Failed to load enrolled participants:', err)
        setEnrolled(new Set())
      }
    }

    loadEnrolled()
  }, [selectedProg])

  const selectedProgram = useMemo(
    () => programs.find((program) => program && program.id === selectedProg) || null,
    [programs, selectedProg]
  )

  const handleEnroll = async (userId) => {
    if (!selectedProg) {
      toast.error('Please select a training program first')
      return
    }
    if (enrolling) return

    setEnrolling(userId)
    try {
      await trainingService.enroll(selectedProg, userId)
      setEnrolled((prev) => new Set([...prev, userId]))
      toast.success('Jobseeker enrolled successfully!')
    } catch (err) {
      console.error('Enrollment error:', err)
      const errorMessage = err.response?.data?.message || 'Failed to enroll participant'
      toast.error(errorMessage)
    } finally {
      setEnrolling(null)
    }
  }

  const filteredUsers = useMemo(
    () =>
      users.filter(
        (u) =>
          u &&
          u.first_name &&
          u.last_name &&
          u.email &&
          `${u.first_name} ${u.last_name} ${u.email}`.toLowerCase().includes(search.toLowerCase())
      ),
    [users, search]
  )

  const filteredPrograms = useMemo(
    () =>
      programs.filter(
        (program) =>
          program &&
          program.program_name &&
          program.program_name.toLowerCase().includes(programSearch.toLowerCase())
      ),
    [programs, programSearch]
  )

  if (loading) return <LoadingSpinner />

  if (!loading && programs.length === 0) {
    return (
      <div className="animate-fade-in space-y-5">
        <div className="page-header">
          <h1 className="page-title">Enroll Participants 👤</h1>
          <p className="page-subtitle">Add jobseekers to training programs</p>
        </div>
        <EmptyState icon="📚" title="No training programs available" subtitle="Create some training programs first before enrolling participants." />
      </div>
    )
  }

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Enroll Participants 👤</h1>
        <p className="page-subtitle">Add jobseekers to training programs</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div className="space-y-5">
          <div className="card space-y-4">
            <div>
              <h2 className="font-semibold text-lg">Training Programs</h2>
              <p className="text-sm text-gray-500">Select the program to enroll jobseekers in.</p>
            </div>

            <div className="form-group">
              <label className="label">Search programs</label>
              <input
                className="input"
                placeholder="Search programs..."
                value={programSearch}
                onChange={(e) => setProgramSearch(e.target.value)}
              />
            </div>

            <div className="grid gap-2 max-h-[420px] overflow-y-auto">
              {filteredPrograms.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                  No programs available. Create one first in the CLCDO dashboard.
                </div>
              ) : (
                filteredPrograms.map((program) => (
                  <button
                    key={program.id}
                    type="button"
                    className={`w-full rounded-lg border p-4 text-left transition ${selectedProg === program.id ? 'border-blue-500 bg-blue-50 text-blue-900' : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300 hover:bg-gray-50'}`}
                    onClick={() => setSelectedProg(program.id)}
                  >
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="font-semibold">{program.program_name}</p>
                        <p className="text-xs text-gray-500">{program.location || 'No location'} • {program.status}</p>
                      </div>
                      <span className="text-xs font-semibold uppercase text-gray-500">{program.enrolled_count || 0} enrolled</span>
                    </div>
                  </button>
                ))
              )}
            </div>
          </div>

          {selectedProgram && (
            <div className="card border-l-4 border-green-500 bg-green-50 p-5 text-sm text-gray-700">
              <h3 className="font-semibold text-green-800">Selected Program</h3>
              <p className="mt-2 text-sm font-medium">{selectedProgram.program_name}</p>
              <p className="text-xs text-gray-600">{selectedProgram.location || 'Location not set'}</p>
              <p className="text-xs text-gray-600">Dates: {selectedProgram.start_date || 'TBD'}{selectedProgram.end_date ? ` – ${selectedProgram.end_date}` : ''}</p>
              <p className="text-xs text-gray-600">Enrolled: {selectedProgram.enrolled_count || 0}{selectedProgram.max_participants ? ` / ${selectedProgram.max_participants}` : ''}</p>
            </div>
          )}
        </div>

        <div className="card space-y-4">
          <div>
            <h2 className="font-semibold text-lg">Jobseekers</h2>
            <p className="text-sm text-gray-500">Search and enroll jobseekers into the selected program.</p>
          </div>

          <div className="form-group">
            <label className="label">Search jobseekers</label>
            <input
              className="input"
              placeholder="Search by name or email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {filteredUsers.length === 0 ? (
            <EmptyState icon="👥" title="No jobseekers found" description="Try changing your search or add more jobseekers to the system." />
          ) : (
            <div className="grid gap-3">
              {filteredUsers.map((user) => (
                <div key={user.id} className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4">
                  <div>
                    <p className="font-semibold text-gray-900">{user.first_name} {user.last_name}</p>
                    <p className="text-xs text-gray-500">{user.email}</p>
                    {user.education_level && <p className="text-xs text-gray-400">{user.education_level.replace(/_/g, ' ')}</p>}
                  </div>
                  <button
                    onClick={() => handleEnroll(user.id)}
                    disabled={!selectedProg || enrolled.has(user.id) || enrolling === user.id}
                    className={`flex-shrink-0 btn btn-sm ${enrolled.has(user.id) ? 'bg-green-100 text-green-700 cursor-default' : 'btn-primary'} ${(!selectedProg || enrolling === user.id) ? 'opacity-70 cursor-not-allowed' : ''}`}
                  >
                    {enrolling === user.id ? 'Enrolling...' : enrolled.has(user.id) ? '✓ Enrolled' : <><FiUserPlus size={14} className="mr-1" /> Enroll</>}
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
