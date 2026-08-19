import { useNavigate } from 'react-router-dom'
import { FiArrowLeft, FiLock } from 'react-icons/fi'

export default function Forbidden() {
  const navigate = useNavigate()

  return (
    <div className="min-h-screen bg-gradient-to-br from-orange-50 via-white to-red-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-orange-100 mb-4">
            <FiLock className="text-orange-600" size={40} />
          </div>
          <h1 className="text-4xl font-bold font-display text-gray-900 mb-2">403</h1>
          <p className="text-lg font-semibold text-gray-700">Access Forbidden</p>
        </div>

        <div className="bg-white rounded-xl shadow-md border border-orange-100 p-6 mb-6">
          <p className="text-gray-600">Your account is authenticated, but this action is restricted to another staff role.</p>
        </div>

        <button
          onClick={() => navigate(-1)}
          className="btn-secondary flex items-center justify-center gap-2 w-full"
        >
          <FiArrowLeft size={16} /> Go Back
        </button>
      </div>
    </div>
  )
}
