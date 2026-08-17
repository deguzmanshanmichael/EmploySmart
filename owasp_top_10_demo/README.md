# OWASP Top 10 Vulnerable Demo - Complete Guide

## 📋 Overview

This is an intentionally vulnerable job matching web application built with vanilla HTML, CSS, and JavaScript on the frontend, and PHP on the backend. It demonstrates 5 critical OWASP Top 10 vulnerabilities.

**⚠️ WARNING:** This application contains critical security vulnerabilities. **NEVER use this in production or as a template for real applications.**

---

## 🎯 Project Structure

```
owasp_top_10_demo/
├── frontend/
│   ├── index.html              # Home page with overview
│   ├── login.html              # Login form (SQL Injection)
│   ├── admin.html              # Admin panel (Broken Access Control)
│   ├── jobs.html               # Job listings (IDOR vulnerabilities)
│   └── vulnerabilities.html    # Detailed vulnerability documentation
├── backend/
│   ├── login.php               # Vulnerable login processor
│   ├── search.php              # Vulnerable search (SQL Injection)
│   └── uploads/                # File upload directory
└── README.md                   # This file
```

---

## 🔓 Vulnerability #1: SQL Injection (OWASP A03:2021)

### What is it?
SQL Injection occurs when user input is directly concatenated into SQL queries without proper sanitization, allowing attackers to inject malicious SQL code.

### Where is it in the app?
- **Frontend:** `frontend/login.html` (login form)
- **Frontend:** `frontend/jobs.html` (search form)
- **Backend:** `backend/login.php` (processes login without prepared statements)
- **Backend:** `backend/search.php` (processes search without parameterized queries)

### Vulnerable Code Example

**Backend (login.php):**
```php
$email = $_POST['email'];
$password = $_POST['password'];
// VULNERABLE - Direct concatenation!
$query = "SELECT * FROM users WHERE email = '" . $email . "' AND password = '" . $password . "'";
$result = mysqli_query($conn, $query);
```

### How to Exploit

#### Test 1: Bypass Login with SQL Injection
1. Go to `frontend/login.html`
2. In the Email field, enter: `' OR '1'='1`
3. In the Password field, enter: `anything`
4. Click Login
5. Expected: The query becomes `SELECT * FROM users WHERE email = '' OR '1'='1' AND password = 'anything'`
6. Since `'1'='1'` is always true, it bypasses authentication!

#### Test 2: Other SQL Injection Payloads
- `admin' --` (comments out password check)
- `' OR 1=1 --` (classic injection)
- `'; DROP TABLE users; --` (destructive)
- `' UNION SELECT user(),database(),version() --` (information disclosure)

#### Test 3: Search Function SQL Injection
1. Go to `frontend/jobs.html`
2. Try search: `%' OR '1'='1` to return all jobs
3. Try search: `%'; DROP TABLE jobs; --` (destructive attack)

### Security Impact
- **Severity:** CRITICAL
- Attacker can: Read all data, modify data, delete data, gain admin access
- Potential exposure: User credentials, personal data, financial information

### How to Fix

#### Secure Code Example
```php
// SECURE - Using Prepared Statements
$email = $_POST['email'];
$password = $_POST['password'];

// Using MySQLi prepared statement
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
$stmt->bind_param("ss", $email, $password); // ss = string, string
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Using PDO (alternative)
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND password = :password");
$stmt->execute(['email' => $email, 'password' => $password]);
```

#### Prevention Measures
1. ✅ Always use prepared statements with parameterized queries
2. ✅ Use ORM frameworks (Doctrine, Eloquent)
3. ✅ Implement input validation and sanitization
4. ✅ Use principle of least privilege for database users
5. ✅ Never concatenate user input into SQL queries
6. ✅ Deploy Web Application Firewall (WAF)
7. ✅ Use database activity monitoring

---

## 🔓 Vulnerability #2: Broken Access Control (OWASP A01:2021)

