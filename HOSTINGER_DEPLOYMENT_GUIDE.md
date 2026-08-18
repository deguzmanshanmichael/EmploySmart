# EmploySmart - Hostinger Deployment Guide

## Overview
This guide covers deploying EmploySmart on **Hostinger Shared Hosting** with cPanel.

### Stack Components:
- **Frontend**: React/Vite (built static files)
- **Backend**: PHP REST API
- **Database**: MySQL
- **Domain**: Custom domain with SSL

---

## Phase 1: Prepare Your Domain

### Step 1.1: Register Domain on Hostinger
1. Log in to your **Hostinger Dashboard**
2. Go to **Domains** → **Register Domain**
3. Search and register your domain (e.g., `employsmart.com`)
4. Complete the purchase
5. **Enable SSL Certificate** (free with Hostinger) - should auto-provision in 24 hours

### Step 1.2: Point Domain to Hosting
- In Hostinger Dashboard, ensure domain is connected to your hosting package
- This should happen automatically when you purchase together
- Verify in **Domains** → Your domain → **Nameservers** show Hostinger's NS

---

## Phase 2: Prepare Backend (Local)

### Step 2.1: Set Up Environment Variables

Create a `.env` file in the `/server` directory:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=employsmart_user
DB_PASS=YourSecurePassword123!
DB_NAME=employsmart_db

# JWT Configuration
JWT_SECRET=your-very-long-random-secret-key-min-32-chars-change-this
JWT_ALGORITHM=HS256
JWT_EXPIRY=7200

# App Configuration
APP_ENV=production
APP_DEBUG=false
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Email (optional, for notifications)
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-app-password
MAIL_FROM=noreply@yourdomain.com
```

### Step 2.2: Prepare Frontend Build

1. Open PowerShell in your client folder:
```powershell
cd c:\xampp\htdocs\EmploySmart\client
npm install  # if not done already
npm run build
```

2. This creates a `/client/dist` folder with optimized static files

### Step 2.3: Prepare Database Schema

- The schema file is ready: `/database/employsmart_schema_infinityfree.sql`
- You'll import this into Hostinger's MySQL

---

## Phase 3: Set Up Hostinger (via cPanel)

### Step 3.1: Access cPanel
1. Log in to **Hostinger Dashboard**
2. Click **Manage** on your hosting package
3. Scroll down and click **cPanel** (or go to `https://your-ip:2083`)
4. Login with your cPanel credentials (provided in email)

### Step 3.2: Create MySQL Database

1. In cPanel, search for **MySQL Databases**
2. Create new database:
   - **Database Name**: `employsmart_db` (or your choice)
   - Click **Create Database**
3. Create new MySQL user:
   - **Username**: `employsmart_user`
   - **Password**: Use a strong password (same as your `.env` file)
   - Click **Create User**
4. Add user to database with **ALL PRIVILEGES**

### Step 3.3: Import Database Schema

1. In cPanel, search for **phpMyAdmin**
2. Login with your new database credentials
3. Select your database (`employsmart_db`)
4. Click **Import** tab
5. Upload `/database/employsmart_schema_infinityfree.sql`
6. Click **Go** to import

✅ Your database is now set up with all required tables!

### Step 3.4: Set Up File Manager Structure

In cPanel, open **File Manager**:

```
public_html/
├── api/                 (Backend PHP code)
├── public/              (Frontend built files)
└── .htaccess           (Routing configuration)
```

### Step 3.5: Configure .htaccess for API Routing

Create `.htaccess` in `public_html/api/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /api/

    # Remove .php extension
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?$1 [QSA,L]

    # Security headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

### Step 3.6: Configure .htaccess for Frontend Routing

Create `.htaccess` in `public_html/public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /public/

    # Route all requests to index.html for React Router
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.html [QSA,L]
</IfModule>
```

---

## Phase 4: Upload Files to Hostinger

### Step 4.1: Prepare Files Locally

1. **Backend Files**:
   - Zip the entire `/server` folder
   - Ensure `.env` file is included with your production credentials

2. **Frontend Files**:
   - Copy all files from `/client/dist` folder
   - These are your built React app

### Step 4.2: Upload via cPanel File Manager

1. In cPanel → **File Manager** → `public_html/`

2. **Create folders**:
   - Create `api` folder
   - Create `public` folder

3. **Upload backend**:
   - Upload `/server` contents to `public_html/api/`
   - Upload `.env` file to `public_html/api/.env`
   - **Ensure `.env` is NOT world-readable**:
     - Right-click `.env` → **Change Permissions** → Set to `600`

4. **Upload frontend**:
   - Upload all files from `/client/dist` to `public_html/public/`

### Step 4.3: Alternative Upload Method (FTP)

If File Manager is slow, use FTP (more reliable for large projects):

1. In cPanel, search for **FTP Accounts**
2. Create new FTP account with access to `public_html`
3. Use **FileZilla** or **WinSCP** on your computer:
   - Host: `your-domain.com` or IP from cPanel
   - Username: FTP username created
   - Password: FTP password
   - Remote site: `/`
4. Connect and upload `api` and `public` folders

---

## Phase 5: Configure Your Application

### Step 5.1: Update Frontend API Base URL

In your React app, update the API base URL to point to your Hostinger domain:

Edit `/client/src/config/api.js` (or wherever you set axios baseURL):

```javascript
const API_BASE_URL = process.env.NODE_ENV === 'production' 
  ? 'https://yourdomain.com/api'
  : 'http://localhost:3000/api';

