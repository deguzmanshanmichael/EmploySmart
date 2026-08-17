import { useState, useEffect, useMemo, useCallback } from 'react'
import { trainingService } from '../../services/index'
import { LoadingSpinner, EmptyState } from '../../components/index'
import toast from 'react-hot-toast'
import { FiCheckCircle, FiUpload } from 'react-icons/fi'

export default function TrainingCompletion() {
  const [programs, setPrograms] = useState([])
  const [selectedProg, setSelectedProg] = useState(null)
  const [participants, setParticipants] = useState([])
  const [loading, setLoading] = useState(true)
  const [completing, setCompleting] = useState(null)
  const [dates, setDates] = useState({})
  const [certs, setCerts] = useState({})
  const [programSearch, setProgramSearch] = useState('')
  const [participantSearch, setParticipantSearch] = useState('')

  const loadPrograms = useCallback(async () => {
    setLoading(true)
    try {
      const res = await trainingService.getAll({ limit: 100 })
      setPrograms(res.data.data || [])
    } catch (err) {
      console.error('Failed to load programs:', err)
      toast.error('Failed to load training programs')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadPrograms()
  }, [loadPrograms])

  const selectedProgram = useMemo(
    () => programs.find((program) => program && program.id === selectedProg) || null,
    [programs, selectedProg]
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

  const filteredParticipants = useMemo(
    () =>
      participants.filter(
        (p) =>
          p &&
          `${p.first_name || ''} ${p.last_name || ''} ${p.email || ''}`.toLowerCase().includes(participantSearch.toLowerCase())
      ),
    [participants, participantSearch]
  )

  const loadParticipants = async (progId) => {
    setSelectedProg(progId)
    if (!progId) {
      setParticipants([])
      return
    }

    try {
      const res = await trainingService.getOne(progId)
      setParticipants((res.data.data?.participants || []).filter((p) => p.status !== 'dropped' && p.status !== 'completed'))
    } catch (err) {
      console.error('Failed to load participants:', err)
      toast.error('Failed to load enrolled participants')
      setParticipants([])
    }
  }

  const handleComplete = async (userId) => {
    if (!selectedProg) {
      toast.error('Select a training program first')
      return
    }

    setCompleting(userId)
    const fd = new FormData()
    fd.append('completion_date', dates[userId] || new Date().toISOString().split('T')[0])
    if (certs[userId]) fd.append('certificate', certs[userId])

    try {
      await trainingService.complete(selectedProg, userId, fd)
      setParticipants((ps) => ps.map((p) => p.user_id === userId ? { ...p, status: 'completed' } : p))
      toast.success('Marked as completed! Skills updated.')
    } catch (err) {
      console.error('Completion error:', err)
      toast.error(err.response?.data?.message || 'Failed to mark completion')
    } finally {
      setCompleting(null)
    }
  }

  if (loading) return <LoadingSpinner />

  return (
    <div className="animate-fade-in space-y-5">
      <div className="page-header">
        <h1 className="page-title">Mark Completion ✅</h1>
        <p className="page-subtitle">Issue certificates and complete training participants</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div className="space-y-5">
          <div className="card space-y-4">
            <div>
              <h2 className="font-semibold text-lg">Training Programs</h2>
              <p className="text-sm text-gray-500">Search and select the program to mark participants complete.</p>
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
                  No programs found. Try another search or create a program.
                </div>
              ) : (
                filteredPrograms.map((program) => (
                  <button
                    type="button"
                    key={program.id}
                    className={`w-full rounded-lg border p-4 text-left transition ${selectedProg === program.id ? 'border-blue-500 bg-blue-50 text-blue-900' : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300 hover:bg-gray-50'}`}
                    onClick={() => loadParticipants(program.id)}
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
            <h2 className="font-semibold text-lg">Participants</h2>
            <p className="text-sm text-gray-500">Search participants and mark completed status.</p>
          </div>

          <div className="form-group">
            <label className="label">Search participants</label>
            <input
              className="input"
              placeholder="Search by name or email..."
              value={participantSearch}
              onChange={(e) => setParticipantSearch(e.target.value)}
              disabled={!selectedProg}
            />
          </div>

          {!selectedProg ? (
            <EmptyState icon="📌" title="Select a program first" description="Choose a training program on the left to view enrolled participants." />
          ) : filteredParticipants.length === 0 ? (
            <EmptyState icon="👥" title="No participants found" description="Try a different search term or select another program." />
          ) : (
            <div className="grid gap-3">
              {filteredParticipants.map((participant) => (
                <div key={participant.user_id} className={`rounded-xl border p-4 ${participant.status === 'completed' ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'}`}>
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                      <p className="font-semibold text-gray-900">{participant.first_name} {participant.last_name}</p>
                      <p className="text-xs text-gray-500">{participant.email}</p>
                      <p className="text-xs text-gray-500">Status: {participant.status}</p>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-[1fr_auto] items-end">
                      <input
                        type="date"
                        className="input text-sm w-full"
                        value={dates[participant.user_id] || new Date().toISOString().split('T')[0]}
                        onChange={(e) => setDates((prev) => ({ ...prev, [participant.user_id]: e.target.value }))}
                        disabled={participant.status === 'completed'}
                      />
                      <label className="btn-secondary btn-sm cursor-pointer inline-flex items-center gap-1.5 justify-center">
                        <FiUpload size={13} />
                        {certs[participant.user_id] ? '✓ Cert' : 'Upload'}
                        <input
                          type="file"
                          accept=".pdf"
                          className="hidden"
                          onChange={(e) => setCerts((prev) => ({ ...prev, [participant.user_id]: e.target.files[0] }))}
                          disabled={participant.status === 'completed'}
                        />
                      </label>
                      <button
                        onClick={() => handleComplete(participant.user_id)}
                        disabled={participant.status === 'completed' || completing === participant.user_id}
                        className={`btn btn-sm ${participant.status === 'completed' ? 'bg-green-100 text-green-700 cursor-default' : 'btn-success'} ${completing === participant.user_id ? 'opacity-70 cursor-not-allowed' : ''}`}
                      >
                        {completing === participant.user_id ? 'Completing...' : participant.status === 'completed' ? 'Completed' : <><FiCheckCircle size={13} /> Complete</>}
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
