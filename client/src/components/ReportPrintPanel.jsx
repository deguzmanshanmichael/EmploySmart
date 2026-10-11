import { useState } from 'react'
import toast from 'react-hot-toast'
import { FiPrinter } from 'react-icons/fi'
import { printReport } from '../utils/printReport'
import { quarterStart, todayIso } from '../utils/reportBuilders'

const pad = (n) => String(n).padStart(2, '0')

/**
 * Choose a report and a date range, then print it in the official report format.
 * `reports`: [{ value, label, build: async (from, to) => printOptions }]
 */
export default function ReportPrintPanel({ reports, title = 'Print a report' }) {
  const [type, setType] = useState(reports[0]?.value)
  const [from, setFrom] = useState(quarterStart())
  const [to, setTo] = useState(todayIso())

  const preset = (kind) => {
    const d = new Date()
    if (kind === 'month') setFrom(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-01`)
    if (kind === 'quarter') setFrom(quarterStart(d))
    if (kind === 'year') setFrom(`${d.getFullYear()}-01-01`)
    setTo(todayIso())
  }

  const print = () => {
    if (!from || !to) { toast.error('Please choose both dates.'); return }
    if (from > to) { toast.error('The start date must be on or before the end date.'); return }
    const report = reports.find((r) => r.value === type)
    if (!printReport(() => report.build(from, to))) toast.error('Please allow pop-ups to print reports.')
  }

  return (
    <section className="card-flat space-y-3">
      <h2 className="flex items-center gap-2 font-bold text-gray-800"><FiPrinter /> {title}</h2>
      <div className="grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto] md:items-end">
        <div className="form-group">
          <label className="label" htmlFor="report-type">Report</label>
          <select id="report-type" className="input" value={type} onChange={(e) => setType(e.target.value)}>
            {reports.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
          </select>
        </div>
        <div className="form-group">
          <label className="label" htmlFor="report-from">From</label>
          <input id="report-from" type="date" className="input" value={from} max={to} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div className="form-group">
          <label className="label" htmlFor="report-to">To</label>
          <input id="report-to" type="date" className="input" value={to} min={from} onChange={(e) => setTo(e.target.value)} />
        </div>
        <button onClick={print} className="btn-primary"><FiPrinter /> Print Report</button>
      </div>
      <div className="flex flex-wrap gap-2 text-xs">
        <span className="text-gray-500">Quick range:</span>
        <button type="button" onClick={() => preset('month')} className="btn-secondary btn-sm">This month</button>
        <button type="button" onClick={() => preset('quarter')} className="btn-secondary btn-sm">This quarter</button>
        <button type="button" onClick={() => preset('year')} className="btn-secondary btn-sm">This year</button>
      </div>
    </section>
  )
}