export default API_BASE_URL;
```

Then rebuild the frontend:
```powershell
npm run build
# Re-upload dist files to public_html/public/
```

### Step 5.2: Verify PHP Configuration

In cPanel, check **Select PHP Version**:
- Ensure **PHP 8.1+** is selected
- Verify extensions: `curl`, `json`, `mysqli`, `pdo` are enabled

---

## Phase 6: Testing & Verification

### Step 6.1: Test API Endpoint
Open your browser and visit:
```
https://yourdomain.com/api/
```
You should see: `{"message":"EmploySmart API v1.0"}`

### Step 6.2: Test Frontend
Visit:
```
https://yourdomain.com/public/
```
You should see your EmploySmart login page

### Step 6.3: Test Database Connection
Run an API test:
```bash
curl -X GET "https://yourdomain.com/api/health"
```

### Step 6.4: Check Error Logs
In cPanel → **Error Log**, check for any PHP errors

---

## Phase 7: Security Hardening (Production)

### Step 7.1: Protect Sensitive Files
In cPanel File Manager:
1. Right-click `public_html/api/.env` → **Change Permissions** → `600`
2. Right-click `public_html/api/` → **Change Permissions** → `755`
3. Verify `.htaccess` prevents direct `.php` access

### Step 7.2: Enable ModSecurity
In cPanel → **ModSecurity** → Enable for better protection

### Step 7.3: Configure Firewall Rules
In Hostinger Dashboard → **Security** → **Firewall**:
- Enable firewall
- Configure rate limiting
- Add your IP to whitelist

### Step 7.4: Regular Backups
In Hostinger Dashboard → **Backups**:
- Enable automatic daily backups
- Download backups weekly as backup

---

## Phase 8: Post-Deployment Tasks

### Step 8.1: Seed Initial Data
1. Via phpMyAdmin, run `/database/seed_database.sql` to add test data
2. Or use your API endpoints to create initial employers/users

### Step 8.2: Set Up Email (Optional)
If using notifications, configure in your `.env`:
- Use Hostinger's SMTP: `smtp.hostinger.com` (port 465)
- Create email account in cPanel → **Email Accounts**

### Step 8.3: Monitor Performance
- In Hostinger Dashboard, monitor resource usage
- If hitting limits, consider upgrading to Cloud Hosting

---

## Troubleshooting

### Issue: "Blank Page" on Frontend
- Check browser console for API URL errors
- Ensure `/client/dist` files are uploaded correctly
- Check `.htaccess` in public folder

### Issue: "API 500 Error"
- Check `error_log` in `public_html/api/`
- Verify database credentials in `.env`
- Ensure MySQL user has proper permissions

### Issue: "CORS Error"
- Check `ALLOWED_ORIGINS` in `.env`
- Review `server/config/cors.php`
- Ensure domain with HTTPS is listed

### Issue: "Connection Refused"
- Verify database server is `localhost` not IP
- Check MySQL user permissions
- Restart MySQL in cPanel if needed

---

## Directory Structure (Final)

```
public_html/
├── .htaccess                    (main routing)
├── api/
│   ├── .htaccess               (API routing)
│   ├── .env                    (PRIVATE - mode 600)
│   ├── index.php               (API entry point)
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── routes/
│   ├── services/
│   ├── helpers/
│   ├── middleware/
│   └── uploads/
└── public/
    ├── .htaccess               (Frontend routing)
    ├── index.html
    ├── manifest.json
    ├── assets/
    ├── employsmart/
    └── ... (other React built files)
```

---

## Quick Reference URLs

After deployment:
- **Frontend**: `https://yourdomain.com/public/`
- **API Base**: `https://yourdomain.com/api/`
- **cPanel**: `https://yourdomain.com:2083`
- **phpMyAdmin**: `https://yourdomain.com/cpanel/` → MySQL Databases

---

## Next Steps

1. ✅ Register domain with Hostinger
2. ✅ Create MySQL database via cPanel
3. ✅ Build frontend with `npm run build`
4. ✅ Upload backend and frontend files
5. ✅ Configure `.env` with production credentials
6. ✅ Test API and frontend
7. ✅ Enable SSL and security features
8. ✅ Monitor error logs and performance

---

## Support & Resources

- **Hostinger Support**: [support.hostinger.com](https://support.hostinger.com)
- **cPanel Tutorials**: cPanel Help inside dashboard
- **PHP Configuration**: Check with Hostinger support for custom PHP settings

**Good luck with your deployment! 🚀**
