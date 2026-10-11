/**
 * PDF.js for reading PDFs in the browser (resume preview, scanned-resume OCR).
 *
 * The legacy build also runs on older phone browsers. PDF.js runs on the page itself
 * instead of in a web worker: a worker file can be blocked by the host (for example when
 * .mjs files are served with a non-JavaScript type), and resumes are small enough that
 * reading them on the page is quick. Both parts load only when a PDF is opened.
 */
let loading = null

export function loadPdfjs() {
  if (!loading) {
    loading = (async () => {
      // Registering the worker code on the page makes PDF.js use it directly.
      await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs')
      return import('pdfjs-dist/legacy/build/pdf.mjs')
    })().catch((error) => {
      loading = null
      throw error
    })
  }
  return loading
}

/** Open PDF bytes (Uint8Array). The bytes are copied because PDF.js may take ownership of them. */
export async function openPdf(bytes) {
  const pdfjs = await loadPdfjs()
  return pdfjs.getDocument({ data: bytes.slice(), isEvalSupported: false }).promise
}
