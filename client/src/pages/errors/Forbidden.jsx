import { useNavigate } from 'react-router-dom'
import { FiArrowLeft, FiLock } from 'react-icons/fi'

export default function Forbidden() {
  const navigate = useNavigate()

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-orange-100 mb-4">
          <FiLock className="text-orange-600" size={40} />
        </div>
        <h1 className="text-4xl font-bold font-display text-gray-900">403</h1>
        <p className="mt-2 text-lg font-semibold text-gray-700">Access forbidden</p>
        <p className="mt-3 text-sm text-gray-600">Your account is signed in, but this staff action is restricted to another role.</p>
        <button onClick={() => navigate(-1)} className="btn-secondary mt-6 w-full"><FiArrowLeft /> Go back</button>
      </div>
    </div>
  )
}
