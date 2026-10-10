import { Link } from 'react-router-dom'
import { FiArrowLeft, FiPhone, FiMapPin, FiMail } from 'react-icons/fi'
import useMunicipality from '../../hooks/useMunicipality'

export default function RequestVerification() {
  const { config, place } = useMunicipality()
  const office = config.contact_office || 'PESO'
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
            <h3 className="font-bold text-blue-800 text-sm">{office} Office{place ? ` — ${place}` : ''}</h3>
            {(config.contact_address || place) && <p className="flex items-center gap-2 text-sm text-gray-600"><FiMapPin size={14} className="text-blue-500" /> {config.contact_address || place}</p>}
            {config.contact_phone && <p className="flex items-center gap-2 text-sm text-gray-600"><FiPhone size={14} className="text-blue-500" /> {config.contact_phone}</p>}
            {config.contact_email && <p className="flex items-center gap-2 text-sm text-gray-600"><FiMail size={14} className="text-blue-500" /> {config.contact_email}</p>}
          </div>
          {config.office_hours && <p className="text-xs text-gray-400 mb-5">Office Hours: {config.office_hours}</p>}
          <Link to="/login" className="btn-primary inline-flex items-center gap-2">
            <FiArrowLeft size={15} /> Back to Login
          </Link>
        </div>
      </div>
    </div>
  )
}