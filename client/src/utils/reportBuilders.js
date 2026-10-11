/**
 * Builders that turn report data into the official print layout (see printReport.js).
 * Every number, chart and insight sentence is computed from the records passed in.
 */
import api from '../services/api'
import { REPORT_COLORS as C } from './printReport'

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

// ─── Dates ────────────────────────────────────────────────────────────────────

/** Parse "YYYY-MM-DD[ HH:MM:SS]" without timezone shifts. */
function parts(value) {
  const m = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/)
  return m ? { y: +m[1], m: +m[2], d: +m[3], h: m[4] != null ? +m[4] : null, min: m[5] != null ? +m[5] : null } : null
}
const pad = (n) => String(n).padStart(2, '0')
const lastDay = (y, m) => new Date(y, m, 0).getDate()

export function longDate(value) {
  const p = parts(value)
  return p ? `${MONTHS[p.m - 1]} ${p.d}, ${p.y}` : '—'
}
export function dateTime(value) {
  const p = parts(value)
  if (!p) return '—'
  if (p.h == null) return longDate(value)
  const h12 = ((p.h + 11) % 12) + 1
  return `${longDate(value)}, ${h12}:${pad(p.min)} ${p.h < 12 ? 'AM' : 'PM'}`
}
export function rangeLabel(from, to) {
  return `${longDate(from)} - ${longDate(to)}`
}
export function todayIso() {
  const d = new Date()
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
export function quarterStart(date = new Date()) {
  return `${date.getFullYear()}-${pad(Math.floor(date.getMonth() / 3) * 3 + 1)}-01`
}

/** "Q1 2026", "March 2026", "2026", or the full range. */
export function periodLabel(from, to) {
  const a = parts(from), b = parts(to)
  if (!a || !b) return ''
  if (a.y === b.y && a.d === 1 && b.d === lastDay(b.y, b.m)) {
    if (a.m === b.m) return `${MONTHS[a.m - 1]} ${a.y}`
    if (a.m === 1 && b.m === 12) return String(a.y)
    if ((a.m - 1) % 3 === 0 && b.m === a.m + 2) return `Q${(a.m + 2) / 3} ${a.y}`
  }
  return rangeLabel(from, to)
}

/** Every month touched by the range, clipped to the range. */
export function monthsInRange(from, to) {
  const a = parts(from), b = parts(to)
  const out = []
  if (!a || !b) return out
  let y = a.y, m = a.m
  while (y < b.y || (y === b.y && m <= b.m)) {
    const startD = y === a.y && m === a.m ? a.d : 1
    const endD = y === b.y && m === b.m ? b.d : lastDay(y, m)
    out.push({
      key: `${y}-${pad(m)}`,
      label: `${MONTHS[m - 1].toUpperCase()} ${y}`,
      name: MONTHS[m - 1],
      short: MONTHS[m - 1].slice(0, 3),
      range: `${pad(m)}/${pad(startD)}/${y} - ${pad(m)}/${pad(endD)}/${y}`,
      items: [],
    })
    m += 1
    if (m > 12) { m = 1; y += 1 }
    if (out.length > 60) break
  }
  return out
}

export function groupByMonth(records, from, to, dateKey = 'date') {
  const months = monthsInRange(from, to)
  const index = Object.fromEntries(months.map((mo) => [mo.key, mo]))
  for (const r of records) {
    const key = String(r[dateKey] || '').slice(0, 7)
    if (index[key]) index[key].items.push(r)
  }
  // Short labels gain the year when the range spans more than one year.
  const multiYear = months.length && months[0].key.slice(0, 4) !== months[months.length - 1].key.slice(0, 4)
  if (multiYear) months.forEach((mo) => { mo.short = `${mo.short} ${mo.key.slice(2, 4)}` })
  return months
}

// ─── Text helpers ─────────────────────────────────────────────────────────────

const plural = (n, word, many) => `${n} ${n === 1 ? word : (many || `${word}s`)}`
const ACRONYMS = { peso: 'PESO', clcdo: 'CLCDO', lgu: 'LGU' }
const titleCase = (s) => ACRONYMS[String(s || '').toLowerCase()] || String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0)

