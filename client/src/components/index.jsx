// ─── JobCard ────────────────────────────────────────────────────────────────
import { FiMapPin, FiClock, FiBriefcase, FiStar, FiUsers } from 'react-icons/fi'
import { format } from 'date-fns'

const jobTypeBadge = {
  fulltime:   'badge-blue',
  parttime:   'badge-green',
  contract:   'badge-yellow',
  internship: 'badge-purple',
}

export function JobCard({ job, onApply, onView, applied = false, matchScore }) {
  return (
    <div className="card hover:shadow-md transition-all duration-200 animate-fade-in">
      {matchScore !== undefined && (
        <div className="flex items-center gap-2 mb-3">
          <div className="flex-1 bg-gray-100 rounded-full h-2">
            <div
              className="match-score-bar transition-all duration-500"
              style={{ width: `${matchScore}%` }}
            />
          </div>
          <span className="text-xs font-bold text-indigo-600 whitespace-nowrap">
            {matchScore}% match
          </span>
        </div>
      )}

      <div className="flex items-start justify-between gap-3 mb-3">
        <div className="flex-1 min-w-0">
          <h3 className="font-bold text-gray-900 text-base leading-snug truncate">
            {job.title}
          </h3>
          <p className="text-sm text-blue-700 font-semibold mt-0.5">{job.company_name}</p>
          {job.industry && <p className="text-xs text-gray-400 mt-0.5">{job.industry}</p>}
        </div>
        {job.job_type && (
          <span className={`${jobTypeBadge[job.job_type] || 'badge-gray'} flex-shrink-0 capitalize`}>
            {job.job_type}
          </span>
        )}
      </div>

      <div className="flex flex-wrap gap-x-4 gap-y-1.5 mb-3 text-xs text-gray-500">
        {job.location && (
          <span className="flex items-center gap-1"><FiMapPin size={12} /> {job.location}</span>
        )}
        {job.salary_range && (
          <span className="flex items-center gap-1">💰 {job.salary_range}</span>
        )}
        {job.vacancies && (
          <span className="flex items-center gap-1"><FiUsers size={12} /> {job.vacancies} slot{job.vacancies > 1 ? 's' : ''}</span>
        )}
        {job.deadline && (
          <span className="flex items-center gap-1">
            <FiClock size={12} /> Deadline: {format(new Date(job.deadline), 'MMM d, yyyy')}
          </span>
        )}
      </div>

      {job.skills?.length > 0 && (
        <div className="flex flex-wrap gap-1.5 mb-4">
          {job.skills.slice(0, 5).map((s) => (
            <SkillTag key={s.id} name={s.skill_name} size="sm" />
          ))}
          {job.skills.length > 5 && (
            <span className="badge badge-gray">+{job.skills.length - 5} more</span>
          )}
        </div>
      )}

      <div className="flex gap-2 mt-2">
        {onView && (
          <button onClick={() => onView(job)} className="btn-secondary btn-sm flex-1">
            View Details
          </button>
        )}
        {onApply && (
          <button
            onClick={() => onApply(job)}
            disabled={applied}
            className={`flex-1 ${applied ? 'btn btn-sm bg-green-100 text-green-700 cursor-default' : 'btn-primary btn-sm'}`}
          >
            {applied ? '✓ Applied' : 'Apply Now'}
          </button>
        )}
      </div>
    </div>
  )
}

// ─── SkillTag ────────────────────────────────────────────────────────────────
export function SkillTag({ name, onRemove, size = 'md' }) {
  const sizeClass = size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-2.5 py-1'
  return (
    <span className={`inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 font-medium ${sizeClass}`}>
      {name}
      {onRemove && (
        <button onClick={() => onRemove(name)} className="ml-0.5 hover:text-red-500 transition-colors leading-none">
          ×
        </button>
      )}
    </span>
  )
}

// ─── ApplicationCard ─────────────────────────────────────────────────────────
const normalizeApplicationStatus = status => status === 'rejected' ? 'declined' : status
const statusStyle = {
  pending:  'badge-yellow',
  reviewed: 'badge-blue',
  accepted: 'badge-green',
  declined: 'badge-red',
  rejected: 'badge-red',
}

export function ApplicationCard({ app, onWithdraw, onOpenMessages }) {
  return (
    <div className="card animate-fade-in">
      <div className="flex items-start justify-between gap-3 mb-2">
        <div>
          <h3 className="font-bold text-gray-900">{app.title}</h3>
          <p className="text-sm text-blue-700 font-semibold">{app.company_name}</p>
        </div>
        <span className={statusStyle[normalizeApplicationStatus(app.application_status)] || 'badge-gray'}>
          {normalizeApplicationStatus(app.application_status)}
        </span>
      </div>
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 mb-3">
        {app.location && <span className="flex items-center gap-1"><FiMapPin size={11} />{app.location}</span>}
        {app.job_type && <span className="flex items-center gap-1"><FiBriefcase size={11} />{app.job_type}</span>}
        <span className="flex items-center gap-1">
          <FiClock size={11} /> Applied {format(new Date(app.applied_at), 'MMM d, yyyy')}
        </span>
      </div>
      {app.remarks && (
        <p className="text-xs text-gray-600 bg-gray-50 rounded-lg p-2.5 mb-3">
          <span className="font-semibold">Remark:</span> {app.remarks}
        </p>
      )}
      {app.interview_date && (
        <p className="text-xs text-green-700 font-semibold mb-3">
          📅 Interview: {format(new Date(app.interview_date), 'MMM d, yyyy h:mm a')}
        </p>
      )}
      <div className="flex flex-wrap gap-2">
        {onOpenMessages && (
          <button onClick={() => onOpenMessages(app)} className="btn-secondary btn-sm">
            Employer's Note
          </button>
        )}
        {onWithdraw && app.application_status === 'pending' && (
          <button onClick={() => onWithdraw(app.id)} className="btn-danger btn-sm">
            Withdraw
          </button>
        )}
      </div>
    </div>
  )
}

