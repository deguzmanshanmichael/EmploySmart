import { Link } from 'react-router-dom'
import { FiArrowLeft, FiPhone, FiMapPin, FiMail } from 'react-icons/fi'

export default function RequestVerification() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex items-center justify-center p-4">
      <div className="w-full max-w-lg">
        <div className="bg-white rounded-2xl shadow-lg border border-gray-100 p-8 text-center">
          <div className="text-5xl mb-4">📋</div>
          <h2 className="text-2xl font-bold font-display text-gray-900 mb-2">Account Verification</h2>
          <p className="text-gray-500 text-sm mb-6">
            Your account needs to be verified by the PESO office before you can access the system.
            Please visit or contact the PESO office with the following information.
          </p>
          <div className="bg-blue-50 rounded-xl p-5 text-left mb-6 space-y-3">
            <h3 className="font-bold text-blue-800 text-sm">PESO Office — Cabanatuan City</h3>
            <p className="flex items-center gap-2 text-sm text-gray-600"><FiMapPin size={14} className="text-blue-500" /> City Hall, Cabanatuan City, Nueva Ecija</p>
            <p className="flex items-center gap-2 text-sm text-gray-600"><FiPhone size={14} className="text-blue-500" /> (044) 940-0000</p>
            <p className="flex items-center gap-2 text-sm text-gray-600"><FiMail size={14} className="text-blue-500" /> peso@cabanatuan.gov.ph</p>
          </div>
          <p className="text-xs text-gray-400 mb-5">Office Hours: Monday–Friday, 8:00 AM – 5:00 PM</p>
          <Link to="/login" className="btn-primary inline-flex items-center gap-2">
            <FiArrowLeft size={15} /> Back to Login
          </Link>
        </div>
      </div>
    </div>
  )
}