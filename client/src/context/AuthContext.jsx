import { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react'
import { authService } from '../services/authService'
import toast from 'react-hot-toast'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try { return JSON.parse(localStorage.getItem('user')) } catch { return null }
  })
  const [loading, setLoading] = useState(true)
  const refreshTimerRef = useRef(null)

  // ─── Schedule proactive token refresh 60s before expiry ─────────────────
  const scheduleRefresh = useCallback((expiresIn = 900) => {
    if (refreshTimerRef.current) clearTimeout(refreshTimerRef.current)
    const delay = Math.max((expiresIn - 60) * 1000, 5000)
    refreshTimerRef.current = setTimeout(async () => {
      const refreshToken = localStorage.getItem('refresh_token')
      if (!refreshToken) return logout()
      try {
        const res = await authService.refresh(refreshToken)
        const { access_token, refresh_token: newRefresh, csrf_token, expires_in, user: userData } = res.data.data
        localStorage.setItem('access_token', access_token)
        localStorage.setItem('refresh_token', newRefresh)
        localStorage.setItem('csrf_token', csrf_token)
        if (userData) {
          localStorage.setItem('user', JSON.stringify(userData))
          setUser(userData)
        }
        scheduleRefresh(expires_in)
      } catch {
        logout()
        toast.error('Session expired. Please log in again.')
      }
    }, delay)
  }, [])

  // ─── Boot: verify existing session ───────────────────────────────────────
  useEffect(() => {
    const init = async () => {
      const token = localStorage.getItem('access_token')
      if (token) {
        try {
          const res = await authService.me()
          const userData = res.data.data
          setUser(userData)
          localStorage.setItem('user', JSON.stringify(userData))
          scheduleRefresh(900)
        } catch {
          logout()
        }
      }
      setLoading(false)
    }
    init()
    return () => { if (refreshTimerRef.current) clearTimeout(refreshTimerRef.current) }
  }, [])

  const login = async (credentials) => {
    try {
      const res = await authService.login(credentials)
      console.log('👉 Login response:', res)
      console.log('👉 Response data:', res.data)

      if (!res.data) {
        console.error('❌ Empty response from server:', res)
        throw new Error('Invalid server response format: ' + JSON.stringify(res || 'no-response'))
      }

      if (res.data.success === false) {
        const msg = res.data.message || 'Login failed'
        console.error('❌ Server responded with error:', res.data)
        throw new Error(msg)
      }

      if (!res.data.data) {
        console.error('❌ Missing data property in response:', res.data)
        throw new Error('Invalid server response format: ' + JSON.stringify(res.data || {}))
      }

      const { access_token, refresh_token, csrf_token, expires_in, user: userData } = res.data.data
      localStorage.setItem('access_token', access_token)
      localStorage.setItem('refresh_token', refresh_token)
      localStorage.setItem('csrf_token', csrf_token)
      localStorage.setItem('user', JSON.stringify(userData))
      setUser(userData)
      scheduleRefresh(expires_in)
      return userData
    } catch (error) {
      const payload = error?.response?.data
      if (payload?.success === false && payload?.message) {
        throw new Error(payload.message)
      }
      if (payload?.message) {
        throw new Error(payload.message)
      }
      if (typeof payload === 'string' && payload.includes('Fatal error')) {
        throw new Error('Server error. Please try again later.')
      }
      throw error
    }
  }

  const logout = useCallback(async () => {
    if (refreshTimerRef.current) clearTimeout(refreshTimerRef.current)
    try { await authService.logout() } catch { /* ignore */ }
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('user')
    setUser(null)
  }, [])

  const updateUser = (data) => {
    const updated = { ...user, ...data }
    setUser(updated)
    localStorage.setItem('user', JSON.stringify(updated))
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, updateUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}