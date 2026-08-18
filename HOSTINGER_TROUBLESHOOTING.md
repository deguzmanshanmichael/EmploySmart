# EmploySmart on Hostinger - Troubleshooting Guide

## 🔍 Common Issues & Solutions

---

## Issue 1: API Returns 500 Error or "Connection Refused"

### Symptoms:
- API endpoint shows HTTP 500
- Error: "Connection refused" or "Cannot connect to database"
- Browser shows blank error page

### Solutions:

**Step 1: Check PHP Error Log**
```
1. cPanel → File Manager
2. Navigate to public_html/api/
3. Look for error_log file
4. Right-click → View
5. Look for database connection errors
```

**Step 2: Verify Database Credentials**
```
1. Check `.env` file has correct values:
   - DB_HOST=localhost (NOT 127.0.0.1)
   - DB_USER=employsmart_user (exact username)
   - DB_PASS=YourPassword (matches what you set)
   - DB_NAME=employsmart_db (correct database name)

2. Test via phpMyAdmin:
   - cPanel → phpMyAdmin
   - Try logging in with these credentials
   - If login fails, password is wrong
```

**Step 3: Check Database Connection Code**
```
In public_html/api/config/database.php, verify:
- mysqli_connect() uses correct parameters
- No typos in connection string
- Database server is 'localhost' not 'yoursite.com'
```

**Step 4: Restart MySQL (if you have control)**
```
1. cPanel → MySQL Databases
2. If available, click "Restart MySQL"
3. Wait 30 seconds
4. Try API again
```

---

## Issue 2: Blank Frontend Page (404 or Content Not Loading)

### Symptoms:
- Visit `https://yourdomain.com/public/` → blank page
- Browser console shows errors
- No login form visible

### Solutions:

**Step 1: Verify Files Uploaded**
```
1. cPanel → File Manager
2. Check if public_html/public/ folder exists
3. Should contain: index.html, assets/, manifest.json
4. If empty, upload /client/dist/ files again
```

**Step 2: Check Browser Console (F12)**
```
1. Press F12 to open Developer Tools
2. Go to Console tab
3. Look for red errors
4. Common errors:
   - "Failed to fetch from /api" → API URL wrong
   - "Cannot find module" → Missing files
   - "CORS error" → Check ALLOWED_ORIGINS in .env
```

**Step 3: Verify .htaccess in public folder**
```
1. File Manager → public_html/public/
2. Show hidden files (gear icon → Show Hidden Files)
3. Should see .htaccess file
4. Right-click → Edit
5. Content should route to index.html (see config reference)
```

**Step 4: Check if React build is valid**
```
Local test (on your computer):
1. Open public_html/public/index.html in browser
2. If it still shows blank, build is corrupted
3. Rebuild locally:
   cd client
   npm run build
   Upload dist/ files again
```

---

## Issue 3: API Endpoint Works But Frontend Gets CORS Error

### Symptoms:
- Console shows: "Access to XMLHttpRequest at 'https://yourdomain.com/api/...' 
  from origin 'https://yourdomain.com' has been blocked by CORS policy"
- Login fails silently

### Solutions:

**Step 1: Check ALLOWED_ORIGINS in .env**
```
1. File Manager → public_html/api/
2. Right-click .env → Edit
3. Find line: ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
4. Verify it includes your actual domain exactly
5. If using www, add both www and non-www versions
6. Save and refresh browser
```

**Step 2: Check server/config/cors.php**
```
1. File Manager → public_html/api/config/
2. Edit cors.php
3. Verify the function setCorsHeaders() includes:
   - Access-Control-Allow-Origin: * (or specific domain)
   - Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
   - Access-Control-Allow-Headers: Content-Type, Authorization
```

**Step 3: Clear Browser Cache**
```
1. Press Ctrl+Shift+Delete
2. Select "Cached images and files"
3. Click "Clear browsing data"
4. Refresh https://yourdomain.com/public/
```

**Step 4: Update Frontend API URL**
```
If API URL is hardcoded wrong in frontend:
1. Edit /client/src/ files (search for axios/fetch base URLs)
2. Ensure it's: https://yourdomain.com/api
3. Rebuild: npm run build
4. Upload new dist/ files
```

---

## Issue 4: "Cannot POST /api/auth/login" or Similar 404 on API

### Symptoms:
- Specific API endpoints return 404
- Error: "Cannot POST /api/auth/login"
- Some endpoints work, others don't

### Solutions:

**Step 1: Verify Routes File Exists**
```
1. File Manager → public_html/api/routes/
2. Should contain route definition files
3. Check routes are registered in index.php
```