### What is it?
Broken Access Control occurs when authorization checks are missing or weak, allowing users to access resources or perform actions they shouldn't be able to.

### Types
1. **IDOR (Insecure Direct Object References)** - Access resources by changing IDs in URLs
2. **Missing Authorization** - No checks if user has permission
3. **Unprotected Pages** - Pages accessible without authentication

### Where is it in the app?
- **Frontend:** `frontend/admin.html` - Accessible to anyone without login!
- **Frontend:** `frontend/jobs.html` - Can edit any job by changing ID in URL
- **Concept:** User profiles accessible with sequential IDs

### Vulnerable Code Example

**admin.html:**
```html
<!-- NO AUTHENTICATION CHECK! -->
<!-- This page is visible to anyone -->
<!-- No backend verification of user role or login status -->
<div class="admin-panel">
  <h2>Admin Dashboard</h2>
  <!-- Sensitive admin functions here -->
</div>
```

### How to Exploit

#### Test 1: Access Admin Panel Without Login
1. Simply navigate to `frontend/admin.html`
2. You have full admin access without any authentication!
3. See all users, database credentials, API keys exposed in the page

#### Test 2: Edit Other Users' Jobs (IDOR)
1. Go to `frontend/jobs.html`
2. Click "Edit Job" on job_id=1
3. Notice the URL: `job-edit.html?job_id=1`
4. Try changing it to: `job-edit.html?job_id=2`, `job-edit.html?job_id=3`, etc.
5. You can edit other companies' job postings!

#### Test 3: View Other Users' Profiles
1. Look for URLs with user IDs (typically sequential)
2. Change the ID to access other users' data
3. Example: `user-profile.html?user_id=1`, then try `user_id=2`, `user_id=3`, etc.

#### Test 4: Direct API Access
1. Attackers could script direct API calls to:
   - `backend/get-user.php?id=1`
   - `backend/delete-job.php?id=1`
   - `backend/approve-admin.php?id=1`
2. No authorization check on backend API endpoints

### Security Impact
- **Severity:** CRITICAL
- Attacker can: View other users' data, modify/delete resources, escalate privileges
- Potential exposure: Confidential information, business data, financial records

### How to Fix

#### Secure Code Example
```html
<!-- SECURE - Check authentication and authorization before rendering -->
<script>
  // Check if user is authenticated
  const user = JSON.parse(localStorage.getItem('user'));
  if (!user || !user.id) {
    window.location.href = 'login.html';
  }
  
  // Check if user has admin role
  if (user.role !== 'admin') {
    window.location.href = 'unauthorized.html';
  }
</script>
```

```php
// SECURE - Backend authorization check
<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

// Check if resource belongs to user (IDOR prevention)
$user_id = $_SESSION['user_id'];
$resource_id = $_GET['id'];

$stmt = $conn->prepare("SELECT owner_id FROM jobs WHERE id = ?");
$stmt->bind_param("i", $resource_id);
$stmt->execute();
$result = $stmt->get_result();
$resource = $result->fetch_assoc();

if ($resource['owner_id'] != $user_id) {
    http_response_code(403);
    die(json_encode(['error' => 'You do not have permission to access this resource']));
}
?>
```

#### Prevention Measures
1. ✅ Always verify authentication before showing pages
2. ✅ Check authorization (user role/permissions) on server-side
3. ✅ Verify resource ownership before allowing access
4. ✅ Use role-based access control (RBAC)
5. ✅ Implement attribute-based access control (ABAC)
6. ✅ Never trust client-side checks alone
7. ✅ Log all access attempts for auditing

---

## 🔓 Vulnerability #3: Identification & Authentication Failures (OWASP A07:2021)

### What is it?
Authentication failures occur when user account security mechanisms are weak or missing, allowing attackers to compromise user accounts.

### Common Issues
1. Weak password requirements
2. No rate limiting on login attempts (brute force)
3. Weak password hashing (MD5, SHA1)
4. No account lockout mechanism
5. No multi-factor authentication (MFA)
6. Credentials exposed in logs or source code
7. Session tokens not properly validated

