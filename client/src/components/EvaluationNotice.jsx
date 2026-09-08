export default function EvaluationNotice({ className = '' }) {
  return (
    <div className={`border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 ${className}`} role="note">
      <div className="mx-auto flex max-w-7xl flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <p><strong>Evaluation phase:</strong> This system is being evaluated. Please use fictional information only and do not enter real personal, contact, employment, or payment details.</p>
        <span className="font-semibold whitespace-nowrap">Thank you.</span>
      </div>
    </div>
  )
}
