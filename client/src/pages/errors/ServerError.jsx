import { useNavigate } from 'react-router-dom'
import { FiAlertTriangle, FiArrowLeft, FiRefreshCw } from 'react-icons/fi'

export default function ServerError() {
  const navigate = useNavigate()

  const handleRetry = () => {
    window.location.reload()
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-orange-50 via-white to-red-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-orange-100 mb-4">
            <FiAlertTriangle className="text-orange-600" size={40} />
          </div>
          <h1 className="text-4xl font-bold font-display text-gray-900 mb-2">500</h1>
          <p className="text-lg font-semibold text-gray-700">Server Error</p>
        </div>

        <div className="bg-white rounded-xl shadow-md border border-orange-100 p-6 mb-6">
          <p className="text-gray-600 mb-4">
            Something went wrong on our end. Our team has been notified and is working to resolve the issue.
          </p>
          <p className="text-sm text-gray-500">Please try again in a few moments.</p>
        </div>

        <div className="flex flex-col gap-3">
          <button onClick={handleRetry} className="btn-primary flex items-center justify-center gap-2 w-full">
            <FiRefreshCw size={16} /> Try Again
          </button>

          <button
            onClick={() => navigate('/')}
            className="btn-secondary flex items-center justify-center gap-2 w-full"
          >
            <FiArrowLeft size={16} /> Go Home
          </button>
        </div>

        <p className="text-xs text-gray-400 mt-6">
          If the problem persists, please{' '}
          <a href="mailto:support@employsmart.com" className="text-blue-600 hover:underline">
            contact support
          </a>
        </p>
      </div>
    </div>
  )
}
