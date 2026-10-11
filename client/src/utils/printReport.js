/**
 * Official printable report layout used by every print feature, styled with the
 * EmploySmart design system (brand blue palette, Sora + Plus Jakarta Sans, rounded
 * cards, pill badges).
 *
 * Layout (A4 portrait): government letterhead with the municipality seal (left) and
 * the office seal (right, PESO or CLCDO) configured in Admin → Municipality Settings,
 * report metadata, title, summary cards, charts, an insight box, month-grouped tables
 * with subtotals and a grand total, a "Prepared by" signature block, and a footer
 * with the LGU contacts, print date/time and "Page X of Y" on every page.
 *
 * printReport(options) or printReport(async () => options). The window opens
 * immediately (so pop-up blockers allow it) and is filled once the data is ready.
 */
import { loadMunicipality } from '../hooks/useMunicipality'

// System palette (see tailwind.config.js / index.css).
export const REPORT_COLORS = {
  blue: '#1d4ed8', blueLight: '#3b82f6', navy: '#1e3a8a', indigo: '#4f46e5',
  green: '#16a34a', orange: '#d97706', red: '#dc2626', purple: '#7c3aed', teal: '#0f766e', gray: '#9ca3af',
}
const C = REPORT_COLORS
const PALETTE = [C.blue, C.orange, C.green, C.red, C.purple, C.teal, C.blueLight, C.gray]
// Card tints mirror the dashboard StatCard tiles (bg-*-50 / text-*-600).
const TINTS = {
  [C.blue]: '#eff6ff', [C.green]: '#f0fdf4', [C.orange]: '#fffbeb', [C.red]: '#fef2f2', [C.purple]: '#f5f3ff',
  [C.teal]: '#f0fdfa', [C.navy]: '#eef2ff', [C.indigo]: '#eef2ff', [C.gray]: '#f9fafb', [C.blueLight]: '#eff6ff',
}
const CARD_COLORS = [C.blue, C.green, C.orange, C.purple]

// Feather-style icons used on the summary cards.
const ICONS = {
  users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  briefcase: '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
  file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  check: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>',
  clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
  book: '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
  award: '<circle cx="12" cy="8" r="7"/><path d="M8.21 13.89 7 23l5-3 5 3-1.21-9.12"/>',
  activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
  percent: '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
  male: '<circle cx="10" cy="14" r="6"/><path d="M14.5 9.5 21 3"/><path d="M15 3h6v6"/>',
  female: '<circle cx="12" cy="9" r="6"/><path d="M12 15v7"/><path d="M9 19h6"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
  calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
}
const DEFAULT_ICONS = ['users', 'briefcase', 'file', 'check']

const OFFICES = {
  peso: { key: 'peso', short: 'PESO', name: 'Public Employment Service Office', position: 'PESO Officer' },
  clcdo: { key: 'clcdo', short: 'CLCDO', name: 'City Livelihood and Community Development Office', position: 'CLCDO Officer' },
  admin: { key: 'admin', short: 'ES', name: 'EmploySmart System Administration', position: 'System Administrator' },
  employer: { key: 'employer', short: 'ES', name: 'Employer', position: 'Employer Representative' },
}
const SYSTEM_NAME = 'EmploySmart Job Matching and Livelihood Training System'

// Status words shown as pill badges in tables (same colours as the app's badges).
const BADGES = {
  green: ['accepted', 'approved', 'completed', 'on file', 'verified', 'placed', 'login', 'register'],
  blue: ['reviewed', 'ongoing', 'enrolled', 'in progress'],
  yellow: ['pending', 'upcoming', 'pending approval', 'not yet uploaded'],
  red: ['declined', 'rejected', 'failed_login', 'dropped', 'separated'],
  gray: ['archived', 'withdrawn', 'closed', 'expired'],
}
const BADGE_STYLE = { green: ['#dcfce7', '#15803d'], blue: ['#dbeafe', '#1d4ed8'], yellow: ['#fef9c3', '#a16207'], red: ['#fee2e2', '#b91c1c'], gray: ['#f3f4f6', '#4b5563'], purple: ['#ede9fe', '#6d28d9'] }

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

