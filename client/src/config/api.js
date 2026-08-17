/**
 * API Configuration
 * 
 * Security notes:
 * - Never expose sensitive secrets in frontend code
 * - API tokens are managed via localStorage with secure flag (HTTPS only)
 * - CSRF tokens are included in all POST/PUT/DELETE/PATCH requests
 * - All requests include timestamp to prevent cache issues
 */

export const API_BASE_URL = import.meta.env.DEV
  ? '/api'
  : (import.meta.env.VITE_API_URL || 'http://localhost/EmploySmart/server')

/**
 * Security headers and defaults
 */
export const API_DEFAULTS = {
  timeout: 15000,
  withCredentials: false,  // Don't include cookies automatically
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest', // Prevent CSRF for older browsers
  },
}

/**
 * Token storage keys (never store sensitive data here)
 */
export const TOKEN_KEYS = {
  ACCESS: 'access_token',
  REFRESH: 'refresh_token',
  CSRF: 'csrf_token',
  USER: 'user',
}

/**
 * API endpoints (for reference)
 */
export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/auth/login',
    REGISTER: '/auth/register',
    LOGOUT: '/auth/logout',
    REFRESH: '/auth/refresh',
    ME: '/auth/me',
  },
}
