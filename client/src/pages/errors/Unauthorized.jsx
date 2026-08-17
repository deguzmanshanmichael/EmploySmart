import { useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { FiLock, FiArrowLeft, FiLogOut } from 'react-icons/fi'

export default function Unauthorized() {
  const navigate = useNavigate()
  const location = useLocation()
  const { user, logout } = useAuth()

  const handleLogout = () => {
    logout()
    navigate('/login/user')
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-red-50 via-white to-orange-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-red-100 mb-4">
            <FiLock className="text-red-600" size={40} />
          </div>
          <h1 className="text-4xl font-bold font-display text-gray-900 mb-2">401</h1>
          <p className="text-lg font-semibold text-gray-700">Unauthorized</p>
        </div>

        <div className="bg-white rounded-xl shadow-md border border-red-100 p-6 mb-6">
          <p className="text-gray-600 mb-4">
            {user
              ? "You don't have permission to access this page with your current role."
              : 'Your session has expired or you are not authenticated. Please log in to continue.'}
          </p>

          {location.pathname && (
            <p className="text-xs text-gray-400 bg-gray-50 rounded p-2 font-mono">
              {location.pathname}
            </p>
          )}
        </div>

        {user && (
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">
            <p className="text-sm text-gray-700 mb-2">
              <strong>Logged in as:</strong> {user.first_name} {user.last_name}
            </p>
            <p className="text-sm text-gray-700">
              <strong>Role:</strong> <span className="capitalize">{user.role}</span>
            </p>
          </div>
        )}

        <div className="flex flex-col gap-3">
          <button
            onClick={() => navigate(-1)}
            className="btn-secondary flex items-center justify-center gap-2 w-full"
          >
            <FiArrowLeft size={16} /> Go Back
          </button>

          {user ? (
            <>
              <button
                onClick={() => navigate(`/${user.role}`)}
                className="btn-primary w-full"
              >
                Go to Dashboard
              </button>
              <button
                onClick={handleLogout}
                className="btn-outline flex items-center justify-center gap-2 w-full text-red-600 hover:bg-red-50 border-red-200"
              >
                <FiLogOut size={16} /> Sign Out
              </button>
            </>
          ) : (
            <button onClick={() => navigate('/login/user')} className="btn-primary w-full">
              Sign In
            </button>
          )}
        </div>

        <p className="text-xs text-gray-400 mt-6">
          Need help? Contact support at{' '}
          <a href="mailto:support@employsmart.com" className="text-blue-600 hover:underline">
            support@employsmart.com
          </a>
        </p>
      </div>
    </div>
  )
}