// ─── TrainingCard ─────────────────────────────────────────────────────────────
const trainingStatus = {
  upcoming:  'badge-yellow',
  ongoing:   'badge-blue',
  completed: 'badge-green',
}

export function TrainingCard({ training, onEnroll, onDelete, onViewEnrolled, enrolled = false }) {
  const spotsLeft = training.max_participants
    ? training.max_participants - (training.enrolled_count || 0)
    : null

  return (
    <div className="card animate-fade-in">
      <div className="flex items-start justify-between gap-3 mb-2">
        <h3 className="font-bold text-gray-900">{training.program_name}</h3>
        <div className="flex items-center gap-2">
          <span className={trainingStatus[training.status] || 'badge-gray'}>{training.status}</span>
          {onDelete && (
            <button
              onClick={() => onDelete(training)}
              className="text-red-500 hover:text-red-700 p-1"
              title="Cancel Program"
            >
              🗑️
            </button>
          )}
        </div>
      </div>
      <p className="text-sm text-gray-500 mb-3 line-clamp-2">{training.description}</p>
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 mb-3">
        <span>📍 {training.location}</span>
        {training.start_date && <span>📅 {format(new Date(training.start_date), 'MMM d, yyyy')}</span>}
        {spotsLeft !== null && (
          <span className={spotsLeft <= 5 ? 'text-orange-600 font-semibold' : ''}>
            {spotsLeft} spots left
          </span>
        )}
      </div>
      {training.skills?.length > 0 && (
        <div className="flex flex-wrap gap-1.5 mb-3">
          {training.skills.map(s => <SkillTag key={s.id} name={s.skill_name} size="sm" />)}
        </div>
      )}
      {onEnroll && (
        <button
          disabled={enrolled || spotsLeft === 0}
          onClick={() => onEnroll(training)}
          className={enrolled ? 'btn btn-sm bg-green-100 text-green-700 cursor-default' : 'btn-primary btn-sm'}
        >
          {enrolled ? '✓ Enrolled' : spotsLeft === 0 ? 'Full' : 'Enroll'}
        </button>
      )}
      {onViewEnrolled && (
        <button
          onClick={() => onViewEnrolled(training)}
          className="btn-secondary btn-sm ml-2"
        >
          👥 View Enrolled
        </button>
      )}
    </div>
  )
}

// ─── Modal ───────────────────────────────────────────────────────────────────
import { FiX } from 'react-icons/fi'

export function Modal({ title, onClose, children, footer, size = 'md', show = true }) {
  const sizeClass = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }[size]

  if (!show) return null

  return (
    <div className="modal-overlay" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className={`modal-box ${sizeClass} w-full`}>
        <div className="modal-header">
          <h3 className="text-lg font-bold text-gray-900">{title}</h3>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">
            <FiX size={18} />
          </button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-footer">{footer}</div>}
      </div>
    </div>
  )
}

// ─── Pagination ───────────────────────────────────────────────────────────────
export function Pagination({ page, pages, onPageChange }) {
  if (pages <= 1) return null
  return (
    <div className="flex items-center justify-center gap-2 mt-6">
      <button
        disabled={page <= 1}
        onClick={() => onPageChange(page - 1)}
        className="btn-secondary btn-sm disabled:opacity-40"
      >
        ← Prev
      </button>
      <span className="text-sm text-gray-600 font-medium px-2">
        Page {page} of {pages}
      </span>
      <button
        disabled={page >= pages}
        onClick={() => onPageChange(page + 1)}
        className="btn-secondary btn-sm disabled:opacity-40"
      >
        Next →
      </button>
    </div>
  )
}

// ─── LoadingSpinner ───────────────────────────────────────────────────────────
export function LoadingSpinner({ text = 'Loading...' }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 gap-3">
      <div className="spinner w-8 h-8" />
      <p className="text-sm text-gray-500">{text}</p>
    </div>
  )
}

// ─── EmptyState ───────────────────────────────────────────────────────────────
export function EmptyState({ icon = '📭', title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <div className="text-5xl mb-4">{icon}</div>
      <h3 className="text-lg font-bold text-gray-700 mb-1">{title}</h3>
      {description && <p className="text-sm text-gray-400 mb-4">{description}</p>}
      {action}
    </div>
  )
}

// ─── ConfirmDialog ───────────────────────────────────────────────────────────
export function ConfirmDialog({ title, message, onConfirm, onCancel, danger = true }) {
  return (
    <Modal title={title} onClose={onCancel} size="sm"
      footer={
        <>
          <button onClick={onCancel} className="btn-secondary">Cancel</button>
          <button onClick={onConfirm} className={danger ? 'btn-danger' : 'btn-primary'}>Confirm</button>
        </>
      }
    >
      <p className="text-gray-600 text-sm">{message}</p>
    </Modal>
  )
}

// ─── StatCard ─────────────────────────────────────────────────────────────────
export function StatCard({ label, value, icon, color = 'bg-blue-50 text-blue-600', change }) {
  return (
    <div className="stat-card">
      <div className={`stat-icon ${color}`}>{icon}</div>
      <div>
        <p className="text-2xl font-bold text-gray-900">{value ?? '—'}</p>
        <p className="text-sm text-gray-500">{label}</p>
        {change !== undefined && (
          <p className={`text-xs font-semibold mt-0.5 ${change >= 0 ? 'text-green-600' : 'text-red-500'}`}>
            {change >= 0 ? '↑' : '↓'} {Math.abs(change)}% this month
          </p>
        )}
      </div>
    </div>
  )
}