/**
 * permissionUtils.js
 * Fine-grained permission checks across the EmploySmart system.
 */

/**
 * Feature permission map.
 * Each key is a feature; value is an array of roles that can access it.
 */
export const PERMISSIONS = {
  // Jobs
  'jobs.browse':        ['jobseeker', 'employer', 'peso', 'admin'],
  'jobs.apply':         ['jobseeker'],
  'jobs.create':        ['employer'],
  'jobs.approve':       ['peso', 'admin'],
  'jobs.delete':        ['employer', 'peso', 'admin'],
  'jobs.view_pending':  ['peso', 'admin'],

  // Applications
  'applications.view_own':    ['jobseeker'],
  'applications.view_all':    ['employer', 'peso', 'admin'],
  'applications.update_status': ['employer', 'peso', 'admin'],
  'applications.withdraw':    ['jobseeker'],

  // Employers
  'employers.verify':   ['peso', 'admin'],
  'employers.view_all': ['peso', 'admin'],

  // Training
  'training.create':    ['clcdo', 'admin'],
  'training.enroll':    ['clcdo', 'peso', 'admin'],
  'training.complete':  ['clcdo', 'admin'],
  'training.view_all':  ['clcdo', 'peso', 'admin', 'jobseeker'],

  // Skills
  'skills.manage':      ['admin', 'peso', 'clcdo'],
  'skills.view':        ['jobseeker', 'employer', 'peso', 'clcdo', 'admin'],

  // Users
  'users.view_all':     ['admin', 'peso', 'clcdo'],
  'users.verify':       ['admin', 'peso'],
  'users.delete':       ['admin'],

  // Reports
  'reports.peso':       ['peso', 'admin'],
  'reports.clcdo':      ['clcdo', 'admin'],
  'reports.platform':   ['admin'],

  // System
  'system.logs':        ['admin'],
  'system.roles':       ['admin'],
}

/**
 * Returns true if the given role has permission for the given feature.
 * @param {string} role  - e.g. 'jobseeker'
 * @param {string} feature - e.g. 'jobs.apply'
 */
export function can(role, feature) {
  const allowed = PERMISSIONS[feature]
  if (!allowed) return false
  return allowed.includes(role)
}

/**
 * Returns true if the user object has permission for the feature.
 * @param {object} user
 * @param {string} feature
 */
export function userCan(user, feature) {
  if (!user?.role) return false
  return can(user.role, feature)
}

/**
 * Returns all features a given role can access.
 */
export function getPermissionsForRole(role) {
  return Object.entries(PERMISSIONS)
    .filter(([, roles]) => roles.includes(role))
    .map(([feature]) => feature)
}