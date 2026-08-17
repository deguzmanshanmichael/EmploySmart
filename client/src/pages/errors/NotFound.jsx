import { useNavigate, useLocation } from 'react-router-dom'
import { FiSearch, FiArrowLeft, FiHome } from 'react-icons/fi'

export default function NotFound() {
  const navigate = useNavigate()
  const location = useLocation()

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-50 via-white to-pink-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-purple-100 mb-4">
            <FiSearch className="text-purple-600" size={40} />
          </div>
          <h1 className="text-4xl font-bold font-display text-gray-900 mb-2">404</h1>
          <p className="text-lg font-semibold text-gray-700">Page Not Found</p>
        </div>

        <div className="bg-white rounded-xl shadow-md border border-purple-100 p-6 mb-6">
          <p className="text-gray-600 mb-4">
            We couldn't find the page you're looking for. It may have been moved, deleted, or the URL might be incorrect.
          </p>

          {location.pathname && (
            <p className="text-xs text-gray-400 bg-gray-50 rounded p-2 font-mono break-all">
              {location.pathname}
            </p>
          )}
        </div>

        <div className="flex flex-col gap-3">
          <button
            onClick={() => navigate(-1)}
            className="btn-secondary flex items-center justify-center gap-2 w-full"
          >
            <FiArrowLeft size={16} /> Go Back
          </button>

          <button onClick={() => navigate('/')} className="btn-primary flex items-center justify-center gap-2 w-full">
            <FiHome size={16} /> Home
          </button>
        </div>

        <p className="text-xs text-gray-400 mt-6">
          If you believe this is a mistake, please{' '}
          <a href="mailto:support@employsmart.com" className="text-blue-600 hover:underline">
            contact support
          </a>
        </p>
      </div>
    </div>
  )
}