/**
 * Insight from a monthly series: peak month, change from the previous month and
 * the trend after the peak, plus an optional two-way split and closing remark.
 */
export function monthlyInsight({ months, noun, nounSingular, period, split, closing }) {
  const total = months.reduce((s, m) => s + m.items.length, 0)
  if (total === 0) return `No ${noun} were recorded for ${period}.`
  const counts = months.map((m) => m.items.length)
  const sentences = []
  if (months.length === 1) {
    sentences.push(`${plural(total, nounSingular, noun)} ${total === 1 ? 'was' : 'were'} recorded in ${months[0].name}.`)
  } else {
    const peak = counts.indexOf(Math.max(...counts))
    let s = `${noun.charAt(0).toUpperCase()}${noun.slice(1)} peaked in ${months[peak].name} (${plural(counts[peak], nounSingular, noun)})`
    if (peak > 0) {
      const prev = counts[peak - 1]
      s += prev > 0 ? `, a ${pct(counts[peak] - prev, prev)}% increase from ${months[peak - 1].name}` : `, up from none in ${months[peak - 1].name}`
    }
    const last = counts.length - 1
    if (peak < last) {
      const end = counts[last]
      s += end === 0 ? `, before dropping to none in ${months[last].name}` : end >= counts[peak] * 0.75 ? `, before easing slightly in ${months[last].name}` : `, before falling to ${end} in ${months[last].name}`
    }
    sentences.push(`${s}.`)
  }
  if (split && split.length === 2) {
    const [a, b] = split
    if (a.value + b.value > 0) {
      const balance = a.value === b.value ? 'evenly split' : Math.abs(a.value - b.value) <= Math.max(1, (a.value + b.value) * 0.1) ? 'nearly balanced' : `mostly ${(a.value > b.value ? a.label : b.label).toLowerCase()}`
      sentences.push(`${split.title || 'The split'} was ${balance} for the period (${a.value} ${a.label.toLowerCase()} vs. ${b.value} ${b.label.toLowerCase()}).`)
    }
  }
  if (closing) sentences.push(closing)
  return sentences.join(' ')
}

function monthSections(months, headers, toRow, subtotalNoun) {
  return months.filter((m) => m.items.length).map((m) => ({
    title: m.label,
    range: m.range,
    headers,
    rows: m.items.map((item, i) => toRow(item, i)),
    subtotal: `Subtotal (${m.label}): ${m.items.length}${subtotalNoun ? ` ${subtotalNoun}` : ''}`,
  }))
}

const withBadges = (sections, columns) => sections.map((s) => ({ ...s, badgeColumns: columns }))

function totalLine(label, months) {
  const total = months.reduce((s, m) => s + m.items.length, 0)
  // Long ranges list only the months that have records, so the line stays readable.
  const shown = months.length > 6 ? months.filter((m) => m.items.length) : months
  const parts2 = months.length > 1 && shown.length > 0 && shown.length <= 12 ? ` (${shown.map((m) => `${m.short}: ${m.items.length}`).join('  |  ')})` : ''
  return `${label}: ${total}${parts2}`
}

const monthBars = (months) => months.map((m) => ({ label: m.short, value: m.items.length }))
const countBy = (items, key) => items.reduce((acc, i) => { const k = i[key] || 'unspecified'; acc[k] = (acc[k] || 0) + 1; return acc }, {})
const sexSplit = (items) => {
  const by = countBy(items, 'sex')
  return [{ label: 'Male', value: by.male || 0, color: C.blue }, { label: 'Female', value: by.female || 0, color: C.purple }]
}
const titleRange = (from, to) => {
  const a = parts(from), b = parts(to)
  if (!a || !b) return ''
  const s = (p) => MONTHS[p.m - 1].slice(0, 3).toUpperCase()
  return a.y === b.y ? (a.m === b.m ? `(${s(a)} ${a.y})` : `(${s(a)} - ${s(b)} ${a.y})`) : `(${s(a)} ${a.y} - ${s(b)} ${b.y})`
}

