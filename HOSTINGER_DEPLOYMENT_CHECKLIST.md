# EmploySmart - Hostinger Deployment Checklist

## 📋 Pre-Deployment Preparation (Local)

- [ ] Clone/backup your project from `/xampp/htdocs/EmploySmart`
- [ ] Build frontend: 
  ```
  cd client
  npm install
  npm run build
  ```
  - [ ] Verify `/client/dist` folder created successfully
- [ ] Prepare `.env` file for production with secure credentials:
  - [ ] Generate strong `JWT_SECRET` (use an online generator)
  - [ ] Set strong `DB_PASS`
  - [ ] Update `ALLOWED_ORIGINS` with your domain
- [ ] Create `.htaccess` files (see guide for content):
  - [ ] One for `api` folder routing
  - [ ] One for `public` folder routing

---

## 🔗 Domain & Hosting Setup (Hostinger Dashboard)

- [ ] Purchase/Register domain on Hostinger
- [ ] Domain connected to hosting package
- [ ] SSL certificate provisioned (usually automatic, wait 24 hours)
- [ ] Verify domain nameservers point to Hostinger

---

## 📊 Database Setup (cPanel)

- [ ] Access cPanel via Hostinger Dashboard → Manage → cPanel
- [ ] Create MySQL database:
  - [ ] Name: `employsmart_db`
  - [ ] Note the database name
- [ ] Create MySQL user:
  - [ ] Username: `employsmart_user`
  - [ ] Password: [secure password]
  - [ ] Grant ALL privileges to database
- [ ] Import schema via phpMyAdmin:
  - [ ] Open phpMyAdmin from cPanel
  - [ ] Select database
  - [ ] Import `/database/employsmart_schema_infinityfree.sql`
  - [ ] Verify all tables created (users, jobs, applications, etc.)

---

## 📁 File Structure Setup (cPanel File Manager)

- [ ] Create folder structure in `public_html/`:
  - [ ] `public_html/api/` (for backend)
  - [ ] `public_html/public/` (for frontend)
- [ ] Upload backend files to `public_html/api/`:
  - [ ] All PHP files from `/server`
  - [ ] `.env` file with production credentials
  - [ ] Upload `.htaccess` file for API routing
- [ ] Upload frontend files to `public_html/public/`:
  - [ ] All files from `/client/dist/`
  - [ ] Upload `.htaccess` file for React routing
- [ ] Set file permissions:
  - [ ] `.env` → chmod 600 (private)
  - [ ] `.htaccess` → chmod 644
  - [ ] PHP files → chmod 644
  - [ ] Folders → chmod 755

---

## 🔐 Environment & Configuration

- [ ] `.env` file saved in `public_html/api/`:
  ```
  DB_HOST=localhost
  DB_USER=employsmart_user
  DB_PASS=[your secure password]
  DB_NAME=employsmart_db
  JWT_SECRET=[long random string, min 32 chars]
  JWT_ALGORITHM=HS256
  JWT_EXPIRY=7200
  APP_ENV=production
  APP_DEBUG=false
  ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
  ```
- [ ] Update `/client/dist` API base URL (if not already set)
  - [ ] Should point to: `https://yourdomain.com/api`
- [ ] Verify PHP version 8.1+ selected in cPanel
- [ ] Check PHP extensions enabled:
  - [ ] `curl`
  - [ ] `json`
  - [ ] `mysqli`
  - [ ] `pdo`

---

## ✅ Testing & Verification

### API Testing
- [ ] Test API endpoint: `https://yourdomain.com/api/`
  - Expected: `{"message":"EmploySmart API v1.0"}`
- [ ] Test health check: `https://yourdomain.com/api/health`
- [ ] Check error logs: cPanel → Error Log (in `api` folder)

### Frontend Testing
- [ ] Visit: `https://yourdomain.com/public/`
  - [ ] Login page loads
  - [ ] No blank page or console errors
- [ ] Test login functionality:
  - [ ] Use test user from seed data
  - [ ] Verify authentication works

### Database Connection
- [ ] Test via phpMyAdmin:
  - [ ] Can connect to database
  - [ ] All tables visible
  - [ ] Test data loaded (if seeded)

---

## 🛡️ Security Hardening

- [ ] Enable HTTPS only (force redirect):
  - [ ] Update `.htaccess` to force HTTPS
  - [ ] Or use Hostinger's Force HTTPS option
- [ ] Protect sensitive files:
  - [ ] `.env` file → chmod 600
  - [ ] `config/` folder → No direct access via `.htaccess`
- [ ] ModSecurity enabled in cPanel:
  - [ ] Security → ModSecurity toggle
- [ ] Firewall enabled in Hostinger Dashboard
- [ ] Remove debug mode:
  - [ ] `APP_DEBUG=false` in `.env`
- [ ] Hide PHP errors from users:
  - [ ] `display_errors = Off` in PHP config
- [ ] SSL certificate active:
  - [ ] Browser shows 🔒 lock icon
  - [ ] No "mixed content" warnings

---

## 📧 Optional: Email Configuration

- [ ] Create email account in cPanel (e.g., `noreply@yourdomain.com`)
- [ ] Add to `.env`:
  ```
  MAIL_HOST=smtp.hostinger.com
  MAIL_PORT=465
  MAIL_USERNAME=noreply@yourdomain.com
  MAIL_PASSWORD=[app password]
  MAIL_FROM=noreply@yourdomain.com
  ```

---

## 🚀 Post-Deployment

- [ ] Seed initial data if needed:
  - [ ] Use phpMyAdmin or API to create test users/jobs
- [ ] Test complete user flows:
  - [ ] Job seeker registration & login
  - [ ] Employer posting jobs
  - [ ] Application submission
  - [ ] Message/notification system
- [ ] Monitor resource usage in Hostinger Dashboard:
  - [ ] CPU, Memory, Disk Space
- [ ] Set up automatic backups:
  - [ ] Hostinger Dashboard → Backups → Enable Daily
  - [ ] Download weekly backup locally

---

## 🐛 Common Troubleshooting

| Issue | Solution |
|-------|----------|
| Blank frontend page | Check browser console → verify API URL in dist files |
| API 500 error | Check `public_html/api/error.log` for PHP errors |
| CORS error | Verify `ALLOWED_ORIGINS` matches your domain in `.env` |
| Connection refused | Check DB credentials, ensure `localhost` not IP |
| "File not found" 404 | Verify `.htaccess` files uploaded correctly |
| Slow performance | Upgrade to Cloud Hosting if hitting resource limits |

---

## 📞 Support Contacts

- **Hostinger Support Chat**: Available 24/7 in Dashboard
- **cPanel Tutorials**: Built into cPanel interface
- **Check Error Logs**: `public_html/` error.log via File Manager

---

## 🎯 Final Verification Checklist

- [ ] Domain registered and connected
- [ ] SSL certificate active (green lock)
- [ ] API endpoint responds correctly
- [ ] Frontend loads without errors
- [ ] Database connected and populated
- [ ] Login works with test credentials
- [ ] User can browse jobs/applications
- [ ] Error logs clean (no critical errors)
- [ ] Performance acceptable
- [ ] Backups configured

---

## ✨ You're Live!

Once all checkboxes are completed, your EmploySmart system is deployed on Hostinger!

**Access Points:**
- Frontend: `https://yourdomain.com/public/`
- API: `https://yourdomain.com/api/`
- cPanel: `https://yourdomain.com:2083`

**Next: Monitor logs regularly and update security patches!**
