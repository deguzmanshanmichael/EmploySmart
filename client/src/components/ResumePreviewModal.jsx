import { useEffect, useMemo, useRef, useState } from 'react'
import { FiDownload, FiExternalLink, FiX } from 'react-icons/fi'
import { openPdf } from '../utils/pdfjs'

/**
 * Shows a generated resume PDF page by page so the jobseeker can review it before
 * deciding to download. Pages are drawn with PDF.js, which also works on phones where
 * embedded PDF viewers are unavailable; browsers with a built-in PDF viewer use that
 * if PDF.js cannot run.
 */
export default function ResumePreviewModal({ blob, filename, onClose }) {
  const pagesRef = useRef(null)
  const [status, setStatus] = useState('loading')
  const [detail, setDetail] = useState('')
  const blobUrl = useMemo(() => window.URL.createObjectURL(blob), [blob])

  useEffect(() => () => window.URL.revokeObjectURL(blobUrl), [blobUrl])

  useEffect(() => {
    let cancelled = false
    let doc = null
    const container = pagesRef.current

    ;(async () => {
      try {
        doc = await openPdf(new Uint8Array(await blob.arrayBuffer()))
        const width = Math.min(container.clientWidth || 800, 820)
        // Very dense phone screens would need huge canvases; twice the size is sharp enough.
        const ratio = Math.min(window.devicePixelRatio || 1, 2)
        for (let n = 1; n <= doc.numPages && !cancelled; n++) {
          const page = await doc.getPage(n)
          const viewport = page.getViewport({ scale: (width / page.getViewport({ scale: 1 }).width) * ratio })
          const canvas = document.createElement('canvas')
          canvas.width = Math.floor(viewport.width)
          canvas.height = Math.floor(viewport.height)
          canvas.style.width = `${viewport.width / ratio}px`
          canvas.className = 'block mx-auto bg-white shadow-md rounded-sm max-w-full h-auto'
          canvas.setAttribute('role', 'img')
          canvas.setAttribute('aria-label', `Resume page ${n} of ${doc.numPages}`)
          await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise
          if (!cancelled) container.appendChild(canvas)
        }
        if (!cancelled) setStatus('ready')
      } catch (error) {
        console.error('Resume preview failed', error)
        if (cancelled) return
        setDetail(error?.message || String(error))
        // Desktop browsers can still show the PDF with their own viewer.
        setStatus(container.childElementCount === 0 && navigator.pdfViewerEnabled ? 'embed' : 'error')
      }
    })()

    return () => {
      cancelled = true
      doc?.destroy()
      if (container) container.innerHTML = ''
    }
  }, [blob])

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose() }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const handleDownload = () => {
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-2 sm:p-4" role="dialog" aria-modal="true" aria-label="Resume preview" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[95vh] flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between gap-3 px-4 py-3 border-b">
          <div>
            <h2 className="font-bold text-gray-800">Resume preview</h2>
            <p className="text-xs text-gray-500">Check your resume, then download it if everything looks right.</p>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Close preview">
            <FiX size={22} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto bg-slate-100 p-3 sm:p-6">
          {status === 'loading' && <p className="text-center text-sm text-gray-500 py-10">Preparing preview…</p>}
          {status === 'embed' && (
            <iframe src={blobUrl} title="Resume preview" className="w-full h-[70vh] bg-white rounded shadow-md" />
          )}
          {status === 'error' && (
            <div className="text-center text-sm text-gray-600 py-8 space-y-3">
              <p>The preview could not be drawn here. You can open the PDF or download it instead.</p>
              <a href={blobUrl} target="_blank" rel="noopener" className="btn-outline btn-sm inline-flex items-center gap-2">
                <FiExternalLink /> Open PDF
              </a>
              {detail && <p className="text-xs text-gray-400">Details: {detail}</p>}
            </div>
          )}
          <div ref={pagesRef} className="space-y-4" />
        </div>

        <div className="flex flex-wrap justify-end gap-2 px-4 py-3 border-t">
          <button onClick={onClose} className="btn-outline btn-sm">Close</button>
          <button onClick={handleDownload} className="btn-primary btn-sm inline-flex items-center gap-2" disabled={status === 'loading'}>
            <FiDownload /> Download PDF
          </button>
        </div>
      </div>
    </div>
  )
}