### Where is it in the app?
- **Frontend:** `frontend/login.html` - No CAPTCHA or rate limiting indicators
- **Backend:** `backend/login.php` - Plain text password checking
- **Frontend:** `frontend/admin.html` - Database credentials in HTML
- **Overall:** No mention of MFA or password hashing

### Vulnerable Code Example

**backend/login.php:**
```php
// VULNERABLE - Multiple authentication failures
$email = $_POST['email'];
$password = $_POST['password'];

// No rate limiting - can brute force
// No account lockout - infinite attempts allowed

// Weak password verification
$query = "SELECT * FROM users WHERE email = ? AND password = '" . md5($password) . "'";
// MD5 is cryptographically broken! Should use bcrypt/argon2

// No session token generation
$_SESSION['user_id'] = $user['id'];
// Should regenerate session ID: session_regenerate_id();
```

### Demo Credentials (Intentionally Weak)
- **Email:** employer@joblink.com
- **Password:** password123 (simple, guessable)

- **Email:** admin@joblink.com
- **Password:** admin123 (simple, default-like)

### How to Exploit

#### Test 1: Brute Force Login
1. Go to `frontend/login.html`
2. Try common password combinations:
   - admin@joblink.com / admin
   - admin@joblink.com / admin123
   - employer@joblink.com / password
   - employer@joblink.com / password123
3. No rate limiting means unlimited attempts
4. Add CAPTCHA to stop automated attacks

#### Test 2: Default Credentials
1. Many applications use default accounts
2. Try credentials like:
   - admin/admin
   - admin/12345
   - admin/password

#### Test 3: Weak Password Requirements
1. Check if passwords like "123" or "pass" are accepted
2. No complexity requirements (uppercase, numbers, symbols)
3. No minimum length enforcement

#### Test 4: Exposed Credentials
1. Open `frontend/admin.html`
2. View page source
3. Find database password: `password123` - plaintext in HTML!
4. Find API key in JavaScript: `api_key_12345_exposed`

#### Test 5: Session Hijacking
1. Get session cookie from one user
2. Use it in another browser to impersonate user
3. No CSRF token or session rotation

### Security Impact
- **Severity:** CRITICAL
- Attacker can: Access user accounts, steal data, escalate privileges
- Potential exposure: Personal information, financial data, account takeover

### How to Fix

#### Secure Code Example
```php
<?php
// SECURE - Proper authentication implementation

// 1. Rate limiting
require 'rate_limiter.php';
$email = $_POST['email'];

if (isRateLimited($email, $max_attempts = 5, $time_window = 900)) { // 5 attempts per 15 min
    http_response_code(429);
    die(json_encode(['error' => 'Too many login attempts. Try again later.']));
}

// 2. Get user with prepared statement
$stmt = $conn->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// 3. Verify password using bcrypt
if ($user && password_verify($_POST['password'], $user['password_hash'])) {
    // 4. Create secure session
    session_regenerate_id(true); // Prevent session fixation
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['created_at'] = time();
    
    // 5. Set secure cookie flags
    setcookie('PHPSESSID', session_id(), [
        'expires' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,      // HTTPS only
        'httponly' => true,    // No JavaScript access
        'samesite' => 'Strict' // CSRF protection
    ]);
    
    echo json_encode(['success' => true]);
} else {
    recordFailedAttempt($email);
    http_response_code(401);
    die(json_encode(['error' => 'Invalid credentials']));
}
?>
```

#### Prevention Measures
1. ✅ Use strong password hashing (bcrypt, Argon2, scrypt)
2. ✅ Enforce password policy (min 12 chars, complexity)
3. ✅ Implement rate limiting on login attempts
4. ✅ Add account lockout after N failed attempts
5. ✅ Implement multi-factor authentication (2FA/MFA)
6. ✅ Add CAPTCHA to prevent automated attacks
7. ✅ Regenerate session IDs after login
8. ✅ Use secure cookie flags (HttpOnly, Secure, SameSite)
9. ✅ Never expose credentials in source code
10. ✅ Implement session timeout (15-30 minutes)

