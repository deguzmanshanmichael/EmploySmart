# Hostinger Deployment - Configuration Files

## File 1: Production .env Template

Save this as `.env` in `public_html/api/` on Hostinger

```env
# ============================================
# EmploySmart Production Environment Config
# ============================================

# Database Configuration (from cPanel MySQL setup)
DB_HOST=localhost
DB_USER=employsmart_user
DB_PASS=YourSecurePassword123!
DB_NAME=employsmart_db

# JWT Authentication
JWT_SECRET=your-very-long-random-secret-key-change-this-to-something-very-long-and-random-min-32-chars
JWT_ALGORITHM=HS256
JWT_EXPIRY=7200

# Application Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# CORS - Allow these origins to access the API
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Database Debug (disable in production)
DB_QUERY_DEBUG=false

# Email Configuration (optional - for notifications)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-app-password-from-cpanel
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=EmploySmart

# Session Configuration
SESSION_LIFETIME=120
SESSION_SECURE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=Lax

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600

# Logging
LOG_CHANNEL=single
LOG_LEVEL=error
```

**IMPORTANT CHANGES:**
- Replace `YourSecurePassword123!` with the password you created for `employsmart_user`
- Replace `your-very-long-random-secret-key...` with a unique 32+ character string (use: https://generate-random.org/)
- Replace `yourdomain.com` with your actual domain
- Replace `noreply@yourdomain.com` with the email account you create in cPanel

---

## File 2: API .htaccess

Save as `.htaccess` in `public_html/api/`

```apache
# ============================================
# EmploySmart API - Apache Configuration
# ============================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /api/
    
    # Prevent direct access to certain files
    RewriteRule ^\.env$ - [F]
    RewriteRule ^config/ - [F]
    RewriteRule ^\. - [F]
    
    # Remove .php extension from URLs
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?$1 [QSA,L]
    
    # Forward all requests to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    # Security Headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header set X-XSS-Protection "1; mode=block"
    
    # Force HTTPS
    <If "%{HTTPS} == 'off'">
        Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    </If>
</IfModule>

# Disable directory listing
Options -Indexes

# Block access to sensitive files
<FilesMatch "\.(env|json|md|sql|log)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Compress output
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
    ExpiresByType text/html "access plus 1 minute"
    ExpiresByType application/json "access plus 1 minute"
    ExpiresByType text/javascript "access plus 1 week"
    ExpiresByType application/x-javascript "access plus 1 week"
</IfModule>
```

---

## File 3: Frontend .htaccess

Save as `.htaccess` in `public_html/public/`

```apache
# ============================================
# EmploySmart Frontend - React Router Config
# ============================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /public/
    
    # Remove trailing slashes
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.+)/$             $1 [R=301,L]
    
    # Route all non-file, non-directory requests to index.html
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.html [QSA,L]
</IfModule>

<IfModule mod_headers.c>
    # Security Headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set X-XSS-Protection "1; mode=block"
    
    # Cache busting for static assets
    <FilesMatch "\.(js|css|woff2|png|svg|jpg)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    
    # Always fetch index.html fresh
    <FilesMatch "^index\.html$">
        Header set Cache-Control "public, max-age=0, must-revalidate"
    </FilesMatch>
</IfModule>

# Disable directory listing
Options -Indexes

# Compress output
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Prevent access to hidden files and directories
<FilesMatch "^\.|^#.*#|~$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
```

---

## File 4: Root .htaccess (Optional)

Save as `.htaccess` in `public_html/` to handle redirects

```apache
# ============================================
# Main Root Redirect Configuration
# ============================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Redirect www to non-www (or vice versa, adjust as needed)
    RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
    RewriteRule ^(.*)$ https://%1$1 [R=301,L]
</IfModule>

# Disable PHP in frontend/api for safety
<Directory ~ "(node_modules|\.git)">
    Deny from all
</Directory>
```

---

## File 5: Generation Script for JWT_SECRET

If you're on Windows PowerShell, run this to generate a secure JWT_SECRET:

```powershell
# Generate a 64-character random string
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RNGCryptoServiceProvider]::new()
$rng.GetBytes($bytes)
[System.Convert]::ToBase64String($bytes)
```

Or use an online generator: https://generate-random.org/

---

## File 6: Database User Permissions (via phpMyAdmin)

After creating the database user, run these queries in phpMyAdmin to ensure proper permissions:

```sql
-- Grant all privileges on employsmart_db to employsmart_user
GRANT ALL PRIVILEGES ON employsmart_db.* TO 'employsmart_user'@'localhost' WITH GRANT OPTION;

-- Flush privileges to apply changes
FLUSH PRIVILEGES;

-- Verify permissions
SHOW GRANTS FOR 'employsmart_user'@'localhost';
```

---

## File 7: Quick Upload Checklist for Each File

| File Path | Source | Destination | Permissions | Notes |
|-----------|--------|-------------|-------------|-------|
| `.env` | Create locally | `public_html/api/.env` | `600` | **PRIVATE** - Never share |
| `.htaccess` | Create locally | `public_html/api/.htaccess` | `644` | Routing config |
| `.htaccess` | Create locally | `public_html/public/.htaccess` | `644` | React routing |
| `index.php` | `/server/index.php` | `public_html/api/index.php` | `644` | API entry point |
| All `config/` files | `/server/config/` | `public_html/api/config/` | `644` | Config files |
| All `controllers/` files | `/server/controllers/` | `public_html/api/controllers/` | `644` | Controller classes |
| All from `/client/dist/` | `/client/dist/` | `public_html/public/` | `644` | Built React app |
| `public/manifest.json` | `/client/public/manifest.json` | `public_html/public/manifest.json` | `644` | PWA manifest |

---

## Troubleshooting Configuration

### If API returns 500 error, check `/public_html/api/error.log`:
```bash
# SSH command (if available)
tail -f public_html/api/error.log

# Or via File Manager - check error_log in public_html/api/ folder
```

### If frontend shows blank page:
1. Open browser DevTools (F12)
2. Check Console tab for errors
3. Check Network tab - ensure API calls go to `https://yourdomain.com/api/`
4. Verify `/client/dist` files uploaded to `public_html/public/`

### If .env not being read:
```bash
# Verify file exists and is readable
ls -la public_html/api/.env

# Check if PHP can access it
php -r "require '.env'; echo 'OK';"
```

---

## Security Verification Commands

After deployment, verify security via cPanel Terminal or SSH:

```bash
# Test API endpoint
curl -I https://yourdomain.com/api/

# Check HTTPS headers
curl -I https://yourdomain.com/public/

# Verify .env file is protected
ls -la public_html/api/.env
# Should show: -rw------- (600)

# Check for executable PHP files
find public_html -name "*.php" -executable
# Should return nothing for shared hosting
```

---

**Save this file and reference during deployment! 🚀**
