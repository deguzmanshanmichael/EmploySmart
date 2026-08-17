# Security Hardening Summary

This document describes the backend security hardening changes made to the EmploySmart application and how to verify them in the system.

## What Was Hardened

### 1. Environment-based configuration
- Added `server/config/env.php` to load application secrets and settings from environment variables.
- Replaced hardcoded database and JWT values in:
  - `server/config/database.php`
  - `server/config/jwt.php`
- Added `.env.example` to document required runtime configuration.
- Updated `.gitignore` to ignore `.env` and local sensitive files.

### 2. Secure database access
- Ensured prepared statements are used for all dynamic SQL input in critical controllers.
- Fixed raw SQL uses in:
  - `server/controllers/EmployerController.php`
  - `server/controllers/TrainingController.php`
  - `server/controllers/JobController.php`
  - `server/controllers/UserController.php`
- Replaced direct `$_GET[...]` reads with sanitized query helpers in list and filter endpoints.

### 3. Input validation and sanitization
- Improved request sanitization inside `server/helpers/validator.php`.
- Added recursive cleaning of JSON payloads and query parameters.
- Ensures user-provided values are normalized before usage.

### 4. Rate limiting and brute-force protection
- Added `server/helpers/rate_limiter.php` for lightweight throttling.
- Added rate limits to authentication flows in `server/controllers/AuthController.php`:
  - Registration
  - Login
  - Refresh token requests

### 5. Security headers and CORS hardening
- Hardened response headers in `server/config/cors.php`.
- Added protections for:
  - `X-Content-Type-Options`
  - `X-Frame-Options`
  - `X-XSS-Protection`
  - `Referrer-Policy`
  - `Content-Security-Policy`
- Added origin restriction support via `ALLOWED_ORIGINS`.

### 6. Auth token security
- JWT secret and expiry values now come from the environment.
- Refresh token flow now rotates tokens and deletes expired refresh tokens regularly.
- Auth controller tracks login/logout activity for better logging.

## Files Changed

- `server/config/env.php`
- `server/config/database.php`
- `server/config/jwt.php`
- `server/config/cors.php`
- `server/helpers/validator.php`
- `server/helpers/rate_limiter.php`
- `server/index.php`
- `server/controllers/AuthController.php`
- `server/controllers/EmployerController.php`
- `server/controllers/TrainingController.php`
- `server/controllers/JobController.php`
- `server/controllers/UserController.php`
- `.env.example`
- `.gitignore`

## Required Environment Variables

Use `.env.example` as the template and set secure values in a local `.env` file or your server environment.

Required values:
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `JWT_SECRET`
- `JWT_ACCESS_EXPIRES`
- `JWT_REFRESH_EXPIRES`
- `JWT_ALGO`
- `ALLOWED_ORIGINS`
- `RATE_LIMIT_CACHE_SIZE`
- `RATE_LIMIT_WINDOW_SECONDS`
- `RATE_LIMIT_MAX_REQUESTS`

## How to Test the Hardening

### 1. Run the backend

Start the PHP backend in your local environment, ensuring `server/index.php` is the entrypoint.

Example command from the project root if using the built-in PHP server:

```powershell
cd c:\xampp\htdocs\EmploySmart\server
php -S 192.168.83.250:8000
```

Adjust to your existing PHP/Apache setup if needed.

### 2. Verify environment loading

- Confirm the backend starts without hardcoded secret errors.
- Check that `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `JWT_SECRET` are read from `.env` or your environment.

### 3. Verify security headers

Request any backend route and confirm the following headers are present:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy` with default restrictions

### 4. Verify CORS restrictions

- Confirm only configured `ALLOWED_ORIGINS` are accepted.
- Confirm preflight `OPTIONS` requests succeed for allowed origins.
- Confirm invalid origins are rejected or blocked by the browser.

### 5. Test auth protections

#### Registration
- `POST /auth/register` with valid data should create a user.
- Attempt with missing or invalid fields should return a validation error.
- Repeated registration attempts from the same IP/email should be rate limited.

#### Login
- `POST /auth/login` with valid credentials should return tokens.
- Invalid credentials should return a generic login error.
- Repeated failed logins from the same IP should trigger throttling.

#### Refresh and logout
- `POST /auth/refresh` should rotate the refresh token and issue a new access token.
- `POST /auth/logout` should invalidate the current refresh token.
- Confirm expired refresh tokens are deleted and cannot be reused.

### 6. Test query sanitization and SQL injection defenses

#### Filtered list endpoints
- `GET /users?search=test&role=jobseeker`
- `GET /jobs?search=engineer&location=Manila&job_type=full_time&experience_level=mid&approval_status=approved`
- `GET /trainings?status=ongoing`
- `GET /employers?status=approved`

Confirm results are returned and filters work correctly.

#### SQL injection check
- Send a payload or URL parameter containing SQL control characters such as `" OR 1=1 --`
- Confirm the request does not return unauthorized or expanded data.

### 7. Test sanitization of payloads

- Send JSON values containing HTML or script markup.
- Confirm `server/helpers/validator.php` strips or neutralizes unsafe characters before usage.

### 8. Review logs and behavior

- Confirm auth events and security-related actions are logged as expected.
- Confirm `server/controllers/AuthController.php` logs login failures in a way that does not expose sensitive details.

## Notes

- This document focuses on backend security hardening. Frontend validation and additional UI-level protections should be added separately.
- Maintain `JWT_SECRET` and database credentials out of source control at all times.
- Use a secure `.env` storage method on production hosts and do not commit it.

---

If you want, I can also add a short checklist file under `docs/` for manual verification steps. 