// ─── Data loading ─────────────────────────────────────────────────────────────

export async function fetchReport(kind, from, to, extra = {}) {
  const res = await api.get(`/reports/${kind}`, { params: { from, to, ...extra }, timeout: 60000 })
  return res.data.data
}

// ─── Employment (PESO) ────────────────────────────────────────────────────────

const STATUS_COLORS = { pending: C.orange, reviewed: C.blue, accepted: C.green, rejected: C.red, withdrawn: C.gray, approved: C.green }
const statusLabel = (s) => (s === 'rejected' ? 'Declined' : titleCase(s))

export function employmentSummaryReport(data) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const seekers = groupByMonth(data.jobseekers, from, to)
  const jobs = groupByMonth(data.jobs, from, to)
  const apps = groupByMonth(data.applications, from, to)
  const placed = data.applications.filter((a) => a.status === 'accepted')
  const byStatus = countBy(data.applications, 'status')
  const placementRate = pct(placed.length, data.applications.length)
  return {
    office: 'peso',
    title: 'Employment Services Summary',
    subtitle: `Summary report - ${period}`,
    dateRange: rangeLabel(from, to),
    stats: [
      { label: 'New jobseekers', value: data.jobseekers.length, icon: 'users', color: C.blue },
      { label: 'Job postings', value: data.jobs.length, icon: 'briefcase', color: C.purple },
      { label: 'Applications', value: data.applications.length, icon: 'file', color: C.orange },
      { label: 'Placements (hired)', value: placed.length, icon: 'check', color: C.green },
    ],
    charts: [
      { type: 'bar', title: 'Applications by month', data: monthBars(apps) },
      { type: 'donut', title: 'Application status', centerLabel: 'applications', data: Object.entries(byStatus).map(([k, v]) => ({ label: statusLabel(k), value: v, color: STATUS_COLORS[k] })) },
    ],
    insight: monthlyInsight({
      months: apps, noun: 'applications', nounSingular: 'application', period,
      closing: data.applications.length
        ? `${placementRate}% of applications filed in the period have been accepted so far. ${data.totals.pending_applications} application(s), ${data.totals.pending_jobs} job posting(s) and ${data.totals.pending_employers} employer verification(s) are awaiting action today.`
        : `${data.totals.open_jobs} job posting(s) are currently open for applicants.`,
    }),
    sections: [{
      title: 'MONTHLY BREAKDOWN',
      range: rangeLabel(from, to),
      headers: ['Month', 'New Jobseekers', 'Job Postings', 'Vacancies', 'Applications', 'Placements'],
      strongColumn: 0,
      rows: apps.map((m, i) => [
        `${m.name} ${m.key.slice(0, 4)}`, seekers[i].items.length, jobs[i].items.length,
        jobs[i].items.reduce((s, j) => s + (Number(j.vacancies) || 1), 0), m.items.length, m.items.filter((a) => a.status === 'accepted').length,
      ]),
      subtotal: `Totals: ${data.jobseekers.length} new jobseekers  |  ${data.jobs.length} job postings  |  ${data.jobs.reduce((s, j) => s + (Number(j.vacancies) || 1), 0)} vacancies  |  ${data.applications.length} applications  |  ${placed.length} placements`,
    }, {
      title: 'CURRENT WORKLOAD',
      range: `as of ${longDate(todayIso())}`,
      headers: ['Item', 'Count'],
      strongColumn: 0,
      rows: [
        ['Registered jobseekers (all time)', data.totals.all_jobseekers],
        ['Open job postings', data.totals.open_jobs],
        ['Job postings awaiting approval', data.totals.pending_jobs],
        ['Employers awaiting verification', data.totals.pending_employers],
        ['Applications awaiting review', data.totals.pending_applications],
      ],
    }],
  }
}