**Step 2: Check index.php Router Logic**
```
In public_html/api/index.php:
1. Verify $resource variable is parsed correctly
2. Ensure routes match endpoint structure
3. Example: POST /auth/login should route to AuthController
```

**Step 3: Verify HTTP Method**
```
Some issues come from wrong HTTP method:
- Frontend sends POST but route expects GET
- Check browser Network tab (F12) for actual request type
- Verify controller matches HTTP method
```

**Step 4: Test with cURL (if SSH available)**
```
curl -X POST https://yourdomain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test123"}'
```

---

## Issue 5: SSL Certificate Not Working (Site Loads as HTTP)

### Symptoms:
- Site shows `http://yourdomain.com` instead of `https://`
- Browser shows "Not Secure" warning
- Mixed content errors

### Solutions:

**Step 1: Check SSL Provisioning**
```
1. Hostinger Dashboard → Domains
2. Select your domain
3. Check SSL certificate status
4. If "Pending", wait 24 hours
5. If "Failed", contact Hostinger support
```

**Step 2: Force HTTPS in .htaccess**
```
Add to public_html/.htaccess:

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

**Step 3: Update Frontend Configuration**
```
Verify all API calls use HTTPS:
1. Search in /client/src/ for axios.create
2. Ensure baseURL starts with https://
3. Rebuild and re-upload
```

**Step 4: Clear Browser Cache & HSTS**
```
1. Chrome: Settings → Privacy → Clear browsing data
2. Check "Cookies and other site data"
3. Visit site again
```

---

## Issue 6: File Upload/Download Not Working

### Symptoms:
- Resume upload fails
- File download returns 404
- "Permission denied" errors

### Solutions:

**Step 1: Check Uploads Folder Permissions**
```
1. File Manager → public_html/api/uploads/
2. Right-click → Change Permissions
3. Set to 755 (rwxr-xr-x)
4. Ensure folder is writable
```

**Step 2: Verify Upload Directory Path**
```
In your controller code, verify:
- Upload path uses: /public_html/api/uploads/
- NOT: /var/www/html/uploads
- Path should be relative or use $_SERVER['DOCUMENT_ROOT']
```

**Step 3: Check PHP Upload Limits**
```
In cPanel → Select PHP Version:
1. View php.ini directives
2. Check these values:
   - upload_max_filesize: 128M
   - post_max_size: 128M
3. If too low, increase or contact support
```

**Step 4: Test Upload via API**
```
Use this cURL command:
curl -X POST https://yourdomain.com/api/upload \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@/path/to/file.pdf"
```

---

## Issue 7: Database Import Failed or No Tables Created

### Symptoms:
- Database exists but is empty
- Error during schema import
- "Access denied" when running SQL

### Solutions:

**Step 1: Re-Import Schema**
```
1. cPanel → phpMyAdmin
2. Select your database
3. Click Import tab
4. Choose /database/employsmart_schema_infinityfree.sql
5. Click Go
6. Wait for completion message
```

**Step 2: Check User Privileges**
```
In phpMyAdmin, run:
SHOW GRANTS FOR 'employsmart_user'@'localhost';

Should show:
- GRANT ALL PRIVILEGES ON employsmart_db.*
- TO 'employsmart_user'@'localhost'

If not, run:
GRANT ALL PRIVILEGES ON employsmart_db.* TO 'employsmart_user'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

**Step 3: Check Table Structure**
```
In phpMyAdmin:
1. Select database
2. Click Structure tab
3. Should see tables: users, jobs, applications, etc.
4. If missing, schema import failed
5. Try importing again, or:
   - Drop database, recreate
   - Re-import schema fresh
```

**Step 4: Verify SQL File Syntax**
```
If import keeps failing:
1. Download schema file locally
2. Open in text editor
3. Check for:
   - Correct line endings (not mixed)
   - No UTF-8 BOM character
   - SQL syntax is valid
4. Try importing through command line if available
```

---

## Issue 8: "Max Execution Time Exceeded" Error

### Symptoms:
- Error: "Maximum execution time of XXX seconds exceeded"
- Happens on complex queries or file operations
- Frontend times out

### Solutions:

**Step 1: Increase PHP Timeout**
```
In cPanel → Select PHP Version:
1. View php.ini directives
2. Find: max_execution_time
3. Change from 30 to 300 (seconds)
4. Save
```

**Step 2: Optimize Database Queries**
```
In your PHP code:
1. Add indexes to frequently queried columns
2. Limit result sets with LIMIT clause
3. Use pagination for large data
4. Avoid N+1 queries in loops
```

**Step 3: Increase Memory Limit (if needed)**
```
In cPanel → Select PHP Version:
1. Find: memory_limit
2. Increase from 128M to 256M or 512M
3. Save
```

---

