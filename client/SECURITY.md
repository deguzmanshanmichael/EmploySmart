# Client-Side Security Implementation Guide

## Overview
This document outlines the security measures implemented in the EmploySmart frontend to protect user data and prevent common web vulnerabilities.

## 1. Authentication & Token Management

### JWT Token Storage
- **Access Token**: Short-lived JWT stored in localStorage
  - Expires: 15 minutes (900 seconds)
  - Automatically refreshed before expiry
- **Refresh Token**: Long-lived JWT for obtaining new access tokens
  - Expires: 7 days
  - Used only via secure API calls
- **CSRF Token**: Included in all state-changing requests (POST, PUT, DELETE, PATCH)

### Token Refresh Strategy
```javascript
// Proactive refresh 60 seconds before expiry
// Prevents expired token usage during active sessions
// Queue system handles concurrent requests during refresh
```

### Logout & Session Clearing
```javascript
// All tokens removed from localStorage
// User context cleared
// Redirect to login page
```

## 2. API Security

### Request Interceptors
✓ Automatic JWT attachment to Authorization header
✓ CSRF token inclusion in headers for state-changing requests
✓ Cache-busting timestamps on GET requests
✓ Timeout protection (15 seconds)

### Response Interceptors
✓ Network error detection with user-friendly messaging
✓ 401 (Unauthorized) handling with automatic token refresh
✓ 403 (Forbidden) redirect to unauthorized page
✓ 500+ (Server Error) redirect to error page
✓ Queue management for concurrent requests during token refresh

### Error Handling Hierarchy
1. **Network Errors** (status 0)
   - User advised to check connection
   - Retry option provided
   
2. **401 Unauthorized**
   - Attempt token refresh
   - If refresh fails, clear session and redirect to login
   
3. **403 Forbidden**
   - User lacks permissions
   - Redirect to 401/Unauthorized page
   
4. **500+ Server Errors**
   - Server-side issue
   - Redirect to error page
   - Error details logged

## 3. Route Protection

### ProtectedRoute Component
```javascript
// Checks authentication status
// Verifies role authorization
// Redirects unauthorized users appropriately
// Shows loading spinner during auth verification
```

### Role-Based Access Control (RBAC)
Routes restricted by role:
- **jobseeker**: `/jobseeker/*`
- **employer**: `/employer/*`
- **peso**: `/peso/*`
- **clcdo**: `/clcdo/*`
- **admin**: `/admin/*`

Unauthorized role access redirects to dashboard of allowed role.

## 4. Error Pages

### 401 Unauthorized
- Shown when user lacks permission or session expired
- Displays current user info and role
- Options to log out or return to dashboard

### 403 Forbidden
- Same as 401 (combined for UX)

### 404 Not Found
- Route doesn't exist
- Options to go back or home

### 500 Server Error
- Server-side issue occurred
- Retry and home options

### Network Error
- Connection failure
- Troubleshooting steps provided

### Session Expired
- Session ended due to inactivity/timeout
- Prompt to log in again

## 5. Environment Configuration

### .env File Best Practices
- Never commit `.env.local` or `.env.production`
- Use HTTPS in production
- Update API URL per environment
- Add VITE_API_URL prefix for Vite recognition

### Configuration Levels
1. `.env` - Default (can commit)
2. `.env.local` - Local overrides (gitignore'd)
3. `.env.production` - Production (gitignore'd)

## 6. Form Validation

### Client-Side Validation (Defense in Depth)
✓ Required field checking
✓ Email format validation
✓ Password strength (6+ characters)
✓ Password confirmation matching
✓ Phone number format validation (international support)
✓ ZIP code format validation (4-10 digits)
✓ Future date prevention (birth date validation)
✓ Date range validation (start/end dates)

**Note**: Client validation is for UX. Server validation is the security boundary.

## 7. XSS Protection

- No direct HTML injection
- React automatically escapes content
- Sanitize user-generated content on server
- Use CSP headers (server-side)

## 8. CSRF Protection

- CSRF token generated server-side
- Included in all state-changing request headers
- Server validates token against session

## 9. HTTP Security Headers (Server Implementation)

### Required Headers
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Content-Security-Policy: default-src 'self'
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

## 10. Data Handling

### localStorage Usage
⚠️ Limited to non-sensitive data:
- User basic info (name, role)
- Session tokens (unavoidable for SPA)
- CSRF token

### Never Store in localStorage
✗ Passwords
✗ Social security numbers
✗ Credit card data
✗ Medical records
✗ Any PII unnecessarily

## 11. Password Security

### During Registration/Change
✓ Minimum 6 characters enforced
✓ Confirmation field required
✓ Mismatch validation
✓ HTTPS transmission required

### Server-Side (Backend Responsibility)
- Bcrypt hashing with salt
- No plaintext storage
- Automatic expiry on change

## 12. Session Management

### Session Timeout
- Auto-logout on inactivity
- Session expiry page shown
- Clear cache on logout
- Prevent back button access to secured pages

### Concurrent Session Handling
- Only one active session per device
- New login invalidates previous token
- Queue system handles request conflicts

## 13. Third-Party Dependencies

### Security Measures
✓ Regular dependency updates
✓ CVE scanning on updates
✓ Minimal dependencies (react, react-router, axios, react-hot-toast, react-icons)
✓ No high-risk packages

### Update Process
```bash
npm audit
npm update [package]
npm audit fix
```

## 14. Mobile Security (Capacitor)

### Android-Specific
✓ Disable screenshot in sensitive screens (TODO)
✓ Use secure storage for sensitive tokens
✓ Implement certificate pinning
✓ HTTPS enforcement

### Recommendations
- Upgrade to HttpOnly secure storage
- Implement biometric authentication
- Add request signing for critical operations

## 15. Security Checklist

### Before Production Deployment
- [ ] Verify HTTPS is enabled
- [ ] Update API URL to production domain
- [ ] Enable security headers server-side
- [ ] Test 401/403/404/500 error pages
- [ ] Verify CSRF token generation
- [ ] Test token refresh mechanism
- [ ] Check rate limiting on API
- [ ] Review error messages (no sensitive info)
- [ ] Enable audit logging
- [ ] Set up monitoring/alerting

### Regular Maintenance
- [ ] Weekly: Check security advisories
- [ ] Monthly: Update dependencies
- [ ] Quarterly: Security audit
- [ ] Semi-annually: Penetration test

## 16. Development Guidelines

### When Adding New Routes
1. Add to AppRoutes.jsx
2. Protect with ProtectedRoute if needed
3. Specify allowedRoles array
4. Create error page if custom handling needed
5. Test with different roles

### When Adding API Calls
1. Use api.js (has interceptors)
2. Handle 401/403/500 errors
3. Show user-friendly error messages
4. Log errors for debugging
5. Test network failure scenarios

### When Handling User Data
1. Validate client-side (UX)
2. Never display sensitive data unnecessarily
3. Use sanitization functions
4. Implement proper error handling
5. Clear sensitive data on logout

## 17. References

- [OWASP Top 10](https://owasp.org/Top10/)
- [JWT Best Practices](https://tools.ietf.org/html/rfc8725)
- [Web Security Academy](https://portswigger.net/web-security)
- [React Security](https://cheatsheetseries.owasp.org/cheatsheets/React_Security_Cheat_Sheet.html)