---

## 🔓 Vulnerability #4: Security Misconfiguration (OWASP A05:2021)

### What is it?
Security Misconfiguration occurs when security settings are not properly configured, exposing sensitive information or allowing unauthorized access.

### Common Issues
1. Exposed secrets (API keys, database passwords)
2. Debug information visible in error pages
3. Directory listing enabled
4. Default credentials not changed
5. Weak CORS policies
6. Security headers missing
7. Sensitive files accessible

### Where is it in the app?
- **Frontend:** `frontend/admin.html` - Database credentials hardcoded in HTML
- **Frontend:** `frontend/admin.html` - API keys exposed in JavaScript
- **Backend:** `backend/login.php` - Error reporting enabled
- **Backend:** CORS headers allow all origins

### Vulnerable Code Example

**admin.html - Exposed in HTML:**
```html
<!-- VULNERABLE - Hardcoded credentials in HTML! -->
<input type="text" value="localhost" readonly>
<input type="text" value="root" readonly>
<input type="text" value="password123" readonly>
<input type="text" value="super_secret_key_12345_exposed_in_source" readonly>
```

**admin.html - Exposed in JavaScript:**
```javascript
// VULNERABLE - API credentials in source code
const API_KEY = 'api_key_12345_exposed';
const API_URL = 'http://localhost:8000/backend/';
console.log('Admin panel loaded - using API:', API_URL);
```

### How to Exploit

#### Test 1: Find Exposed Database Credentials
1. Open `frontend/admin.html`
2. Right-click → "View Page Source"
3. Scroll down or search for "Database Configuration"
4. Find:
   - DB Host: localhost
   - DB User: root
   - DB Pass: password123
   - DB Name: joblink_demo
5. Attacker can now directly connect to the database!

#### Test 2: Find API Keys in JavaScript
1. Open `frontend/admin.html`
2. Press F12 to open Developer Console
3. Look at Network tab or search for API_KEY
4. Find: `api_key_12345_exposed`
5. Attacker can make API calls using these credentials

#### Test 3: Check Page Source for Comments
1. View page source of any page
2. Look for HTML comments with sensitive info
3. Look for JavaScript comments with API endpoints
4. Search for "TODO", "FIXME", "HACK", "DEBUG"

#### Test 4: Check for Debug Information
1. Go to `backend/login.php`
2. Try to trigger an error
3. Error messages might reveal:
   - File paths and structure
   - Database details
   - PHP version
   - Stack traces

#### Test 5: Check CORS Configuration
1. `backend/search.php` has: `header('Access-Control-Allow-Origin: *');`
2. This allows ANY website to access the API
3. Should restrict to trusted origins only

### Security Impact
- **Severity:** HIGH
- Attacker can: Access databases, use APIs, find vulnerabilities
- Potential exposure: Complete system compromise

### How to Fix

#### Secure Code Example
```php
<?php
// SECURE - Using environment variables

// .env file (NEVER committed to git)
// DB_HOST=localhost
// DB_USER=db_user_secure
// DB_PASS=complex_password_here
// API_KEY=secure_api_key_here
// JWT_SECRET=secure_jwt_secret

// Load environment variables
$db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$db_user = $_ENV['DB_USER'] ?? getenv('DB_USER');
$db_pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');

// CORS configuration - Restrict to known origins
$allowed_origins = [
    'https://joblink.com',
    'https://www.joblink.com',
    'https://app.joblink.com'
];

$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($request_origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $request_origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Credentials: true');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'');

// Error handling - Don't expose details
if (error_occurs) {
    error_log('Error details here');  // Log for admins only
    http_response_code(500);
    die(json_encode(['error' => 'An error occurred. Please try again.'])); // Generic message
}
?>
```

