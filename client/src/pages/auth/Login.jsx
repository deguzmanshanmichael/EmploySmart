import { Link } from 'react-router-dom'
import { FiBriefcase, FiUsers, FiArrowRight } from 'react-icons/fi'

export default function Login() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex items-center justify-center p-4">
      <div className="w-full max-w-4xl">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 shadow-lg mb-4">
            <span className="text-white text-2xl font-bold font-display">ES</span>
          </div>
          <h1 className="text-3xl font-bold font-display text-gray-900">EmploySmart</h1>
          <p className="text-gray-500 mt-1 text-sm">Choose the portal that matches your account type</p>
        </div>

        <div className="grid md:grid-cols-2 gap-6">
          <Link to="/login/user" className="group rounded-2xl border border-gray-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 text-blue-700">
              <FiUsers size={24} />
            </div>
            <h2 className="mt-5 text-xl font-bold text-gray-900">User Portal</h2>
            <p className="mt-2 text-sm text-gray-600">For jobseekers, employers, and applicants who need access to jobs, applications, and profile tools.</p>
            <div className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-blue-700">
              Go to User Login <FiArrowRight />
            </div>
          </Link>

          <Link to="/login/staff" className="group rounded-2xl border border-gray-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
              <FiBriefcase size={24} />
            </div>
            <h2 className="mt-5 text-xl font-bold text-gray-900">Staff Portal</h2>
            <p className="mt-2 text-sm text-gray-600">For PESO, CLCDO, and admin staff managing jobs, training, feedback, and system settings.</p>
            <div className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">
              Go to Staff Login <FiArrowRight />
            </div>
          </Link>
        </div>

        <p className="mt-6 text-center text-sm text-gray-500">
          Need an account? <Link to="/register" className="font-semibold text-blue-700 hover:underline">Register here</Link>
        </p>
      </div>
    </div>
  )
}
