/**
 * API Configuration
 * 
 * Security notes:
 * - Never expose sensitive secrets in frontend code
 * - API tokens are managed via localStorage with secure flag (HTTPS only)
 * - CSRF tokens are included in all POST/PUT/DELETE/PATCH requests
 * - All requests include timestamp to prevent cache issues
 */

// Auto-detect API URL based on environment
function getAPIBaseURL() {
  if (import.meta.env.DEV) {
    // Development: use relative path for Vite proxy
    return '/api'
  }
  
  // Production: use environment variable or auto-detect from window location
  if (import.meta.env.VITE_API_URL) {
    return import.meta.env.VITE_API_URL
  }
  
  // Auto-detect for Hostinger or any server
  // Assumes API is at /api on same domain
  const protocol = window.location.protocol
  const host = window.location.host
  return `${protocol}//${host}/api`
}

export const API_BASE_URL = getAPIBaseURL()

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
