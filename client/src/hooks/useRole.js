import { useAuth } from '../context/AuthContext'

/**
 * useRole — returns the current user's role and role-check helpers
 */
export function useRole() {
  const { user } = useAuth()
  const role = user?.role ?? null

  return {
    role,
    isAdmin:     role === 'admin',
    isPeso:      role === 'peso',
    isClcdo:     role === 'clcdo',
    isEmployer:  role === 'employer',
    isJobseeker: role === 'jobseeker',
    isStaff:     ['admin', 'peso', 'clcdo'].includes(role),
    hasRole: (...roles) => roles.includes(role),
  }
}

export default useRole