/** CSS string literal for @page margin-box content. */
function cssString(value) {
  return `"${String(value ?? '').replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\A ')}"`
}

export function absoluteUrl(path) {
  if (!path) return ''
  if (/^(https?:|data:)/i.test(path)) return path
  return `${window.location.origin}/${String(path).replace(/^\/+/, '')}`
}

function initials(text, max = 3) {
  const words = String(text || '').replace(/\b(city|municipality|of|the|and)\b/gi, ' ').split(/\s+/).filter(Boolean)
  return (words.map((w) => w[0]).join('').slice(0, max) || 'LGU').toUpperCase()
}

/** Placeholder seal (brand colours) used until a seal image is uploaded. */
export function placeholderSealSvg(label, color = C.blue) {
  const text = escapeHtml(label)
  const size = label.length > 3 ? 15 : 20
  const id = `g${Math.random().toString(36).slice(2, 8)}`
  return `<svg viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="${text} seal" xmlns="http://www.w3.org/2000/svg">
    <defs><linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${color}"/><stop offset="1" stop-color="${C.indigo}"/></linearGradient></defs>
    <circle cx="50" cy="50" r="47" fill="#fff" stroke="url(#${id})" stroke-width="5"/>
    <circle cx="50" cy="50" r="38" fill="#eff6ff" stroke="${color}" stroke-width="1.2"/>
    <path d="M50 21 L69 39 L63 39 L63 56 L37 56 L37 39 L31 39 Z" fill="url(#${id})"/>
    <text x="50" y="75" text-anchor="middle" font-size="${size}" font-weight="700" fill="${C.navy}" font-family="Sora, Arial, sans-serif">${text}</text>
  </svg>`
}

/** Seal image + caption configured in Municipality Settings for "lgu", "peso" or "clcdo". */
export function sealFor(config, which) {
  const image = {
    lgu: config.landing_seal_lgu_image || config.landing_logo_image,
    peso: config.landing_seal_peso_image || config.landing_office_image,
    clcdo: config.landing_seal_clcdo_image || config.landing_office_image,
  }[which] || ''
  const caption = config[`report_seal_${which}_caption`] || ''
  return { image, caption }
}

function seal({ image, caption }, label, color) {
  const body = image
    ? `<img src="${escapeHtml(absoluteUrl(image))}" alt="${escapeHtml(caption || label)} seal">`
    : placeholderSealSvg(label, color)
  return `<div class="seal">${body}${caption ? `<div class="seal-caption">${escapeHtml(caption)}</div>` : ''}</div>`
}

function niceStep(max) {
  if (max <= 5) return 1
  const raw = max / 5
  const pow = 10 ** Math.floor(Math.log10(raw))
  return [1, 2, 5, 10].map((m) => m * pow).find((s) => s >= raw)
}

