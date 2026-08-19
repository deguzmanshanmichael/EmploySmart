import axios from 'axios'
import { API_BASE_URL } from '../config/api.js'

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 15000,
  withCredentials: false,
})

// ─── Request interceptor: attach access token & CSRF ───────────────────────
api.interceptors.request.use(
  (config) => {
    config.headers = config.headers || {}
    const token = localStorage.getItem('access_token')
    if (token) config.headers.Authorization = `Bearer ${token}`

    config.headers['X-Requested-With'] = config.headers['X-Requested-With'] || 'XMLHttpRequest'

    const csrfToken = localStorage.getItem('csrf_token')
    if (csrfToken && ['post', 'put', 'delete', 'patch'].includes(config.method?.toLowerCase())) {
      config.headers['X-CSRF-Token'] = csrfToken
    }
    
    // Prevent caching of GET requests
    if (config.method === 'get') {
      config.params = config.params || {}
      config.params['_t'] = new Date().getTime()
    }
    
    return config
  },
  (error) => Promise.reject(error)
)

// ─── Response interceptor: handle errors & auto-refresh on 401 ──────────────
let isRefreshing = false
let failedQueue = []

const processQueue = (error, token = null) => {
  failedQueue.forEach((prom) => {
    if (error) prom.reject(error)
    else prom.resolve(token)
  })
  failedQueue = []
}

function clearAuthAndRedirect() {
  localStorage.removeItem('access_token')
  localStorage.removeItem('refresh_token')
  localStorage.removeItem('csrf_token')
  localStorage.removeItem('user')
  window.location.href = '/login'
}

function redirectToError(status) {
  if (status === 401) window.location.href = '/401'
  else if (status === 403) window.location.href = '/403'
  else if (status === 500) window.location.href = '/500'
}

api.interceptors.response.use(
  (response) => {
    console.log('✅ API Response Success:', response.status, response.data)
    return response
  },
  async (error) => {
    console.error('❌ API Response Error:', error.response?.status, error.response?.data, error.message)

    // Network error (no response received)
    if (!error.response) {
      const networkError = {
        ...error,
        status: 0,
        isNetworkError: true,
        message: `Network Error: Cannot connect to server. Please check:\n` +
                `• Your internet connection\n` +
                `• Server is running and reachable\n` +
                `• Firewall settings allow connections`,
      }
      console.error('🌐 Network error:', networkError)
      return Promise.reject(networkError)
    }

    const status = error.response?.status
    const originalRequest = error.config

    // Landing settings are optional; do not send public visitors to the 500 page
    // when the database is temporarily unavailable during hosting setup.
    if (originalRequest?.url?.includes('/settings/landing')) {
      return Promise.reject(error)
    }

    // 401 Unauthorized - try to refresh token
    if (status === 401 && !originalRequest._retry) {
      const isAuthRoute = originalRequest.url?.includes('/auth/')
      const isLoginLikeRoute = ['/auth/login', '/auth/register', '/auth/refresh', '/auth/logout'].some((path) => originalRequest.url?.includes(path))

      // Keep login/register/refresh/logout failures visible to the caller instead of forcing a redirect loop.
      if (isAuthRoute && isLoginLikeRoute) {
        return Promise.reject(error)
      }

      if (isAuthRoute) {
        clearAuthAndRedirect()
        return Promise.reject(error)
      }

      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        })
          .then((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`
            return api(originalRequest)
          })
          .catch((err) => Promise.reject(err))
      }

      originalRequest._retry = true
      isRefreshing = true

      const refreshToken = localStorage.getItem('refresh_token')
      if (!refreshToken) {
        isRefreshing = false
        clearAuthAndRedirect()
        return Promise.reject(error)
      }

      try {
        const res = await axios.post(`${API_BASE_URL}/auth/refresh`, { refresh_token: refreshToken })
        const { access_token, refresh_token: newRefresh, csrf_token, expires_in, user: userData } = res.data.data
        
        localStorage.setItem('access_token', access_token)
        localStorage.setItem('refresh_token', newRefresh)
        if (csrf_token) localStorage.setItem('csrf_token', csrf_token)
        if (userData) localStorage.setItem('user', JSON.stringify(userData))
        
        api.defaults.headers.common.Authorization = `Bearer ${access_token}`
        processQueue(null, access_token)
        
        originalRequest.headers.Authorization = `Bearer ${access_token}`
        return api(originalRequest)
      } catch (refreshError) {
        processQueue(refreshError, null)
        isRefreshing = false
        clearAuthAndRedirect()
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    // 403 Forbidden
    if (status === 403) {
      console.warn('🚫 Forbidden: User lacks permissions for this resource')
      redirectToError(403)
      return Promise.reject({
        status: 403,
        message: 'Access Forbidden: You do not have permission to access this resource.',
        data: error.response?.data,
      })
    }

    // 500+ Server errors
    if (status >= 500) {
      console.error('💥 Server error:', status, error.response?.data)
      redirectToError(500)
      return Promise.reject({
        status,
        message: error.response?.data?.message || 'Server error. Please try again later.',
        data: error.response?.data,
      })
    }

    return Promise.reject(error)
  }
)

export default api