export function newJobseekersReport(data) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const months = groupByMonth(data.jobseekers, from, to)
  const split = sexSplit(data.jobseekers)
  return {
    office: 'peso',
    title: `Newly Registered Jobseekers ${titleRange(from, to)}`,
    dateRange: rangeLabel(from, to),
    groupedBy: 'Registration Month',
    stats: [
      { label: 'Registered this period', value: data.jobseekers.length, icon: 'users', color: C.blue },
      { label: 'Total jobseekers', value: data.totals.all_jobseekers, icon: 'users', color: C.green },
      { label: 'Male', value: split[0].value, icon: 'male', color: C.blue },
      { label: 'Female', value: split[1].value, icon: 'female', color: C.purple },
    ],
    charts: [
      { type: 'bar', title: 'New registrations by month', data: monthBars(months) },
      { type: 'donut', title: 'Jobseeker distribution by sex', centerLabel: 'jobseekers', data: split },
    ],
    insight: monthlyInsight({ months, noun: 'registrations', nounSingular: 'registration', period, split: Object.assign(split, { title: 'Registration by sex' }) }),
    sections: monthSections(months, ['#', 'Name', 'Age', 'Registered On', 'Contact Number', 'Address'], (j, i) => [
      i + 1, j.name, j.age ?? '—', longDate(j.date), j.phone || '—', [j.address, j.city].filter(Boolean).join(', ') || '—',
    ]),
    grandTotal: totalLine('Total jobseekers', months),
  }
}

export function jobPostingsReport(data) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const months = groupByMonth(data.jobs, from, to)
  const status = (j) => (Number(j.archived) ? 'archived' : j.approval_status)
  const byStatus = countBy(data.jobs.map((j) => ({ s: status(j) })), 's')
  const vacancies = data.jobs.reduce((s, j) => s + (Number(j.vacancies) || 1), 0)
  return {
    office: 'peso',
    title: `Job Postings ${titleRange(from, to)}`,
    dateRange: rangeLabel(from, to),
    groupedBy: 'Posting Month',
    stats: [
      { label: 'Job postings', value: data.jobs.length, icon: 'briefcase', color: C.blue },
      { label: 'Vacancies', value: vacancies, icon: 'users', color: C.purple },
      { label: 'Approved', value: byStatus.approved || 0, icon: 'check', color: C.green },
      { label: 'Pending approval', value: byStatus.pending || 0, icon: 'clock', color: C.orange },
    ],
    charts: [
      { type: 'bar', title: 'Job postings by month', data: monthBars(months) },
      { type: 'donut', title: 'Posting status', centerLabel: 'postings', data: Object.entries(byStatus).map(([k, v]) => ({ label: statusLabel(k), value: v, color: STATUS_COLORS[k] || C.gray })) },
    ],
    insight: monthlyInsight({
      months, noun: 'job postings', nounSingular: 'job posting', period,
      closing: data.jobs.length ? `The postings offer ${plural(vacancies, 'vacancy', 'vacancies')} from ${plural(new Set(data.jobs.map((j) => j.company_name)).size, 'employer')}.` : '',
    }),
    sections: withBadges(monthSections(months, ['#', 'Job Title', 'Employer', 'Posted On', 'Vacancies', 'Status'], (j, i) => [
      i + 1, j.title, j.company_name, longDate(j.date), j.vacancies || 1, statusLabel(status(j)),
    ]), [5]),
    grandTotal: totalLine('Total job postings', months),
  }
}

