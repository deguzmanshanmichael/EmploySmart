# EmploySmart Hostinger - Quick Reference Card

**Print this page or keep it open during deployment!**

---

## 🚀 Deployment Quick Steps (15 Minutes)

### Before You Start
- [ ] Domain registered on Hostinger (wait 24h for SSL)
- [ ] Frontend built: `/client/dist/` folder exists
- [ ] `.env` file ready with production credentials

### Step 1: Create Database (cPanel) - 2 min
```
1. cPanel → MySQL Databases
2. Database: employsmart_db
3. User: employsmart_user
4. Password: [strong password]
5. Grant ALL privileges
```

### Step 2: Import Schema (cPanel) - 3 min
```
1. cPanel → phpMyAdmin
2. Select database → Import tab
3. Upload: /database/employsmart_schema_infinityfree.sql
4. Click Go
```

### Step 3: Create Folder Structure (File Manager) - 2 min
```
public_html/
├── api/          (create this)
└── public/       (create this)
```

### Step 4: Upload Backend (File Manager) - 4 min
```
public_html/api/ ← Upload:
- All files from /server/
- .env file
- .htaccess file
```

### Step 5: Upload Frontend (File Manager) - 4 min
```
public_html/public/ ← Upload:
- All files from /client/dist/
- .htaccess file
```

### Step 6: Set Permissions - 1 min
```
.env file → chmod 600 (right-click → Change Permissions)
```

### Step 7: Test - 1 min
```
Visit:
- https://yourdomain.com/api/
- https://yourdomain.com/public/
```

---

## 📋 Essential URLs

| Purpose | URL |
|---------|-----|
| **Frontend** | `https://yourdomain.com/public/` |
| **API** | `https://yourdomain.com/api/` |
| **cPanel** | `https://yourdomain.com:2083` |
| **phpMyAdmin** | `https://yourdomain.com/cpanel/` → MySQL |
| **File Manager** | In cPanel dashboard |

---

## 🔑 Key Credentials to Remember

```
Database:
- Host: localhost (NOT IP)
- Name: employsmart_db
- User: employsmart_user
- Password: [Your strong password]

JWT Secret: [Your 32+ char random string]

Domain: yourdomain.com
Email: noreply@yourdomain.com
```

---

## 📁 File Locations After Upload

```
Backend API:
https://yourdomain.com/api/index.php → public_html/api/index.php
https://yourdomain.com/api/auth/login → routed by .htaccess

Frontend:
https://yourdomain.com/public/index.html → public_html/public/index.html
https://yourdomain.com/public/login → routed by .htaccess to index.html
```

---

## 🐛 If Something Goes Wrong

| Problem | Quick Fix |
|---------|-----------|
| Blank page | F12 → Console → Check for API URL errors |
| API 500 | cPanel → Error Log → Check `/api/error.log` |
| CORS error | Verify `ALLOWED_ORIGINS` in `.env` includes your domain |
| 404 on API | Verify `.htaccess` file uploaded to `/api/` |
| Database error | Check credentials in `.env` match cPanel |
| Slow loading | Clear browser cache (Ctrl+Shift+Delete) |

---

## 📝 .env Values (Template)

```env
DB_HOST=localhost
DB_USER=employsmart_user
DB_PASS=[your strong password]
DB_NAME=employsmart_db
JWT_SECRET=[64 character random string]
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
APP_ENV=production
APP_DEBUG=false
```

---

## ✅ Final Verification

After uploading, check these:

- [ ] `https://yourdomain.com/api/` → Shows `{"message":"EmploySmart API v1.0"}`
- [ ] `https://yourdomain.com/public/` → Shows login form
- [ ] Login works with test credentials
- [ ] SSL shows 🔒 lock icon
- [ ] No errors in browser console (F12)

---

## 📞 Hostinger Support

- **Live Chat**: Hostinger Dashboard (24/7)
- **Email**: support@hostinger.com
- **Check Status**: status.hostinger.com

---

## 🎯 Common File Uploads

| File | Upload To | Permissions |
|------|-----------|-------------|
| `.env` | `public_html/api/` | 600 |
| `.htaccess` (API) | `public_html/api/` | 644 |
| `.htaccess` (Frontend) | `public_html/public/` | 644 |
| `index.php` | `public_html/api/` | 644 |
| `index.html` | `public_html/public/` | 644 |
| All `dist/` files | `public_html/public/` | 644 |

---

## 🔐 Security Reminders

- [ ] `.env` file set to chmod 600 (PRIVATE)
- [ ] HTTPS enabled (not HTTP)
- [ ] `APP_DEBUG=false` in production
- [ ] Strong passwords for all accounts
- [ ] Regular backups enabled in Hostinger
- [ ] Never commit `.env` to version control

---

## 📊 After Going Live

1. **Monitor** → Hostinger Dashboard → Resources
2. **Backup** → Enable automatic daily backups
3. **Updates** → Keep dependencies updated
4. **Logs** → Check error logs weekly
5. **Performance** → Use browser DevTools to optimize

---

## 🚨 Emergency Checklist

If site is down:
1. Check Hostinger Dashboard → Server Status
2. SSH/Terminal → Check if MySQL running
3. cPanel → Error Log for recent PHP errors
4. Browser DevTools (F12) → Network tab for failed requests
5. Contact Hostinger support with error messages

---

## 💡 Pro Tips

- Use FTP (FileZilla) instead of File Manager for faster uploads
- Keep local backups of `/server` and `/client/dist`
- Test thoroughly on local XAMPP before deploying
- Use staging domain first if available
- Monitor SSL certificate renewal (auto-renews on Hostinger)
- Use cPanel's Backup tool for weekly downloads

---

## 📱 Testing the App

1. **Desktop**: Open `https://yourdomain.com/public/`
2. **Mobile**: Open same URL on phone
3. **Test Login**: Use credentials from seed data
4. **Test Features**: 
   - Post a job (if employer)
   - Apply for job (if job seeker)
   - Send message
   - Upload resume

---

**You've got this! Start with Phase 1 and work through step-by-step. 🎉**

*For detailed help, see HOSTINGER_DEPLOYMENT_GUIDE.md*
