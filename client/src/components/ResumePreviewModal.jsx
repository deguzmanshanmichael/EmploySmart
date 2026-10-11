import { useEffect, useRef, useState } from 'react'
import { FiDownload, FiX } from 'react-icons/fi'

/**
 * Shows a generated resume PDF page by page so the jobseeker can review it before
 * deciding to download. Pages are drawn with pdf.js, which also works on phones where
 * embedded PDF viewers are often unavailable.
 */
export default function ResumePreviewModal({ blob, filename, onClose }) {
  const pagesRef = useRef(null)
  const [status, setStatus] = useState('loading')

  useEffect(() => {
    let cancelled = false
    let doc = null
    const container = pagesRef.current

    ;(async () => {
      try {
        const pdfjs = await import('pdfjs-dist')
        const workerUrl = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default
        pdfjs.GlobalWorkerOptions.workerSrc = workerUrl
        doc = await pdfjs.getDocument({ data: new Uint8Array(await blob.arrayBuffer()) }).promise
        const width = Math.min(container.clientWidth || 800, 820)
        const ratio = window.devicePixelRatio || 1
        for (let n = 1; n <= doc.numPages && !cancelled; n++) {
          const page = await doc.getPage(n)
          const viewport = page.getViewport({ scale: (width / page.getViewport({ scale: 1 }).width) * ratio })
          const canvas = document.createElement('canvas')
          canvas.width = viewport.width
          canvas.height = viewport.height
          canvas.style.width = `${viewport.width / ratio}px`
          canvas.className = 'block mx-auto bg-white shadow-md rounded-sm max-w-full h-auto'
          canvas.setAttribute('aria-label', `Resume page ${n} of ${doc.numPages}`)
          await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise
          if (!cancelled) container.appendChild(canvas)
        }
        if (!cancelled) setStatus('ready')
      } catch {
        if (!cancelled) setStatus('error')
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
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)
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
          {status === 'error' && (
            <p className="text-center text-sm text-gray-600 py-10">
              The preview could not be shown on this device. You can still download the resume to view it.
            </p>
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
