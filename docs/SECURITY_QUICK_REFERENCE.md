# Security Improvements - Quick Reference

## ✅ What Changed

### Backend Security Enhancements

| Feature | Details | Where |
|---------|---------|-------|
| **Input Validation** | Email, password, date, positive int, enum | `server/helpers/validator.php` |
| **Sanitization** | HTML encoding, tag stripping | `server/helpers/validator.php` |
| **CSRF Protection** | Token required for mutations | `server/middleware/AuthMiddleware.php` |
| **Rate Limiting** | 5-20 requests per 15min by endpoint | `server/controllers/AuthController.php` |
| **Role Restrictions** | Only admin can create staff accounts | `server/controllers/AuthController.php` |
| **Security Headers** | CSP, X-Frame-Options, HSTS, etc. | `server/config/cors.php` |
| **Audit Logging** | LOGIN, REGISTER, FAILED_LOGIN events | `server/controllers/AuthController.php` |
| **Config Security** | JWT_SECRET required (no defaults) | `server/config/jwt.php` |

### Frontend Security Enhancements

| Feature | Details | Where |
|---------|---------|-------|
| **Form Validation** | Email, phone, ZIP regex checks | `client/src/pages/auth/Register.jsx` |
| **CSRF Token** | Auto-included in mutation requests | `client/src/services/api.js` |
| **Token Management** | CSRF tokens stored and refreshed | `client/src/context/AuthContext.jsx` |
| **Input Trimming** | Strip whitespace in validation | `client/src/pages/auth/Register.jsx` |

---

## 🔒 Key Security Patterns

### 1. Validation Pattern

```php
// Backend - Validate before using
$errors = validateRequired($data, ['email', 'password']);
if (!validateEmail($data['email'])) sendError('Invalid email', 422);
if (!validatePassword($data['password'])) sendError('Password too short', 422);
```

```javascript
// Frontend - Validate before submission
if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
  e.email = 'Invalid email'
}
```

### 2. CSRF Protection Pattern

```php
// Backend - Require CSRF for mutations
$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
  $csrfHeader = $headers['X-CSRF-Token'] ?? '';
  if (empty($csrfHeader)) sendError('CSRF token required', 403);
}
```

```javascript
// Frontend - Include CSRF token in mutations
const csrfToken = localStorage.getItem('csrf_token')
if (csrfToken && ['post', 'put', 'delete', 'patch'].includes(method)) {
  config.headers['X-CSRF-Token'] = csrfToken
}
```

### 3. Role Authorization Pattern

```php
// Only admin can create staff accounts
$authPayload = VerifyToken::tryCheck();
if ($data['role'] === 'admin' && (!$authPayload || $authPayload['role'] !== 'admin')) {
  sendError('Unauthorized', 403);
}
```

### 4. Rate Limiting Pattern

```php
// Prevent brute force attacks
if (!throttleRequest('login:' . md5($email . ':' . $ip), 8, 900)) {
  sendError('Too many attempts', 429);
}
```

### 5. Audit Logging Pattern

```php
// Log security events
$this->logAction($userId, 'LOGIN');
// Includes: timestamp, user_id, action, IP, user agent
```

---

## 🧪 Quick Testing

### Test Registration Validation
```bash
curl -X POST http://192.168.83.250/EmploySmart/server/auth/register \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Juan","last_name":"Dela Cruz","email":"invalid-email","password":"pass123","sex":"male","role":"jobseeker"}'
# Expected: 422 Invalid email address
```

### Test Rate Limiting
```bash
# Run 9 times to hit rate limit
for i in {1..9}; do
  curl -X POST http://192.168.83.250/EmploySmart/server/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@example.com","password":"wrong"}'
done
# Attempts 1-8: 401
# Attempt 9: 429 Too many login attempts
```

### Test CSRF Protection
```bash
# Without CSRF token (should fail)
curl -X PUT http://192.168.83.250/EmploySmart/server/users/1 \
  -H "Authorization: Bearer [token]" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Updated"}'
# Expected: 403 CSRF token required
```

### Test Security Headers
```bash
curl -I http://192.168.83.250/EmploySmart/server/
# Look for: X-Content-Type-Options, X-Frame-Options, Content-Security-Policy
```

---

## 📋 Pre-Deployment Checklist

- [ ] `.env` file configured with JWT_SECRET
- [ ] Database connection working
- [ ] CORS_ALLOWED_ORIGINS set to correct dev/prod URLs
- [ ] Client rebuilt: `cd client && npm run build`
- [ ] No compilation errors in frontend or backend
- [ ] System logs table exists in database
- [ ] Refresh tokens table exists in database
- [ ] Test login/register/logout workflow
- [ ] Verify CSRF tokens in login response
- [ ] Check security headers in response

---

## 📊 Configuration Summary

### Environment Variables Required

```env
JWT_SECRET=your_secure_random_string_here    # 🔴 MUST SET - No default
CORS_ALLOWED_ORIGINS=http://your.origin      # Whitelist only
```

### Optional (Defaults provided)

```env
JWT_ACCESS_EXPIRE=900                        # 15 minutes
JWT_REFRESH_EXPIRE=604800                    # 7 days
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=employsmart
```

---

## 🚨 Important Notes

### For Developers

1. **Always validate on backend**: Frontend validation is for UX, not security
2. **Use prepared statements**: Already implemented in all queries
3. **Sanitize user input**: Done automatically in `getJsonBody()` and `getQueryParam()`
4. **Check CSRF token**: Required for POST/PUT/DELETE/PATCH
5. **Rate limiting is per-endpoint**: Not global (allows legitimate use)

### For DevOps

1. **Generate strong JWT_SECRET**: Use cryptographic randomness
2. **Rotate secrets periodically**: Update JWT_SECRET every 90 days
3. **Monitor logs table**: Watch for suspicious login attempts
4. **Enable HTTPS**: Required for production deployment
5. **Regular backups**: Critical for refresh_tokens table

### For Testers

1. **Test invalid inputs**: Check all validation rules
2. **Test missing tokens**: Verify CSRF check works
3. **Test rate limits**: Confirm throttling activates
4. **Test CORS**: From allowed and disallowed origins
5. **Check logs**: Verify audit trail records events

---

## 📞 Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| 500 error on startup | Check JWT_SECRET in .env |
| 403 CSRF errors | Include X-CSRF-Token header from login response |
| 429 rate limit | Wait 15 minutes or clear rate limiter cache |
| 401 authorization | Token expired; use refresh endpoint |
| CORS blocked | Add origin to CORS_ALLOWED_ORIGINS in .env |
| Validation errors | Check field types and formats in error response |

---

## 📚 Additional Resources

- Full documentation: `docs/SECURITY_IMPROVEMENTS.md`
- API documentation: `docs/API_DOCUMENTATION.md`
- System architecture: `docs/SYSTEM_ARCHITECTURE.md`
- Test cases: See Testing Guide section in full documentation

---

**Version:** 2.0 | **Last Updated:** May 5, 2026
