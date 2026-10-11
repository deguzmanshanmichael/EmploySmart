/**
 * Official printable report layout used by every print feature.
 *
 * Layout (A4 portrait): government letterhead with the LGU and office seals, report
 * metadata, title, summary cards, charts, an insight box, month-grouped tables with
 * subtotals and a grand total, a "Prepared by" signature block, and a footer with
 * the LGU contact details, print date/time and "Page X of Y" on every page.
 *
 * printReport(options) or printReport(async () => options). The window opens
 * immediately (so pop-up blockers allow it) and is filled once the data is ready.
 */
import { loadMunicipality } from '../hooks/useMunicipality'

export const REPORT_COLORS = { green: '#1f9d6b', blue: '#3787d8', orange: '#f0a02c', red: '#d9572f', purple: '#7c5cc4', teal: '#14808a', gray: '#8a94a6', navy: '#17365d' }
const PALETTE = [REPORT_COLORS.blue, REPORT_COLORS.orange, REPORT_COLORS.red, REPORT_COLORS.green, REPORT_COLORS.purple, REPORT_COLORS.teal, REPORT_COLORS.gray]
const CARD_COLORS = [REPORT_COLORS.green, REPORT_COLORS.blue, REPORT_COLORS.orange, REPORT_COLORS.red]

const OFFICES = {
  peso: { short: 'PESO', name: 'Public Employment Service Office', position: 'PESO Officer' },
  clcdo: { short: 'CLCDO', name: 'City Livelihood and Community Development Office', position: 'CLCDO Officer' },
  admin: { short: 'ADMIN', name: 'EmploySmart System Administration', position: 'System Administrator' },
  employer: { short: 'EMP', name: 'Employer', position: 'Employer Representative' },
}
const SYSTEM_NAME = 'EmploySmart Job Matching and Livelihood Training System'

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

function absoluteUrl(path) {
  if (!path) return ''
  if (/^(https?:|data:)/i.test(path)) return path
  return `${window.location.origin}/${String(path).replace(/^\/+/, '')}`
}

function initials(text, max = 3) {
  const words = String(text || '').replace(/\b(city|municipality|of|the|and)\b/gi, ' ').split(/\s+/).filter(Boolean)
  return (words.map((w) => w[0]).join('').slice(0, max) || 'LGU').toUpperCase()
}

/** Placeholder seal drawn when no logo has been uploaded in Municipality Settings. */
function sealSvg(label, color) {
  const text = escapeHtml(label)
  const size = label.length > 3 ? 15 : 19
  return `<svg viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="${text} seal">
    <circle cx="50" cy="50" r="47" fill="#fff" stroke="${color}" stroke-width="4"/>
    <circle cx="50" cy="50" r="39" fill="none" stroke="${color}" stroke-width="1.5"/>
    <path d="M50 22 L68 40 L62 40 L62 58 L38 58 L38 40 L32 40 Z" fill="#b8892a"/>
    <text x="50" y="76" text-anchor="middle" font-size="${size}" font-weight="700" fill="${color}" font-family="Arial, sans-serif">${text}</text>
  </svg>`
}

