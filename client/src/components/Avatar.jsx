import { useEffect, useState } from 'react'
import { API_BASE_URL } from '../config/api.js'

/**
 * Profile photo with an initials fallback. If the image cannot be loaded (for example
 * the file was removed), the user's initials are shown instead of a broken image.
 */
export default function Avatar({ user, className = '', textClassName = 'text-white font-bold' }) {
  const [failed, setFailed] = useState(false)
  const path = user?.profile_picture
  useEffect(() => { setFailed(false) }, [path])
  const initials = `${user?.first_name?.charAt(0) || ''}${user?.last_name?.charAt(0) || ''}`.toUpperCase() || '?'

  if (path && !failed) {
    const src = /^(https?:|data:)/i.test(path) ? path : `${API_BASE_URL}/${String(path).replace(/^\/+/, '')}`
    return <img src={src} alt={`${user?.first_name || 'User'}'s profile photo`} className={`object-cover ${className}`} onError={() => setFailed(true)} />
  }
  return <span className={textClassName}>{initials}</span>
}