export function applicationsReport(data) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const months = groupByMonth(data.applications, from, to)
  const byStatus = countBy(data.applications, 'status')
  return {
    office: 'peso',
    title: `Job Applications and Placements ${titleRange(from, to)}`,
    dateRange: rangeLabel(from, to),
    groupedBy: 'Application Month',
    stats: [
      { label: 'Applications', value: data.applications.length, icon: 'file', color: C.blue },
      { label: 'Placements (accepted)', value: byStatus.accepted || 0, icon: 'check', color: C.green },
      { label: 'Pending / reviewed', value: (byStatus.pending || 0) + (byStatus.reviewed || 0), icon: 'clock', color: C.orange },
      { label: 'Declined', value: byStatus.rejected || 0, icon: 'file', color: C.red },
    ],
    charts: [
      { type: 'bar', title: 'Applications by month', data: monthBars(months) },
      { type: 'donut', title: 'Application status', centerLabel: 'applications', data: Object.entries(byStatus).map(([k, v]) => ({ label: statusLabel(k), value: v, color: STATUS_COLORS[k] })) },
    ],
    insight: monthlyInsight({
      months, noun: 'applications', nounSingular: 'application', period,
      closing: data.applications.length ? `${pct(byStatus.accepted || 0, data.applications.length)}% of these applications resulted in a placement.` : '',
    }),
    sections: withBadges(monthSections(months, ['#', 'Applicant', 'Job Applied For', 'Employer', 'Applied On', 'Status'], (a, i) => [
      i + 1, a.applicant, a.job_title, a.company_name, longDate(a.date), statusLabel(a.status),
    ]), [5]),
    grandTotal: totalLine('Total applications', months),
  }
}

// ─── Training (CLCDO) ─────────────────────────────────────────────────────────

export function trainingProgramsReport(data) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const months = groupByMonth(data.programs, from, to)
  // Programs that started before the range still ran during it; list them under the first month.
  const early = months.length ? data.programs.filter((p) => String(p.date).slice(0, 7) < months[0].key) : []
  if (early.length && months.length) months[0].items.unshift(...early)
  const enrolled = data.programs.reduce((s, p) => s + Number(p.enrolled || 0), 0)
  const completed = data.programs.reduce((s, p) => s + Number(p.completed || 0), 0)
  const byStatus = countBy(data.programs, 'status')
  const top = [...data.programs].sort((a, b) => b.enrolled - a.enrolled).slice(0, 6)
  return {
    office: 'clcdo',
    title: `Training Programs ${titleRange(from, to)}`,
    dateRange: rangeLabel(from, to),
    groupedBy: 'Start Month',
    stats: [
      { label: 'Programs', value: data.programs.length, icon: 'book', color: C.blue },
      { label: 'Participants enrolled', value: enrolled, icon: 'users', color: C.purple },
      { label: 'Completed', value: completed, icon: 'award', color: C.green },
      { label: 'Completion rate', value: `${pct(completed, enrolled)}%`, icon: 'percent', color: C.orange },
    ],
    charts: [
      { type: 'bar', title: 'Participants per program', data: top.map((p) => ({ label: p.program_name, value: Number(p.enrolled) })) },
      { type: 'donut', title: 'Program status', centerLabel: 'programs', data: Object.entries(byStatus).map(([k, v]) => ({ label: titleCase(k), value: v, color: { upcoming: C.orange, ongoing: C.blue, completed: C.green }[k] })) },
    ],
    insight: data.programs.length
      ? `${plural(data.programs.length, 'training program')} ran during ${period}, with ${plural(enrolled, 'participant')} enrolled and ${completed} completion(s) (${pct(completed, enrolled)}%). `
        + (top[0] ? `${top[0].program_name} had the most participants (${top[0].enrolled}). ` : '')
        + (data.programs.filter((p) => p.max_participants && Number(p.enrolled) >= Number(p.max_participants)).length ? `${data.programs.filter((p) => p.max_participants && Number(p.enrolled) >= Number(p.max_participants)).length} program(s) reached full capacity; consider additional batches.` : '')
      : `No training programs ran during ${period}.`,
    sections: withBadges(monthSections(months, ['#', 'Program', 'Location', 'Schedule', 'Enrolled / Capacity', 'Completed', 'Status'], (p, i) => [
      i + 1, p.program_name, p.location || '—', `${longDate(p.start_date)}${p.end_date ? ` - ${longDate(p.end_date)}` : ''}`,
      `${p.enrolled} / ${p.max_participants || '∞'}`, p.completed, titleCase(p.status),
    ], 'program(s)'), [6]),
    grandTotal: `Total programs: ${data.programs.length}  |  Participants: ${enrolled}  |  Completed: ${completed}`,
  }
}