function seal(imageUrl, label, color) {
  return imageUrl
    ? `<div class="seal"><img src="${escapeHtml(absoluteUrl(imageUrl))}" alt="${escapeHtml(label)} logo"></div>`
    : `<div class="seal">${sealSvg(label, color)}</div>`
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
  const barW = Math.min(90, slot * 0.6)
  const y = (v) => top + plotH - (v / axisMax) * plotH
  let grid = ''
  for (let v = 0; v <= axisMax; v += step) {
    grid += `<line x1="${left}" x2="${width - right}" y1="${y(v)}" y2="${y(v)}" stroke="${v === 0 ? '#9aa3b2' : '#e3e7ee'}" stroke-width="1"/>`
      + `<text x="${width - right + 8}" y="${y(v) + 4}" font-size="11" fill="#6b7280">${v}</text>`
  }
  const bars = data.map((d, i) => {
    const v = Number(d.value) || 0
    const x = left + slot * i + (slot - barW) / 2
    const label = String(d.label ?? '')
    // Labels are shortened to fit the bar's slot so neighbouring labels never overlap.
    const maxChars = Math.max(3, Math.floor(slot / 7.5))
    const short = label.length > maxChars ? `${label.slice(0, maxChars - 1)}…` : label
    return `<rect x="${x}" y="${y(v)}" width="${barW}" height="${Math.max(0, top + plotH - y(v))}" fill="${d.color || REPORT_COLORS.blue}"/>`
      + `<text x="${x + barW / 2}" y="${y(v) - 6}" text-anchor="middle" font-size="11" font-weight="700" fill="#17365d">${v}</text>`
      + `<text x="${x + barW / 2}" y="${height - 10}" text-anchor="middle" font-size="11" fill="#4b5563">${escapeHtml(short)}</text>`
  }).join('')
  return `<svg viewBox="0 0 ${width} ${height}" width="100%" role="img">${grid}${bars}</svg>`
}

function donutChartSvg(data, centerLabel) {
  const total = data.reduce((s, d) => s + (Number(d.value) || 0), 0)
  // Segments are drawn as ring paths (outer arc, inner arc) so no slivers appear at the joins.
  const R = 87, r = 53
  const point = (radius, angle) => [100 + radius * Math.sin(angle), 100 - radius * Math.cos(angle)]
  let angle = 0
  const nonZero = data.filter((d) => Number(d.value) > 0)
  const arcs = total === 0
    ? `<circle cx="100" cy="100" r="${(R + r) / 2}" fill="none" stroke="#e5e7eb" stroke-width="${R - r}"/>`
    : nonZero.length === 1
      ? `<circle cx="100" cy="100" r="${(R + r) / 2}" fill="none" stroke="${nonZero[0].color || PALETTE[data.indexOf(nonZero[0]) % PALETTE.length]}" stroke-width="${R - r}"/>`
      : data.map((d, i) => {
        const value = Number(d.value) || 0
        if (!value) return ''
        const start = angle
        angle += (value / total) * 2 * Math.PI
        const large = angle - start > Math.PI ? 1 : 0
        const [x1, y1] = point(R, start), [x2, y2] = point(R, angle), [x3, y3] = point(r, angle), [x4, y4] = point(r, start)
        return `<path d="M${x1} ${y1} A${R} ${R} 0 ${large} 1 ${x2} ${y2} L${x3} ${y3} A${r} ${r} 0 ${large} 0 ${x4} ${y4} Z" fill="${d.color || PALETTE[i % PALETTE.length]}"/>`
      }).join('')
  const legend = data.map((d, i) => {
    const pct = total ? Math.round(((Number(d.value) || 0) / total) * 100) : 0
    return `<div class="legend-item"><span class="swatch" style="background:${d.color || PALETTE[i % PALETTE.length]}"></span>${escapeHtml(d.label)} - ${escapeHtml(d.value)} (${pct}%)</div>`
  }).join('')
  return `<div class="donut"><svg viewBox="0 0 200 200" width="180" height="180" role="img">${arcs}
      <text x="100" y="100" text-anchor="middle" font-size="26" font-weight="700" fill="#1f2937">${total}</text>
      <text x="100" y="120" text-anchor="middle" font-size="11" fill="#6b7280">${escapeHtml(centerLabel || 'total')}</text>
    </svg><div class="legend">${legend}</div></div>`
}

function chartBlock(chart) {
  const body = chart.type === 'donut' ? donutChartSvg(chart.data || [], chart.centerLabel) : barChartSvg(chart.data || [])
  return `<div class="chart"><h3>${escapeHtml(chart.title)}</h3>${body}</div>`
}

