import { useState } from 'react'
import { FiEye, FiEyeOff } from 'react-icons/fi'

export default function PasswordField({ className = 'input', ...props }) {
  const [visible, setVisible] = useState(false)

  return (
    <div className="relative">
      <input {...props} type={visible ? 'text' : 'password'} className={`${className} pr-10`} />
      <button
        type="button"
        onClick={() => setVisible((current) => !current)}
        className="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
        aria-label={visible ? 'Hide password' : 'Show password'}
      >
        {visible ? <FiEyeOff size={16} /> : <FiEye size={16} />}
      </button>
    </div>
  )
}
