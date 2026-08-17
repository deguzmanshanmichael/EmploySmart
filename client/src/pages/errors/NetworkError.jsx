import { useNavigate } from 'react-router-dom'
import { FiWifiOff, FiArrowLeft, FiRefreshCw } from 'react-icons/fi'

export default function NetworkError({ error }) {
  const navigate = useNavigate()

  const handleRetry = () => {
    window.location.reload()
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-red-50 via-white to-orange-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-red-100 mb-4">
            <FiWifiOff className="text-red-600" size={40} />
          </div>
          <h1 className="text-3xl font-bold font-display text-gray-900 mb-2">Connection Error</h1>
          <p className="text-gray-700">Cannot reach the server</p>
        </div>

        <div className="bg-white rounded-xl shadow-md border border-red-100 p-6 mb-6">
          <p className="text-gray-600 mb-4">
            We're unable to connect to the EmploySmart server. This could be due to:
          </p>
          <ul className="text-left text-sm text-gray-600 space-y-2 mb-4">
            <li className="flex items-start gap-2">
              <span className="text-red-600 font-bold">•</span>
              <span>Network connection issues</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-red-600 font-bold">•</span>
              <span>Server is temporarily unavailable</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-red-600 font-bold">•</span>
              <span>Incorrect server IP address or URL</span>
            </li>
          </ul>

          {error?.message && (
            <div className="text-xs text-gray-500 bg-gray-50 rounded p-3 font-mono text-left border border-gray-200 mb-4">
              <p className="font-semibold text-gray-700 mb-2">Error Details:</p>
              <p className="break-words">{error.message}</p>
            </div>
          )}
        </div>

        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">
          <p className="text-sm font-semibold text-gray-800 mb-2">Troubleshooting:</p>
          <ol className="text-sm text-gray-700 space-y-1 list-decimal list-inside">
            <li>Check your internet connection</li>
            <li>Verify the server is running</li>
            <li>Refresh the page and try again</li>
            <li>Contact your administrator if the problem persists</li>
          </ol>
        </div>

        <div className="flex flex-col gap-3">
          <button onClick={handleRetry} className="btn-primary flex items-center justify-center gap-2 w-full">
            <FiRefreshCw size={16} /> Try Again
          </button>

          <button
            onClick={() => navigate(-1)}
            className="btn-secondary flex items-center justify-center gap-2 w-full"
          >
            <FiArrowLeft size={16} /> Go Back
          </button>
        </div>

        <p className="text-xs text-gray-400 mt-6">
          Need assistance?{' '}
          <a href="mailto:support@employsmart.com" className="text-blue-600 hover:underline">
            Contact support
          </a>
        </p>
      </div>
    </div>
  )
}
