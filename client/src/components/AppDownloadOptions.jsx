import { useState, useEffect } from 'react'
import { FiDownload, FiX } from 'react-icons/fi'
import toast from 'react-hot-toast'
import { Capacitor } from '@capacitor/core'

export function AppDownloadOptions() {
  const [showModal, setShowModal] = useState(false)
  const [isNativeApp, setIsNativeApp] = useState(false)

  useEffect(() => {
    // Check if running in native Capacitor app
    setIsNativeApp(Capacitor.isNativePlatform())
  }, [])

  // Temporarily hide the download/install button everywhere.
  if (true) {
    return null
  }

  const handleDownloadAPK = () => {
    try {
      // Direct download from static ZIP path containing the APK, served from the current host
      const zipUrl = `${window.location.origin}/employsmart/EmploySmart.zip`
      const link = document.createElement('a')
      link.href = zipUrl
      link.download = 'EmploySmart.zip'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      toast.success('ZIP download started. Extract and install the APK on your Android device.')
    } catch (err) {
      toast.error('Download failed')
    }
  }

  return (
    <>
      <button
        onClick={() => setShowModal(true)}
        className="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors text-sm"
        title="Download or install app"
      >
        <FiDownload size={16} />
        Get App
      </button>

      {showModal && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900">Install EmploySmart</h2>
              <button
                onClick={() => setShowModal(false)}
                className="p-1 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <FiX size={20} />
              </button>
            </div>

            <div className="space-y-3">
              {/* Android Option */}
              <div className="p-4 bg-orange-50 rounded-lg border border-orange-200">
                <h3 className="font-semibold text-gray-900 mb-2">📱 Android (ZIP)</h3>
                <p className="text-sm text-gray-600 mb-3">
                  Download ZIP file containing the updated APK for offline installation
                </p>
                <button
                  onClick={handleDownloadAPK}
                  className="w-full px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium"
                >
                  Download ZIP for Android
                </button>
                <p className="text-xs text-gray-500 mt-2">
                  💡 Tip: Extract ZIP, then enable "Unknown Sources" to install the APK
                </p>
              </div>

              {/* Web/iOS Option */}
              <div className="p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h3 className="font-semibold text-gray-900 mb-2">🌐 Web / iOS / Desktop</h3>
                <p className="text-sm text-gray-600 mb-3">
                  Works on any device with a browser
                </p>
                <button
                  disabled
                  className="w-full px-3 py-2 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed text-sm font-medium"
                >
                  Already Installed 🎉
                </button>
                <p className="text-xs text-gray-500 mt-2">
                  💡 iOS Tip: Tap Share → "Add to Home Screen" for app-like experience
                </p>
              </div>

              {/* PWA Option */}
              <div className="p-4 bg-purple-50 rounded-lg border border-purple-200">
                <h3 className="font-semibold text-gray-900 mb-2">⚡ Progressive Web App</h3>
                <p className="text-sm text-gray-600 mb-3">
                  Install directly from this browser (Works offline)
                </p>
                <button
                  disabled
                  className="w-full px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium"
                >
                  Look for Install Button in Navbar
                </button>
              </div>
            </div>

            <div className="pt-4 border-t">
              <p className="text-xs text-gray-500 text-center">
                Choose any option above to get EmploySmart on your device
              </p>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