function barChartSvg(data) {
  const width = 520, height = 230, left = 8, right = 34, top = 24, bottom = 30
  const max = Math.max(1, ...data.map((d) => Number(d.value) || 0))
  const step = niceStep(max)
  const axisMax = Math.ceil(max / step) * step
  const plotW = width - left - right, plotH = height - top - bottom
  const slot = plotW / Math.max(1, data.length)
  const barW = Math.min(64, slot * 0.58)
  const y = (v) => top + plotH - (v / axisMax) * plotH
  const id = `bar${Math.random().toString(36).slice(2, 8)}`
  let grid = ''
  for (let v = 0; v <= axisMax; v += step) {
    grid += `<line x1="${left}" x2="${width - right}" y1="${y(v)}" y2="${y(v)}" stroke="${v === 0 ? '#d1d5db' : '#f1f5f9'}" stroke-width="1" ${v === 0 ? '' : 'stroke-dasharray="4 4"'}/>`
      + `<text x="${width - right + 8}" y="${y(v) + 4}" font-size="11" fill="#9ca3af">${v}</text>`
  }
  const bars = data.map((d, i) => {
    const v = Number(d.value) || 0
    const x = left + slot * i + (slot - barW) / 2
    const h = Math.max(0, top + plotH - y(v))
    const r = Math.min(6, barW / 2, h)
    const label = String(d.label ?? '')
    // Labels are shortened to fit the bar's slot so neighbouring labels never overlap.
    const maxChars = Math.max(3, Math.floor(slot / 7.5))
    const short = label.length > maxChars ? `${label.slice(0, maxChars - 1)}…` : label
    const shape = h > 0
      ? `<path d="M${x} ${y(v) + h} V${y(v) + r} Q${x} ${y(v)} ${x + r} ${y(v)} H${x + barW - r} Q${x + barW} ${y(v)} ${x + barW} ${y(v) + r} V${y(v) + h} Z" fill="${d.color || `url(#${id})`}"/>`
      : ''
    return shape
      + `<text x="${x + barW / 2}" y="${y(v) - 7}" text-anchor="middle" font-size="11" font-weight="700" fill="${C.navy}">${v}</text>`
      + `<text x="${x + barW / 2}" y="${height - 10}" text-anchor="middle" font-size="11" fill="#6b7280">${escapeHtml(short)}</text>`
  }).join('')
  return `<svg viewBox="0 0 ${width} ${height}" width="100%" role="img"><defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${C.blueLight}"/><stop offset="1" stop-color="${C.indigo}"/></linearGradient></defs>${grid}${bars}</svg>`
}

function donutChartSvg(data, centerLabel) {
  const total = data.reduce((s, d) => s + (Number(d.value) || 0), 0)
  // Segments are drawn as ring paths (outer arc, inner arc) so no slivers appear at the joins.
  const R = 86, r = 58
  const point = (radius, angle) => [100 + radius * Math.sin(angle), 100 - radius * Math.cos(angle)]
  let angle = 0
  const colorOf = (d, i) => d.color || PALETTE[i % PALETTE.length]
  const nonZero = data.filter((d) => Number(d.value) > 0)
  const arcs = total === 0
    ? `<circle cx="100" cy="100" r="${(R + r) / 2}" fill="none" stroke="#e5e7eb" stroke-width="${R - r}"/>`
    : nonZero.length === 1
      ? `<circle cx="100" cy="100" r="${(R + r) / 2}" fill="none" stroke="${colorOf(nonZero[0], data.indexOf(nonZero[0]))}" stroke-width="${R - r}"/>`
      : data.map((d, i) => {
        const value = Number(d.value) || 0
        if (!value) return ''
        const start = angle
        angle += (value / total) * 2 * Math.PI
        const large = angle - start > Math.PI ? 1 : 0
        const [x1, y1] = point(R, start), [x2, y2] = point(R, angle), [x3, y3] = point(r, angle), [x4, y4] = point(r, start)
        return `<path d="M${x1} ${y1} A${R} ${R} 0 ${large} 1 ${x2} ${y2} L${x3} ${y3} A${r} ${r} 0 ${large} 0 ${x4} ${y4} Z" fill="${colorOf(d, i)}" stroke="#fff" stroke-width="2"/>`
      }).join('')
  const legend = data.map((d, i) => {
    const p = total ? Math.round(((Number(d.value) || 0) / total) * 100) : 0
    return `<div class="legend-item"><span class="swatch" style="background:${colorOf(d, i)}"></span><span class="legend-label">${escapeHtml(d.label)}</span><span class="legend-value">${escapeHtml(d.value)} · ${p}%</span></div>`
  }).join('')
  return `<div class="donut"><svg viewBox="0 0 200 200" width="170" height="170" role="img">${arcs}
      <text x="100" y="102" text-anchor="middle" font-size="28" font-weight="700" fill="${C.navy}" font-family="Sora, Arial, sans-serif">${total}</text>
      <text x="100" y="122" text-anchor="middle" font-size="11" fill="#6b7280">${escapeHtml(centerLabel || 'total')}</text>
    </svg><div class="legend">${legend}</div></div>`
}

function chartBlock(chart) {
  const body = chart.type === 'donut' ? donutChartSvg(chart.data || [], chart.centerLabel) : barChartSvg(chart.data || [])
  return `<div class="panel chart"><h3>${escapeHtml(chart.title)}</h3>${body}</div>`
}

function badge(text) {
  const t = String(text ?? '').trim()
  const key = t.toLowerCase()
  const tone = Object.keys(BADGES).find((k) => BADGES[k].includes(key))
  if (!tone) return escapeHtml(t)
  const [bg, fg] = BADGE_STYLE[tone]
  return `<span class="badge" style="background:${bg};color:${fg}">${escapeHtml(t)}</span>`
}

function tableBlock(section) {
  const headers = section.headers || []
  const strong = section.strongColumn ?? 1
  const badges = new Set(section.badgeColumns || [])
  const numbered = headers[0] === '#'
  const head = headers.map((h, i) => `<th class="${i === 0 && numbered ? 'num' : ''}">${escapeHtml(h)}</th>`).join('')
  const body = (section.rows || []).map((row) => `<tr>${row.map((cell, i) => {
    const cls = [i === strong ? 'strong' : '', i === 0 && numbered ? 'num' : ''].filter(Boolean).join(' ')
    return `<td${cls ? ` class="${cls}"` : ''}>${badges.has(i) ? badge(cell) : escapeHtml(cell)}</td>`
  }).join('')}</tr>`).join('')
  const empty = `<tr><td colspan="${headers.length || 1}" class="empty">${escapeHtml(section.emptyMessage || 'No records for this period.')}</td></tr>`
  return `<section class="group">
    ${section.title ? `<div class="group-title"><span class="group-name">${escapeHtml(section.title)}</span>${section.range ? `<span class="group-range">${escapeHtml(section.range)}</span>` : ''}</div>` : ''}
    <table><thead><tr>${head}</tr></thead><tbody>${body || empty}</tbody></table>
    ${section.subtotal ? `<div class="subtotal"><span>${escapeHtml(section.subtotal)}</span></div>` : ''}
  </section>`
}

function currentUser() {
  try { return JSON.parse(localStorage.getItem('user') || 'null') || {} } catch { return {} }
}

export function buildReportDocument(options, config) {
  const user = currentUser()
  const office = OFFICES[options.office || user.role] || OFFICES.admin
  const now = new Date()
  const datePrinted = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  const timePrinted = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })

  const genericName = !config.municipality_name || /^employsmart municipality$/i.test(config.municipality_name)
  const lguName = genericName ? 'EmploySmart' : config.municipality_name
  const lguSub = config.municipality_province || config.municipality_region || ''
  const address = config.contact_address || [genericName ? '' : config.municipality_name, config.municipality_province, config.municipality_region].filter(Boolean).join(', ')
  const contactLine = [config.contact_phone, config.contact_email].filter(Boolean).join('  ·  ')
  const footerLeft = [lguName, [config.municipality_province, config.municipality_region].filter(Boolean).join(', '), [config.contact_phone && `Contact: ${config.contact_phone}`, config.contact_email && `Email: ${config.contact_email}`].filter(Boolean).join('  |  ')].filter(Boolean).join('\n')
  const footerRight = `Date Printed: ${datePrinted}     Time Printed: ${timePrinted}\nPAGE_PLACEHOLDER\nGenerated by: ${SYSTEM_NAME}`

  const leftSeal = seal(sealFor(config, 'lgu'), initials(lguName), C.blue)
  const rightSeal = office.key === 'peso' || office.key === 'clcdo'
    ? seal(sealFor(config, office.key), office.short, C.navy)
    : seal({ image: '', caption: '' }, office.short, C.indigo)

  const meta = [
    options.dateRange && ['Date Range', options.dateRange],
    options.groupedBy && ['Grouped By', options.groupedBy],
    ['Generated On', `${datePrinted} at ${timePrinted}`],
  ].filter(Boolean).map(([k, v]) => `<div><span>${escapeHtml(k)}:</span> ${escapeHtml(v)}</div>`).join('')

  // Legacy single-table callers.
  const sections = options.sections || (options.tableHeaders ? [{ headers: options.tableHeaders, rows: options.tableRows || [], strongColumn: 0 }] : [])
  const stats = (options.stats || []).map((s, i) => {
    const color = s.color || CARD_COLORS[i % CARD_COLORS.length]
    const icon = ICONS[s.icon] || ICONS[DEFAULT_ICONS[i % DEFAULT_ICONS.length]]
    return `<div class="card"><div class="card-icon" style="background:${TINTS[color] || '#eff6ff'};color:${color}"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${icon}</svg></div><div><div class="card-value">${escapeHtml(s.value)}</div><div class="card-label">${escapeHtml(s.label)}</div>${s.note ? `<div class="card-note">${escapeHtml(s.note)}</div>` : ''}</div></div>`
  }).join('')
  const charts = (options.charts || []).map(chartBlock).join('')
  const insight = options.insight || options.summary

  const preparedName = [user.first_name, user.last_name].filter(Boolean).join(' ') || '________________________'
  const preparedPosition = options.preparedPosition || office.position
  const footerCss = 'font: 8pt "Plus Jakarta Sans", Arial, sans-serif; color: #6b7280; vertical-align: top; padding-top: 3mm; border-top: 1.2pt solid #1d4ed8;'

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${escapeHtml(options.title)}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    @page {
      size: A4 portrait;
      margin: 14mm 14mm 30mm;
      @bottom-left { content: ${cssString(footerLeft)}; white-space: pre; ${footerCss} }
      @bottom-right { content: ${cssString(footerRight).replace('PAGE_PLACEHOLDER', '" "Page " counter(page) " of " counter(pages) "')}; white-space: pre; text-align: right; ${footerCss} }
    }
    * { box-sizing: border-box; }
    body { margin: 0; color: #1f2937; font-family: "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif; font-size: 10.5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .display { font-family: Sora, "Plus Jakarta Sans", Arial, sans-serif; }

    /* Letterhead */
    .letterhead { display: grid; grid-template-columns: 112px 1fr 112px; align-items: center; }
    .seal { text-align: center; }
    .seal img, .seal svg { width: 92px; height: 92px; object-fit: contain; display: block; margin: 0 auto; }
    .seal-caption { margin-top: 4px; font-size: 8.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; line-height: 1.25; }
    .lgu { text-align: center; }
    .lgu .republic { font-size: 10.5px; font-weight: 700; letter-spacing: .18em; color: ${C.blue}; text-transform: uppercase; }
    .lgu .name { margin: 3px 0 2px; font-family: Sora, Arial, sans-serif; font-size: 23px; font-weight: 800; color: ${C.navy}; letter-spacing: -.01em; }
    .lgu .sub { font-size: 13px; color: #6b7280; font-weight: 500; }
    .rule { height: 4px; border-radius: 99px; margin: 10px 0 0; background: linear-gradient(90deg, ${C.blue}, ${C.indigo}); }
    .contact { text-align: center; color: #374151; font-size: 10.5px; line-height: 1.65; padding: 8px 0 10px; border-bottom: 1px solid #e5e7eb; }
    .contact .office { display: inline-block; margin-top: 3px; padding: 2px 10px; border-radius: 99px; background: #eff6ff; color: ${C.blue}; font-weight: 700; font-size: 9.5px; letter-spacing: .04em; }

    /* Title block */
    .meta { text-align: right; color: #6b7280; font-size: 9.5px; line-height: 1.7; margin: 10px 0 2px; }
    .meta span { font-weight: 600; color: #4b5563; }
    h1 { text-align: center; margin: 6px 0 4px; font-family: Sora, Arial, sans-serif; font-size: 19px; font-weight: 800; letter-spacing: .01em; text-transform: uppercase; color: ${C.navy}; }
    .subtitle { text-align: center; margin: 0 0 16px; }
    .subtitle span { display: inline-block; padding: 3px 12px; border-radius: 99px; background: #eff6ff; color: ${C.blue}; font-size: 10.5px; font-weight: 600; }

    /* Summary cards (dashboard StatCard style) */
    .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 12px 0 18px; }
    .card { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; break-inside: avoid; }
    .card-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .card-value { font-family: Sora, Arial, sans-serif; font-size: 20px; font-weight: 700; color: #111827; line-height: 1.1; }
    .card-label { color: #6b7280; font-size: 9.5px; margin-top: 2px; }
    .card-note { color: #9ca3af; font-size: 8.5px; }

    /* Charts */
    .charts { display: grid; grid-template-columns: repeat(${Math.min(2, Math.max(1, (options.charts || []).length))}, 1fr); gap: 12px; margin-bottom: 16px; }
    .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; break-inside: avoid; }
    .panel h3 { font-family: Sora, Arial, sans-serif; font-size: 12px; font-weight: 700; margin: 0 0 8px; color: #111827; }
    .panel.chart { display: flex; flex-direction: column; }
    .panel.chart > svg, .panel.chart > .donut { margin: auto 0; }
    .donut { display: flex; align-items: center; justify-content: center; gap: 14px; }
    .legend { min-width: 120px; }
    .legend-item { display: flex; align-items: center; gap: 6px; font-size: 10px; margin: 5px 0; }
    .swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .legend-label { color: #374151; flex: 1; }
    .legend-value { color: #6b7280; font-weight: 600; white-space: nowrap; }

    /* Insight (alert-info style) */
    .insight { display: flex; gap: 10px; border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 12px; padding: 12px 14px; margin: 0 0 18px; break-inside: avoid; }
    .insight-icon { width: 26px; height: 26px; border-radius: 8px; background: ${C.blue}; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .insight h4 { margin: 0 0 3px; font-size: 9.5px; font-weight: 800; letter-spacing: .12em; color: ${C.blue}; }
    .insight p { margin: 0; font-size: 10.5px; line-height: 1.6; color: #1e40af; }

    /* Tables (system table style) */
    .group { margin-bottom: 14px; }
    .group-title { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; background: linear-gradient(90deg, ${C.blue}, ${C.indigo}); color: #fff; padding: 7px 12px; border-radius: 10px 10px 0 0; break-after: avoid; }
    .group-name { font-family: Sora, Arial, sans-serif; font-weight: 700; font-size: 11px; letter-spacing: .03em; }
    .group-range { font-size: 9.5px; opacity: .9; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 10px 10px; overflow: hidden; }
    .group > table:first-child { border-top: 1px solid #e5e7eb; border-radius: 10px; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    th { background: #f9fafb; color: #4b5563; text-align: left; font-size: 8.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; padding: 8px 10px; border-bottom: 2px solid ${C.blue}; }
    td { padding: 7px 10px; font-size: 10px; border-top: 1px solid #f1f5f9; vertical-align: top; color: #374151; }
    tbody tr:first-child td { border-top: 0; }
    tbody tr:nth-child(even) td { background: #fafbfc; }
    td.strong { font-weight: 700; color: ${C.navy}; }
    th.num, td.num { width: 34px; color: #9ca3af; }
    td.empty { text-align: center; color: #9ca3af; font-style: italic; padding: 14px; }
    .badge { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 9px; font-weight: 700; white-space: nowrap; }
    .subtotal { text-align: right; margin-top: 6px; }
    .subtotal span { display: inline-block; padding: 2px 10px; border-radius: 99px; background: #f3f4f6; color: #4b5563; font-size: 9.5px; font-weight: 700; }
    .grand-total { margin: 12px 0 0; padding: 10px 14px; border-radius: 12px; background: linear-gradient(90deg, #eff6ff, #eef2ff); border: 1px solid #c7d2fe; text-align: right; font-family: Sora, Arial, sans-serif; font-weight: 700; font-size: 12px; color: ${C.navy}; break-inside: avoid; }

    /* Signature */
    .signatures { margin-top: 30px; break-inside: avoid; }
    .signatures .label { font-size: 11px; color: #4b5563; margin-bottom: 34px; }
    .signature { width: 280px; text-align: center; }
    .signature .line { border-top: 1.2px solid #111827; margin-bottom: 6px; }
    .signature .name { font-family: Sora, Arial, sans-serif; font-weight: 700; font-size: 12.5px; color: #111827; }
    .signature .position { color: ${C.blue}; font-size: 10.5px; font-weight: 600; margin-top: 2px; }
    .signature .hint { color: #9ca3af; font-size: 8.5px; font-style: italic; margin-top: 2px; }

    .screen-footer { display: none; }
    @media screen {
      html { background: #f3f4f6; }
      body { max-width: 860px; margin: 24px auto; padding: 32px 40px; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
      .screen-footer { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 8px; border-top: 1.5px solid ${C.blue}; color: #6b7280; font-size: 9px; white-space: pre-line; }
      .screen-footer div:last-child { text-align: right; }
    }
  </style></head><body>
    <header>
      <div class="letterhead">
        ${leftSeal}
        <div class="lgu">
          <div class="republic">Republic of the Philippines</div>
          <div class="name">${escapeHtml(lguName)}</div>
          ${lguSub ? `<div class="sub">${escapeHtml(lguSub)}</div>` : ''}
        </div>
        ${rightSeal}
      </div>
      <div class="rule"></div>
      <div class="contact">${address ? `<div>${escapeHtml(address)}</div>` : ''}${contactLine ? `<div>${escapeHtml(contactLine)}</div>` : ''}<div class="office">${escapeHtml(office.name)}</div></div>
    </header>
    <div class="meta">${meta}</div>
    <h1>${escapeHtml(options.title)}</h1>
    ${options.subtitle ? `<p class="subtitle"><span>${escapeHtml(options.subtitle)}</span></p>` : '<div style="height:10px"></div>'}
    ${stats ? `<div class="cards">${stats}</div>` : ''}
    ${charts ? `<div class="charts">${charts}</div>` : ''}
    ${insight ? `<div class="insight"><div class="insight-icon"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div><div><h4>INSIGHT</h4><p>${escapeHtml(insight)}</p></div></div>` : ''}
    ${sections.map(tableBlock).join('')}
    ${options.grandTotal ? `<div class="grand-total">${escapeHtml(options.grandTotal)}</div>` : ''}
    <div class="signatures">
      <div class="label">Prepared by:</div>
      <div class="signature"><div class="line"></div><div class="name">${escapeHtml(preparedName)}</div><div class="position">${escapeHtml(preparedPosition)}</div><div class="hint">Signature over printed name</div></div>
    </div>
    <div class="screen-footer"><div>${escapeHtml(footerLeft)}</div><div>${escapeHtml(footerRight.replace('PAGE_PLACEHOLDER', 'Page numbers appear when printed'))}</div></div>
  </body></html>`
}

function waitForAssets(doc, timeout = 3500) {
  const images = Array.from(doc.images || [])
  const imagesReady = Promise.all(images.map((img) => (img.complete ? Promise.resolve() : new Promise((resolve) => { img.onload = resolve; img.onerror = resolve }))))
  const fontsReady = doc.fonts?.ready || Promise.resolve()
  return Promise.race([Promise.all([imagesReady, fontsReady]), new Promise((resolve) => setTimeout(resolve, timeout))])
}

export function printReport(input) {
  const printWindow = window.open('', '_blank', 'width=1000,height=900')
  if (!printWindow) return false
  printWindow.document.write('<!doctype html><title>Preparing report…</title><p style="font-family:Arial,sans-serif;padding:24px;color:#1d4ed8">Preparing report…</p>')
  printWindow.document.close()

  ;(async () => {
    try {
      // Settings are read after the input resolves, so anything saved by it is included.
      const options = await (typeof input === 'function' ? input() : input)
      const config = await loadMunicipality({ fresh: true })
      printWindow.document.open()
      printWindow.document.write(buildReportDocument(options, config || {}))
      printWindow.document.close()
      await waitForAssets(printWindow.document)
      printWindow.focus()
      printWindow.print()
    } catch (error) {
      printWindow.document.open()
      printWindow.document.write(`<!doctype html><title>Report error</title><p style="font-family:Arial,sans-serif;padding:24px;color:#b91c1c">The report could not be prepared: ${escapeHtml(error?.response?.data?.message || error?.message || 'unknown error')}. Please close this window and try again.</p>`)
      printWindow.document.close()
    }
  })()
  return true
}
