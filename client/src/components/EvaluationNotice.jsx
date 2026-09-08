import { useState } from 'react'
import { FiX } from 'react-icons/fi'

const DISMISS_KEY = 'employsmart-evaluation-notice-dismissed'

export default function EvaluationNotice({ className = '' }) {
  const [dismissed, setDismissed] = useState(() => localStorage.getItem(DISMISS_KEY) === 'true')

  if (dismissed) return null

  const dismiss = () => {
    localStorage.setItem(DISMISS_KEY, 'true')
    setDismissed(true)
  }

  return (
    <div className={`border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-950 ${className}`} role="note">
      <div className="mx-auto flex max-w-7xl flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <p><strong>Evaluation phase:</strong> Use fictional information only. Thank you.</p>
        <button type="button" onClick={dismiss} className="self-end rounded p-1 text-amber-800 hover:bg-amber-100 sm:self-center" aria-label="Dismiss evaluation notice" title="Dismiss"><FiX size={17} /></button>
      </div>
    </div>
  )
}