## Issue 9: Email Not Sending (Notifications/Password Reset)

### Symptoms:
- Password reset emails not received
- No notification emails sent
- No errors, but emails disappear

### Solutions:

**Step 1: Verify Email Configuration**
```
Check .env file has correct settings:
MAIL_DRIVER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=app-password-from-cpanel
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

**Step 2: Create Email Account in cPanel**
```
1. cPanel → Email Accounts
2. Create account: noreply@yourdomain.com
3. Set strong password
4. Note the app password (if using 2FA)
5. Use that password in .env MAIL_PASSWORD
```

**Step 3: Check Email Logs**
```
1. cPanel → Email Trace
2. See if emails were sent
3. Check "Failure" reasons if bounced
4. Common issues:
   - Reverse DNS not configured
   - Hostname not matching
   - Blacklist problems
```

**Step 4: Use Hostinger's SMTP Relay**
```
Alternative: Use Hostinger's native mail function:
mail('user@example.com', 'Subject', 'Message');

But SMTP is more reliable for production.
```

---

## Issue 10: "You have an error in your SQL syntax" Error

### Symptoms:
- Error message shows SQL syntax error
- Specific database operation fails
- Error mentions "near 'keyword' at line X"

### Solutions:

**Step 1: Check Column Names**
```
In your PHP code:
1. Verify all column names match database schema
2. Check for typos in field names
3. Ensure special characters are quoted with backticks
   Good:  SELECT `password` FROM users
   Bad:   SELECT password FROM users
```

**Step 2: Debug with phpMyAdmin**
```
1. Go to phpMyAdmin
2. Copy the failing SQL query
3. Paste in Query tab
4. Run to see exact error
5. Error will highlight problem
```

**Step 3: Check String Escaping**
```
PHP code should use:
- Prepared statements (best):
  $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  
- Or PDO:
  $pdo->prepare("SELECT * FROM users WHERE email = :email")
  ->execute([':email' => $email]);

Avoid string concatenation:
Bad:  "SELECT * FROM users WHERE email = '$email'"
```

---

## Issue 11: Performance Issues (Slow Loading)

### Symptoms:
- Pages load slowly
- API responses take >3 seconds
- High CPU usage shown in Hostinger Dashboard

### Solutions:

**Step 1: Enable Compression**
```
Verify .htaccess has compression enabled:
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>
```

**Step 2: Optimize Frontend Bundle**
```
1. Check dist/ file sizes:
   - Should be <100KB for main.js
   - If >500KB, something is wrong

2. Rebuild with production optimizations:
   cd client
   npm run build
   
3. Verify Vite is minifying code
```

**Step 3: Check Database Indexes**
```
In phpMyAdmin, add indexes to frequently queried columns:

ALTER TABLE users ADD INDEX email_idx (email);
ALTER TABLE jobs ADD INDEX status_idx (status);
ALTER TABLE applications ADD INDEX user_id_idx (user_id);
```

**Step 4: Monitor Resource Usage**
```
In Hostinger Dashboard → Hosting → Resources:
1. Check current CPU, Memory, Disk usage
2. If consistently high:
   - Upgrade to Cloud Hosting
   - Optimize code further
   - Implement caching
```

**Step 5: Implement Caching**
```
In PHP, use simple file caching:
$cache_file = '/tmp/jobs_cache.json';
if (file_exists($cache_file) && time() - filemtime($cache_file) < 3600) {
    echo file_get_contents($cache_file);
} else {
    // Get from DB, cache result
}
```

---

## General Debugging Checklist

When something breaks:

1. **Check Error Logs First**
   - cPanel → Error Log (all PHP errors)
   - Browser DevTools Console (F12)
   - Browser Network tab (see HTTP status codes)

2. **Test Connectivity**
   - Can you access cPanel?
   - Can you access phpMyAdmin?
   - Can you SSH to server? (if available)

3. **Verify Credentials**
   - Database: username, password, host, database name
   - Domain: registered and SSL active
   - API endpoint: responding to requests

4. **Check File Permissions**
   - .env should be 600
   - Folders should be 755
   - PHP files should be 644

5. **Review Recent Changes**
   - What changed since last working state?
   - Did you update .env file?
   - Did you re-upload all files?

6. **Contact Hostinger Support**
   - Available 24/7 in dashboard chat
   - Ask about: PHP version, MySQL access, resources
   - Provide specific error messages

---

## Hostinger Support Contacts

- **Live Chat**: In Hostinger Dashboard (24/7)
- **Email Support**: support@hostinger.com
- **Status Page**: status.hostinger.com
- **Knowledge Base**: support.hostinger.com

---

**Stuck? Start with checking error logs, they usually tell you exactly what's wrong!** 🔧
