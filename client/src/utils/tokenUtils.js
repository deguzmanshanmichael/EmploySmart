/**
 * tokenUtils.js
 * Utilities for JWT token storage and inspection
 */

const ACCESS_KEY  = 'access_token'
const REFRESH_KEY = 'refresh_token'
const USER_KEY    = 'user'

/** Store both tokens and user data after login */
export function storeTokens(accessToken, refreshToken, user) {
  localStorage.setItem(ACCESS_KEY,  accessToken)
  localStorage.setItem(REFRESH_KEY, refreshToken)
  if (user) localStorage.setItem(USER_KEY, JSON.stringify(user))
}

/** Clear all auth data from storage */
export function clearTokens() {
  localStorage.removeItem(ACCESS_KEY)
  localStorage.removeItem(REFRESH_KEY)
  localStorage.removeItem(USER_KEY)
}

/** Get the raw access token string */
export function getAccessToken() {
  return localStorage.getItem(ACCESS_KEY)
}

/** Get the raw refresh token string */
export function getRefreshToken() {
  return localStorage.getItem(REFRESH_KEY)
}

/** Get the stored user object */
export function getStoredUser() {
  try {
    const raw = localStorage.getItem(USER_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

/**
 * Decode a JWT payload (without verification — verification happens server-side).
 * Returns null if the token is malformed.
 */
export function decodeJWT(token) {
  try {
    const parts = token.split('.')
    if (parts.length !== 3) return null
    const payload = atob(parts[1].replace(/-/g, '+').replace(/_/g, '/'))
    return JSON.parse(payload)
  } catch {
    return null
  }
}

/**
 * Returns the number of seconds until the access token expires.
 * Returns 0 if expired or unreadable.
 */
export function getTokenTTL() {
  const token = getAccessToken()
  if (!token) return 0
  const payload = decodeJWT(token)
  if (!payload?.exp) return 0
  return Math.max(0, payload.exp - Math.floor(Date.now() / 1000))
}

/** Returns true if the access token is present and not expired */
export function isTokenValid() {
  return getTokenTTL() > 0
}

/** Returns true if the access token will expire within the given seconds */
export function isTokenExpiringSoon(withinSeconds = 120) {
  const ttl = getTokenTTL()
  return ttl > 0 && ttl <= withinSeconds
}