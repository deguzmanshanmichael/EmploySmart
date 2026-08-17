import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { FiClock, FiArrowLeft, FiLogOut } from 'react-icons/fi'

export default function SessionExpired() {
  const navigate = useNavigate()
  const { logout } = useAuth()

  const handleGoToLogin = () => {
    logout()
    navigate('/login/user')
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-amber-50 via-white to-orange-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-amber-100 mb-4">
            <FiClock className="text-amber-600" size={40} />
          </div>
          <h1 className="text-3xl font-bold font-display text-gray-900 mb-2">Session Expired</h1>
          <p className="text-gray-700">Your login session has ended</p>
        </div>

        <div className="bg-white rounded-xl shadow-md border border-amber-100 p-6 mb-6">
          <p className="text-gray-600 mb-4">
            For security reasons, we've ended your session due to inactivity or session duration limits. Please log in again to continue.
          </p>
        </div>

        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 text-left">
          <p className="text-sm text-gray-800">
            <strong>For your security:</strong> Your session expires periodically. Any unsaved work may be lost.
          </p>
        </div>

        <div className="flex flex-col gap-3">
          <button onClick={handleGoToLogin} className="btn-primary flex items-center justify-center gap-2 w-full">
            <FiLogOut size={16} /> Sign In Again
          </button>

          <button
            onClick={() => navigate(-1)}
            className="btn-secondary flex items-center justify-center gap-2 w-full"
          >
            <FiArrowLeft size={16} /> Go Back
          </button>
        </div>

        <p className="text-xs text-gray-400 mt-6">
          Questions? Contact{' '}
          <a href="mailto:support@employsmart.com" className="text-blue-600 hover:underline">
            support@employsmart.com
          </a>
        </p>
      </div>
    </div>
  )
}
