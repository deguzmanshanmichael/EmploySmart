import { openPdf } from './pdfjs'

/**
 * In-browser text recognition for scanned resumes.
 *
 * Shared hosting cannot run OCR engines, so scanned PDFs are rendered with PDF.js and
 * read with Tesseract.js on the jobseeker's own device. Both libraries are loaded only
 * when needed. Tesseract downloads its language data (~10 MB) from a CDN on first use.
 */
export async function recognizeResumePdf(fileOrBlob, { maxPages = 5, onProgress } = {}) {
  const report = (stage, value) => onProgress?.({ stage, value })

  report('loading', 0)
  const { createWorker } = await import('tesseract.js')
  const pdf = await openPdf(new Uint8Array(await fileOrBlob.arrayBuffer()))
  const pages = Math.min(pdf.numPages, maxPages)

  let currentPage = 1
  const worker = await createWorker('eng', 1, {
    logger: (m) => {
      if (m.status === 'recognizing text') {
        report('recognizing', Math.round((((currentPage - 1) + m.progress) / pages) * 100))
      }
    },
  })

  try {
    const texts = []
    for (let i = 1; i <= pages; i++) {
      currentPage = i
      report('rendering', Math.round(((i - 1) / pages) * 100))
      const page = await pdf.getPage(i)
      // ~200 DPI gives Tesseract enough detail without exhausting phone memory.
      const viewport = page.getViewport({ scale: 2.8 })
      const canvas = document.createElement('canvas')
      canvas.width = Math.floor(viewport.width)
      canvas.height = Math.floor(viewport.height)
      const context = canvas.getContext('2d')
      await page.render({ canvasContext: context, viewport }).promise
      const { data: result } = await worker.recognize(canvas)
      texts.push(result.text || '')
      canvas.width = 0
      canvas.height = 0
    }
    report('done', 100)
    return { text: texts.join('\n\n'), pages, totalPages: pdf.numPages }
  } finally {
    await worker.terminate()
    pdf.destroy()
  }
}
