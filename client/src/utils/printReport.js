function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

export function printReport({ title, subtitle = '', summary = '', tableHeaders = [], tableRows = [] }) {
  const printWindow = window.open('', '_blank', 'width=1100,height=800')
  if (!printWindow) return false

  const generatedAt = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date())
  const headerHtml = tableHeaders.map((header) => `<th>${escapeHtml(header)}</th>`).join('')
  const bodyHtml = tableRows.map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')

  printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title><style>
    @page { size: A4 landscape; margin: 22mm 14mm 18mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
    .report-header { border-bottom: 3px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 18px; }
    .organization { color: #1d4ed8; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    h1 { margin: 5px 0 3px; font-size: 22px; color: #111827; }
    .subtitle, .meta { margin: 0; color: #5b6472; }
    .meta { margin-top: 7px; font-size: 10px; }
    .summary { margin: 0 0 14px; color: #374151; }
    table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    th { background: #eff6ff; color: #1e3a8a; font-weight: 700; text-align: left; }
    th, td { border: 1px solid #d8dee8; padding: 7px 8px; vertical-align: top; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    .report-footer { position: fixed; bottom: -10mm; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 5px; color: #64748b; font-size: 9px; }
    .page-number::after { content: counter(page); }
    @media screen { body { max-width: 1100px; margin: 28px auto; padding: 0 24px 40px; } .report-footer { position: static; margin-top: 24px; } }
  </style></head><body>
    <header class="report-header"><div class="organization">EmploySmart</div><h1>${escapeHtml(title)}</h1><p class="subtitle">${escapeHtml(subtitle)}</p><p class="meta">Generated ${escapeHtml(generatedAt)}</p></header>
    ${summary ? `<p class="summary">${escapeHtml(summary)}</p>` : ''}
    <table><thead><tr>${headerHtml}</tr></thead><tbody>${bodyHtml || `<tr><td colspan="${tableHeaders.length}">No records available.</td></tr>`}</tbody></table>
    <footer class="report-footer"><span>EmploySmart · Evaluation copy</span><span style="float:right">Page <span class="page-number"></span></span></footer>
  </body></html>`)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
  return true
}