function tableBlock(section) {
  const headers = section.headers || []
  const strong = section.strongColumn ?? 1
  const head = headers.map((h, i) => `<th class="${i === 0 && h === '#' ? 'num' : ''}">${escapeHtml(h)}</th>`).join('')
  const body = (section.rows || []).map((row) => `<tr>${row.map((cell, i) => `<td class="${i === strong ? 'strong' : ''}${i === 0 && headers[0] === '#' ? ' num' : ''}">${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')
  const empty = `<tr><td colspan="${headers.length || 1}" class="empty">${escapeHtml(section.emptyMessage || 'No records for this period.')}</td></tr>`
  return `<section class="group">
    ${section.title ? `<div class="group-title">${escapeHtml(section.title)}${section.range ? `&nbsp; <span>(${escapeHtml(section.range)})</span>` : ''}</div>` : ''}
    <table><thead><tr>${head}</tr></thead><tbody>${body || empty}</tbody></table>
    ${section.subtotal ? `<div class="subtotal">${escapeHtml(section.subtotal)}</div>` : ''}
  </section>`
}

function currentUser() {
  try { return JSON.parse(localStorage.getItem('user') || 'null') || {} } catch { return {} }
}

function buildDocument(options, config) {
  const user = currentUser()
  const office = OFFICES[options.office || user.role] || OFFICES.admin
  const now = new Date()
  const datePrinted = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  const timePrinted = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })

  const genericName = !config.municipality_name || /^employsmart municipality$/i.test(config.municipality_name)
  const lguName = genericName ? 'EmploySmart' : config.municipality_name
  const lguSub = config.municipality_province || config.municipality_region || ''
  const address = config.contact_address || [genericName ? '' : config.municipality_name, config.municipality_province, config.municipality_region].filter(Boolean).join(', ')
  const contactLine = [config.contact_phone, config.contact_email].filter(Boolean).join('  |  ')
  const footerLeft = [lguName, [config.municipality_province, config.municipality_region].filter(Boolean).join(', '), [config.contact_phone && `Contact: ${config.contact_phone}`, config.contact_email && `Email: ${config.contact_email}`].filter(Boolean).join('  |  ')].filter(Boolean).join('\n')
  const footerRight = `Date Printed: ${datePrinted}     Time Printed: ${timePrinted}\nPAGE_PLACEHOLDER\nGenerated by: ${SYSTEM_NAME}`

  const meta = [
    options.dateRange && `Date Range: ${options.dateRange}`,
    options.groupedBy && `Grouped By: ${options.groupedBy}`,
    `Generated On: ${datePrinted} at ${timePrinted}`,
  ].filter(Boolean).map((m) => `<div>${escapeHtml(m)}</div>`).join('')

  // Legacy single-table callers.
  const sections = options.sections || (options.tableHeaders ? [{ headers: options.tableHeaders, rows: options.tableRows || [], strongColumn: 0 }] : [])
  const stats = (options.stats || []).map((s, i) => `<div class="card" style="border-left-color:${s.color || CARD_COLORS[i % CARD_COLORS.length]}"><div class="card-label">${escapeHtml(s.label)}</div><div class="card-value">${escapeHtml(s.value)}</div>${s.note ? `<div class="card-note">${escapeHtml(s.note)}</div>` : ''}</div>`).join('')
  const charts = (options.charts || []).map(chartBlock).join('')
  const insight = options.insight || options.summary

  const preparedName = [user.first_name, user.last_name].filter(Boolean).join(' ') || '________________________'
  const preparedPosition = options.preparedPosition || office.position

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${escapeHtml(options.title)}</title><style>
    @page {
      size: A4 portrait;
      margin: 14mm 14mm 30mm;
      @bottom-left { content: ${cssString(footerLeft)}; white-space: pre; font: 8pt Arial, sans-serif; color: #6b7280; vertical-align: top; padding-top: 3mm; border-top: 0.6pt solid #cbd5e1; }
      @bottom-right { content: ${cssString(footerRight).replace('PAGE_PLACEHOLDER', '" "Page " counter(page) " of " counter(pages) "')}; white-space: pre; text-align: right; font: 8pt Arial, sans-serif; color: #6b7280; vertical-align: top; padding-top: 3mm; border-top: 0.6pt solid #cbd5e1; }
    }
    * { box-sizing: border-box; }
    body { margin: 0; color: #1f2937; font-family: "DejaVu Sans", Verdana, Arial, Helvetica, sans-serif; font-size: 10.5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .letterhead { display: grid; grid-template-columns: 110px 1fr 110px; align-items: center; }
    .seal { text-align: center; }
    .seal img { width: 96px; height: 96px; object-fit: contain; }
    .seal-caption { margin-top: 2px; font-size: 9px; font-weight: 700; letter-spacing: .06em; color: #6b7280; }
    .lgu { text-align: center; }
    .lgu .republic { font-size: 12px; font-weight: 700; letter-spacing: .06em; color: #17365d; }
    .lgu .name { margin: 2px 0; font-size: 23px; font-weight: 700; color: #17365d; }
    .lgu .sub { font-size: 14px; color: #6b7280; }
    .rule { border-top: 1.5px solid #17365d; margin: 4px 0 0; }
    .contact { text-align: center; color: #17365d; font-size: 11px; line-height: 1.6; padding: 6px 0 8px; border-bottom: 2px solid #111827; }
    .meta { text-align: right; color: #6b7280; font-size: 10px; line-height: 1.6; margin: 12px 0 4px; }
    h1 { text-align: center; margin: 6px 0 2px; font-size: 19px; letter-spacing: .03em; text-transform: uppercase; color: #1f2937; }
    .subtitle { text-align: center; color: #6b7280; font-size: 12px; margin: 0 0 16px; }
    .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 10px 0 22px; }
    .card { background: #f4f6f4; border: 1px solid #d9ddd9; border-left: 6px solid ${REPORT_COLORS.green}; padding: 12px 12px 10px; break-inside: avoid; }
    .card-label { color: #6b7280; font-size: 11px; }
    .card-value { font-size: 24px; font-weight: 700; margin-top: 6px; color: #1f2937; }
    .card-note { color: #6b7280; font-size: 9px; margin-top: 2px; }
    .charts { display: grid; grid-template-columns: repeat(${Math.min(2, Math.max(1, (options.charts || []).length))}, 1fr); gap: 24px; margin-bottom: 18px; }
    .chart { break-inside: avoid; }
    .chart h3 { font-size: 12.5px; margin: 0 0 8px; color: #1f2937; }
    .donut { display: flex; flex-direction: column; align-items: center; }
    .legend { margin-top: 8px; }
    .legend-item { font-size: 11px; margin: 4px 0; display: flex; align-items: center; gap: 6px; }
    .swatch { width: 12px; height: 12px; display: inline-block; }
    .insight { border: 1px solid #2f8a5f; background: #e8efea; padding: 10px 16px 12px; margin: 6px 0 20px; break-inside: avoid; }
    .insight h4 { margin: 0 0 6px; font-size: 11px; letter-spacing: .04em; color: #1d5a3c; }
    .insight p { margin: 0; font-size: 11px; line-height: 1.6; }
    .group { margin-bottom: 14px; }
    .group-title { background: #dfe8dc; color: #1d4d2f; font-weight: 700; font-size: 11.5px; padding: 7px 10px; break-after: avoid; }
    .group-title span { font-weight: 700; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    th { background: #17365d; color: #fff; text-align: left; font-size: 11px; padding: 7px 9px; }
    td { padding: 7px 9px; font-size: 11px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    td.strong { font-weight: 700; color: #17365d; }
    th.num, td.num { width: 36px; }
    tbody tr:nth-child(odd) { background: #f2f2f0; }
    td.empty { text-align: center; color: #9ca3af; font-style: italic; }
    table { border: 1px solid #d1d5db; }
    td + td, th + th { border-left: 1px solid #d1d5db; }
    .subtotal { text-align: right; font-weight: 700; color: #6b7280; font-size: 10px; margin-top: 6px; }
    .grand-total { text-align: right; font-weight: 700; font-size: 13px; margin: 10px 0 0; }
    .signatures { margin-top: 34px; break-inside: avoid; }
    .signatures .label { font-size: 12px; margin-bottom: 34px; }
    .signature { width: 290px; text-align: center; }
    .signature .line { border-top: 1px solid #111827; margin-bottom: 6px; }
    .signature .name { font-weight: 700; font-size: 13px; }
    .signature .position { color: #6b7280; font-size: 11px; margin-top: 3px; }
    .signature .hint { color: #6b7280; font-size: 9px; font-style: italic; margin-top: 3px; }
    .screen-footer { display: none; }
    @media screen {
      body { max-width: 860px; margin: 24px auto; padding: 32px 40px; background: #fff; box-shadow: 0 0 0 1px #e5e7eb; }
      html { background: #f3f4f6; }
      .screen-footer { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 8px; border-top: 1px solid #cbd5e1; color: #6b7280; font-size: 9px; white-space: pre-line; }
      .screen-footer div:last-child { text-align: right; }
    }
  </style></head><body>
    <header>
      <div class="letterhead">
        ${seal(config.landing_logo_image, initials(lguName), '#17365d')}
        <div class="lgu">
          <div class="republic">REPUBLIC OF THE PHILIPPINES</div>
          <div class="name">${escapeHtml(lguName)}</div>
          ${lguSub ? `<div class="sub">${escapeHtml(lguSub)}</div>` : ''}
        </div>
        ${seal(config.landing_office_image, office.short, '#1d5a3c')}
      </div>
      <div class="rule"></div>
      <div class="contact">${address ? `<div>${escapeHtml(address)}</div>` : ''}${contactLine ? `<div>${escapeHtml(contactLine)}</div>` : ''}<div>${escapeHtml(office.name)}</div></div>
    </header>
    <div class="meta">${meta}</div>
    <h1>${escapeHtml(options.title)}</h1>
    ${options.subtitle ? `<p class="subtitle">${escapeHtml(options.subtitle)}</p>` : '<div style="height:10px"></div>'}
    ${stats ? `<div class="cards">${stats}</div>` : ''}
    ${charts ? `<div class="charts">${charts}</div>` : ''}
    ${insight ? `<div class="insight"><h4>INSIGHT</h4><p>${escapeHtml(insight)}</p></div>` : ''}
    ${sections.map(tableBlock).join('')}
    ${options.grandTotal ? `<div class="grand-total">${escapeHtml(options.grandTotal)}</div>` : ''}
    <div class="signatures">
      <div class="label">Prepared by:</div>
      <div class="signature"><div class="line"></div><div class="name">${escapeHtml(preparedName)}</div><div class="position">${escapeHtml(preparedPosition)}</div><div class="hint">Signature over printed name</div></div>
    </div>
    <div class="screen-footer"><div>${escapeHtml(footerLeft)}</div><div>${escapeHtml(footerRight.replace('PAGE_PLACEHOLDER', 'Page numbers appear when printed'))}</div></div>
  </body></html>`
}

function waitForImages(doc, timeout = 3000) {
  const images = Array.from(doc.images || [])
  if (!images.length) return Promise.resolve()
  return Promise.race([
    Promise.all(images.map((img) => (img.complete ? Promise.resolve() : new Promise((resolve) => { img.onload = resolve; img.onerror = resolve })))),
    new Promise((resolve) => setTimeout(resolve, timeout)),
  ])
}

export function printReport(input) {
  const printWindow = window.open('', '_blank', 'width=1000,height=900')
  if (!printWindow) return false
  printWindow.document.write('<!doctype html><title>Preparing report…</title><p style="font-family:Arial,sans-serif;padding:24px;color:#374151">Preparing report…</p>')
  printWindow.document.close()

  ;(async () => {
    try {
      const [options, config] = await Promise.all([
        typeof input === 'function' ? input() : Promise.resolve(input),
        loadMunicipality(),
      ])
      printWindow.document.open()
      printWindow.document.write(buildDocument(options, config || {}))
      printWindow.document.close()
      await waitForImages(printWindow.document)
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