export function trainingCompletionsReport(data) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const months = groupByMonth(data.completions, from, to)
  const split = sexSplit(data.completions)
  const withCert = data.completions.filter((c) => Number(c.has_certificate)).length
  return {
    office: 'clcdo',
    title: `Training Completions ${titleRange(from, to)}`,
    dateRange: rangeLabel(from, to),
    groupedBy: 'Completion Month',
    stats: [
      { label: 'Completions', value: data.completions.length, icon: 'award', color: C.green },
      { label: 'Graduates (unique)', value: new Set(data.completions.map((c) => c.participant)).size, icon: 'users', color: C.blue },
      { label: 'Programs', value: new Set(data.completions.map((c) => c.program_name)).size, icon: 'book', color: C.purple },
      { label: 'With certificate', value: withCert, icon: 'file', color: C.orange },
    ],
    charts: [
      { type: 'bar', title: 'Completions by month', data: monthBars(months) },
      { type: 'donut', title: 'Graduates by sex', centerLabel: 'completions', data: split },
    ],
    insight: monthlyInsight({ months, noun: 'completions', nounSingular: 'completion', period, split: Object.assign(split, { title: 'Completion by sex' }), closing: data.completions.length && withCert < data.completions.length ? `${data.completions.length - withCert} completion(s) have no certificate on file yet.` : '' }),
    sections: withBadges(monthSections(months, ['#', 'Participant', 'Training Program', 'Completed On', 'Certificate'], (c, i) => [
      i + 1, c.participant, c.program_name, longDate(c.date), Number(c.has_certificate) ? 'On file' : 'Not yet uploaded',
    ]), [4]),
    grandTotal: totalLine('Total completions', months),
  }
}

// ─── System logs (admin) ──────────────────────────────────────────────────────

export function systemLogsReport(data, filters = {}) {
  const { from, to } = data.range
  const period = periodLabel(from, to)
  const months = groupByMonth(data.logs, from, to)
  const byRole = countBy(data.logs, 'role')
  const users = new Set(data.logs.map((l) => l.name).filter(Boolean)).size
  const topRole = Object.entries(byRole).sort((a, b) => b[1] - a[1])[0]
  const roleColors = { admin: C.navy, peso: C.blue, clcdo: C.green, employer: C.orange, jobseeker: C.purple, unspecified: C.gray }
  return {
    office: 'admin',
    title: 'System Activity Logs',
    subtitle: [filters.role && `Role: ${titleCase(filters.role)}`, filters.search && `Search: "${filters.search}"`].filter(Boolean).join('  ·  ') || `Audit report - ${period}`,
    dateRange: rangeLabel(from, to),
    groupedBy: 'Activity Month',
    stats: [
      { label: 'Log entries', value: data.logs.length, icon: 'activity', color: C.blue },
      { label: 'Active users', value: users, icon: 'users', color: C.green },
      { label: 'Most active role', value: topRole ? titleCase(topRole[0] === 'unspecified' ? 'system' : topRole[0]) : '—', icon: 'shield', color: C.purple },
      { label: 'Months covered', value: months.length, icon: 'calendar', color: C.orange },
    ],
    charts: [
      { type: 'bar', title: 'Activity by month', data: monthBars(months) },
      { type: 'donut', title: 'Activity by role', centerLabel: 'entries', data: Object.entries(byRole).map(([k, v]) => ({ label: k === 'unspecified' ? 'System' : titleCase(k), value: v, color: roleColors[k] })) },
    ],
    insight: monthlyInsight({ months, noun: 'logged activities', nounSingular: 'logged activity', period, closing: data.logs.length >= 5000 ? 'Only the first 5,000 entries are included; narrow the date range for a complete listing.' : '' }),
    sections: withBadges(monthSections(months, ['#', 'User', 'Role', 'Activity', 'Date and Time'], (l, i) => [
      i + 1, l.name || 'System', l.role ? titleCase(l.role) : 'System', l.action, dateTime(l.date),
    ]), [3]),
    grandTotal: totalLine('Total log entries', months),
  }
}
