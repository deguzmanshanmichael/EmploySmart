/**
 * roleUtils.js
 * Utility helpers for role-based display and routing logic
 */

export const ROLES = {
  ADMIN:     'admin',
  PESO:      'peso',
  CLCDO:     'clcdo',
  EMPLOYER:  'employer',
  JOBSEEKER: 'jobseeker',
}

export const ROLE_LABELS = {
  admin:     'Administrator',
  peso:      'PESO Officer',
  clcdo:     'CLCDO Staff',
  employer:  'Employer',
  jobseeker: 'Job Seeker',
}

export const ROLE_COLORS = {
  admin:     { badge: 'badge-red',    bg: 'bg-red-50 text-red-700' },
  peso:      { badge: 'badge-green',  bg: 'bg-green-50 text-green-700' },
  clcdo:     { badge: 'badge-yellow', bg: 'bg-yellow-50 text-yellow-700' },
  employer:  { badge: 'badge-purple', bg: 'bg-purple-50 text-purple-700' },
  jobseeker: { badge: 'badge-blue',   bg: 'bg-blue-50 text-blue-700' },
}

export const ROLE_HOME_ROUTES = {
  admin:     '/admin',
  peso:      '/peso',
  clcdo:     '/clcdo',
  employer:  '/employer',
  jobseeker: '/jobseeker',
}

/** Returns the home dashboard route for a given role */
export function getRoleHome(role) {
  return ROLE_HOME_ROUTES[role] ?? '/login'
}

/** Returns the display label for a role */
export function getRoleLabel(role) {
  return ROLE_LABELS[role] ?? role
}

/** Returns badge and bg class strings for a role */
export function getRoleColor(role) {
  return ROLE_COLORS[role] ?? { badge: 'badge-gray', bg: 'bg-gray-100 text-gray-600' }
}

/** Returns true if the role is a staff/internal role */
export function isStaffRole(role) {
  return ['admin', 'peso', 'clcdo'].includes(role)
}

/** Returns true if the given user has permission for any of the given roles */
export function hasPermission(user, allowedRoles) {
  if (!user || !allowedRoles) return false
  return allowedRoles.includes(user.role)
}