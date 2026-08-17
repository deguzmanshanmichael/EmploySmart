// TrainingPrograms.jsx
import { useState, useEffect, useCallback } from 'react'
import { trainingService } from '../../services/index'
import { TrainingCard, LoadingSpinner, EmptyState, Pagination, ConfirmDialog, Modal } from '../../components/index'
import { Link } from 'react-router-dom'
import { FiPlus } from 'react-icons/fi'
import toast from 'react-hot-toast'

export default function TrainingPrograms() {
  const [programs, setPrograms] = useState([])
  const [total, setTotal]   = useState(0)
  const [pages, setPages]   = useState(1)
  const [page, setPage]     = useState(1)
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [deleting, setDeleting] = useState(null)
  const [showModal, setShowModal] = useState(false)
  const [selectedProgram, setSelectedProgram] = useState(null)
  const [enrolledUsers, setEnrolledUsers] = useState([])
  const [loadingEnrolled, setLoadingEnrolled] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await trainingService.getAll({ status, search, page, limit: 20 })
      setPrograms(res.data.data || [])
      setTotal(res.data.pagination?.total || 0)
      setPages(res.data.pagination?.pages || 1)
    } catch { toast.error('Failed to load') }
    setLoading(false)
  }, [status, search, page])

  useEffect(() => { load() }, [load])

  const handleDelete = async (training) => {
    setDeleting(training.id)
    try {
      await trainingService.delete(training.id)
      toast.success('Program cancelled successfully')
      load() // Reload the list
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to cancel program')
    }
    setDeleting(null)
  }

  const handleViewEnrolled = async (training) => {
    if (!training || !training.id) {
      toast.error('Invalid training program')
      return
    }

    setSelectedProgram(training)
    setShowModal(true)
    setLoadingEnrolled(true)
    setEnrolledUsers([]) // Clear previous data

    try {
      const res = await trainingService.getOne(training.id)
      setEnrolledUsers(res.data.data?.participants || [])
    } catch (err) {
      console.error('Failed to load enrolled users:', err)
      toast.error('Failed to load enrolled users')
      setShowModal(false) // Close modal on error
    } finally {
      setLoadingEnrolled(false)
    }
  }

  const handleCloseModal = () => {
    setShowModal(false)
    setSelectedProgram(null)
    setEnrolledUsers([])
    setLoadingEnrolled(false)
  }

  return (
    <div className="animate-fade-in space-y-5">
      <div className="flex items-center justify-between">
        <div className="page-header mb-0">
          <h1 className="page-title">Training Programs</h1>
          <p className="page-subtitle">{total} programs</p>
        </div>
        <Link to="/clcdo/create-training" className="btn-primary btn-sm"><FiPlus /> New Program</Link>
      </div>

      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
          {[{val:'',label:'All'},{val:'upcoming',label:'Upcoming'},{val:'ongoing',label:'Ongoing'},{val:'completed',label:'Completed'}].map(t => (
            <button key={t.val} onClick={() => { setStatus(t.val); setPage(1) }}
              className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-all ${status === t.val ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'}`}>
              {t.label}
            </button>
          ))}
        </div>

        <div className="w-full md:w-80">
          <label className="sr-only" htmlFor="training-search">Search programs</label>
          <input
            id="training-search"
            type="search"
            className="input w-full"
            placeholder="Search programs by name..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          />
        </div>
      </div>

      {loading ? <LoadingSpinner /> : programs.length === 0 ? (
        <EmptyState icon="📚" title="No programs" action={<Link to="/clcdo/create-training" className="btn-primary btn-sm">Create Program</Link>} />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {programs.map(p => <TrainingCard key={p.id} training={p} onDelete={handleDelete} onViewEnrolled={handleViewEnrolled} />)}
        </div>
      )}
      <Pagination page={page} pages={pages} onPageChange={setPage} />

      {deleting && programs.find(p => p.id === deleting) && (
        <ConfirmDialog
          title="Archive Training Program"
          message={`Are you sure you want to archive "${programs.find(p => p.id === deleting)?.program_name}"? The program will be hidden but data will be preserved.`}
          onConfirm={() => handleDelete(programs.find(p => p.id === deleting))}
          onCancel={() => setDeleting(null)}
        />
      )}

      <Modal show={showModal} onClose={handleCloseModal} title={`Enrolled in ${selectedProgram?.program_name || 'Program'}`}>
        {loadingEnrolled ? (
          <LoadingSpinner />
        ) : enrolledUsers.length === 0 ? (
          <EmptyState icon="👥" title="No participants enrolled" />
        ) : (
          <div className="space-y-3">
            {enrolledUsers.map(u => (
              <div key={u.user_id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                  <p className="font-semibold">{u.first_name} {u.last_name}</p>
                  <p className="text-sm text-gray-500">{u.email}</p>
                  <p className="text-xs text-gray-400">Status: {u.status}</p>
                </div>
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                  u.status === 'completed' ? 'bg-green-100 text-green-700' :
                  u.status === 'enrolled' ? 'bg-blue-100 text-blue-700' :
                  'bg-gray-100 text-gray-700'
                }`}>
                  {u.status}
                </span>
              </div>
            ))}
          </div>
        )}
      </Modal>
    </div>
  )
}