#### Prevention Measures
1. ✅ Store secrets in environment variables (.env)
2. ✅ Use secrets management (Azure Key Vault, AWS Secrets Manager)
3. ✅ Remove debug code before deployment
4. ✅ Implement security headers (CSP, HSTS, etc.)
5. ✅ Configure CORS to trusted origins only
6. ✅ Disable directory listing
7. ✅ Hide error details from users
8. ✅ Use HTTPS only
9. ✅ Disable default accounts
10. ✅ Remove unnecessary files and endpoints

---

## 🔓 Vulnerability #5: Vulnerable & Outdated Components (OWASP A06:2021)

### What is it?
Vulnerable and Outdated Components occur when using third-party libraries with known security vulnerabilities.

### Common Issues
1. Outdated versions of libraries
2. Known CVEs in dependencies
3. Unmaintained packages
4. Libraries included without review
5. No dependency scanning

### Where is it in the app?
- **Frontend:** `index.html` includes jQuery 1.12.4 (from 2016)
- Potential: Bootstrap 3.x, old Angular, old React versions
- Backend: Outdated PHP version

### Vulnerable Code Example

**index.html:**
```html
<!-- VULNERABLE - jQuery 1.12.4 from 2016! -->
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<!-- This version has known XSS vulnerabilities: CVE-2020-11022, CVE-2020-11023 -->
```

### Known Vulnerabilities in jQuery 1.12.4

| CVE ID | Severity | Description |
|--------|----------|-------------|
| CVE-2020-11022 | HIGH | Untrusted code execution via `<option>` tag |
| CVE-2020-11023 | HIGH | Regular Expression Denial of Service (ReDoS) |

### How to Exploit

#### Test 1: Check for Outdated Libraries
1. Open any page source
2. Look for script and link tags
3. Identify jQuery version: 1.12.4
4. Check release date: June 2016 (10 years old!)
5. This is extremely outdated

#### Test 2: Check for Known Vulnerabilities
1. Search online for "jQuery 1.12.4 CVE"
2. Find CVE-2020-11022 and CVE-2020-11023
3. Read vulnerability details
4. These could allow XSS attacks

#### Test 3: Check Other Dependencies
1. Look for all script imports
2. Research versions and known vulnerabilities
3. Use tools like:
   - npm audit (for Node packages)
   - OWASP Dependency-Check
   - Snyk
   - Black Duck

#### Test 4: Potential XSS via jQuery
1. jQuery 1.12.4 has unsafe HTML parsing
2. Attacker could craft malicious input
3. Could execute arbitrary JavaScript

### Security Impact
- **Severity:** MEDIUM to HIGH
- Attacker can: Exploit known vulnerabilities, inject code
- Potential exposure: Depends on specific CVE

### How to Fix

#### Secure Code Example
```html
<!-- SECURE - Updated to latest jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Alternative: Use modern JavaScript instead of jQuery -->
<script>
  // Modern JavaScript instead of jQuery
  document.querySelectorAll('.button').forEach(btn => {
    btn.addEventListener('click', function() {
      // Do something
    });
  });
</script>

<!-- package.json (for Node.js projects) -->
{
  "dependencies": {
    "jquery": "^3.7.0",           // Latest stable
    "bootstrap": "^5.3.0",        // Latest Bootstrap 5
    "axios": "^1.4.0",            // Keep dependencies updated
    "express": "^4.18.0"
  },
  "devDependencies": {
    "npm-check-updates": "^16.0.0"
  }
}

<!-- Security scanning in CI/CD -->
# .github/workflows/security.yml
- name: Check dependencies
  run: npm audit --audit-level=moderate
  
- name: Scan with Snyk
  run: snyk test
  
- name: OWASP Dependency-Check
  run: dependency-check --project "JobLink" --scan .
```

#### Prevention Measures
1. ✅ Regularly audit dependencies
2. ✅ Keep libraries updated to latest stable versions
3. ✅ Monitor for CVEs in used components
4. ✅ Use dependency scanning tools:
   - npm audit
   - pip audit
   - composer audit
   - Snyk
   - OWASP Dependency-Check
5. ✅ Integrate scanning into CI/CD pipeline
6. ✅ Remove unused dependencies
7. ✅ Use Software Bill of Materials (SBOM)
8. ✅ Prefer maintained and popular libraries
9. ✅ Test updates before deploying
10. ✅ Set up automated dependency updates (Dependabot)

---

## 🚀 How to Run the Demo

### Setup

1. **Navigate to the project folder:**
   ```bash
   cd c:\xampp\htdocs\EmploySmart\owasp_top_10_demo
   ```

2. **Using PHP built-in server:**
   ```bash
   cd backend
   php -S localhost:8000
   ```
   Then visit: `http://localhost:8000/frontend/index.html`

3. **Using Apache (XAMPP):**
   - Place folder in `c:\xampp\htdocs\`
   - Visit: `http://localhost/EmploySmart/owasp_top_10_demo/frontend/index.html`

### Testing the Vulnerabilities

1. **Start with the home page** (`index.html`)
2. **Click through each demo section** to understand each vulnerability
3. **Try the attack examples** to see how each vulnerability works
4. **Read the detailed documentation** (`vulnerabilities.html`) for each issue

---

## 📚 Learning Resources

### OWASP References
- [OWASP Top 10 2021](https://owasp.org/Top10/)
- [OWASP Top 10 2024](https://owasp.org/Top10/)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [OWASP Cheat Sheets](https://cheatsheetseries.owasp.org/)

### Tools for Testing
- **SQL Injection:** SQLMap, Burp Suite
- **Authentication:** Burp Suite, Postman
- **Dependency Scanning:** npm audit, Snyk, OWASP Dependency-Check
- **Web Scanning:** OWASP ZAP, Burp Suite Community
- **CORS Testing:** Postman, browser dev tools

### Online Learning
- OWASP WebGoat
- HackTheBox
- TryHackMe
- PortSwigger Web Security Academy

---

## 🔒 Security Best Practices Summary

### Development
- ✅ Use prepared statements for all database queries
- ✅ Validate and sanitize all user input
- ✅ Implement role-based access control (RBAC)
- ✅ Never trust client-side security checks
- ✅ Use strong password hashing (bcrypt/Argon2)
- ✅ Implement rate limiting on sensitive operations
- ✅ Add logging and monitoring for security events
- ✅ Keep dependencies updated
- ✅ Use security scanning tools in CI/CD

### Deployment
- ✅ Use environment variables for secrets
- ✅ Enable HTTPS/TLS only
- ✅ Set security headers (CSP, HSTS, X-Frame-Options)
- ✅ Configure CORS properly
- ✅ Disable debug mode in production
- ✅ Use Web Application Firewall (WAF)
- ✅ Regular security audits and penetration testing
- ✅ Keep servers and software updated
- ✅ Monitor logs for suspicious activity

---

## ⚠️ Important Disclaimer

**This application is intentionally vulnerable for educational purposes only. Do NOT:**
- Use this code as a template for production applications
- Deploy this to the internet
- Use these coding patterns in real projects
- Share this with non-technical audiences without context

**This demo should only be used by:**
- Security professionals for training
- Developers learning about security
- Students in cybersecurity courses
- Organizations conducting security awareness training

---

## 📝 License and Credits

Created for educational purposes to teach OWASP Top 10 vulnerabilities.

For questions or improvements, refer to OWASP official documentation and security best practices guides.

---

## 📞 Further Help

If you want to see these vulnerabilities fixed, refer to the original EmploySmart application codebase which demonstrates proper security implementations:
- `SECURITY_IMPROVEMENTS.md` - Security enhancements
- `SECURITY.md` - Security documentation
- `SECURITY_HARDENING.md` - Backend hardening details
