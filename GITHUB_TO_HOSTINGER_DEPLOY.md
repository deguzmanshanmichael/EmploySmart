# GitHub to Hostinger Deployment Guide

## 🚀 Deploy EmploySmart to Hostinger from GitHub

This guide explains how to deploy the EmploySmart application from this GitHub repository directly to Hostinger hosting.

### Prerequisites

- **Hostinger Account** with an active hosting plan
- **Domain Name** registered or connected to Hostinger
- **GitHub Account** and this repository cloned/forked
- **cPanel Access** (included with Hostinger hosting)
- **FTP/File Manager Access** to your Hostinger account

---

## Method 1: Git Clone on Hostinger (Recommended)

If your Hostinger plan supports SSH/Terminal access:

### Step 1: Access Hostinger via SSH

```bash
ssh your-username@your-domain.com
# or
ssh your-username@your-ip.address
```

### Step 2: Navigate to public_html

```bash
cd ~/public_html
```

### Step 3: Clone from GitHub

```bash
# Clone the repository (if not already done)
git clone https://github.com/yourusername/EmploySmart.git
cd EmploySmart
```

### Step 4: Run Setup Script

```bash
# For Linux/Mac
bash setup.sh

# OR copy .env.example to .env and configure
cp .env.example .env
nano .env  # Edit with your database credentials
```

### Step 5: Create Symlinks (if needed)

```bash
# If not using the root path, create symbolic links
ln -s /home/yourusername/public_html/EmploySmart/server /home/yourusername/public_html/api
ln -s /home/yourusername/public_html/EmploySmart/client/dist /home/yourusername/public_html/public
```

### Step 6: Set Permissions

```bash
# Make .env private
chmod 600 /home/yourusername/public_html/EmploySmart/.env

# Make directories writable
chmod 755 /home/yourusername/public_html/EmploySmart/server/uploads
chmod 755 /home/yourusername/public_html/EmploySmart/server/logs
```

### Step 7: Import Database

```bash
# Via SSH if MySQL client is available
mysql -u employsmart_user -p employsmart_db < database/employsmart_schema_infinityfree.sql

# OR use phpMyAdmin (see Method 2 below)
```

---

## Method 2: Manual Upload (File Manager/FTP)

If SSH is not available:

### Step 1: Prepare Files Locally

On your local computer:

```bash
# Clone repository
git clone https://github.com/yourusername/EmploySmart.git
cd EmploySmart

# For Windows, run:
powershell -ExecutionPolicy Bypass -File setup.ps1

# For Mac/Linux, run:
bash setup.sh
```

### Step 2: Upload Backend Files

**Via Hostinger File Manager:**

1. Go to Hostinger Dashboard → File Manager
2. Create these folders in `public_html/`:
   - `api/` (for backend)
   - `public/` (for frontend)
3. Upload `server/` folder contents to `public_html/api/`
4. Upload `.env` file to `public_html/api/.env`
5. Upload `.htaccess` file to `public_html/api/.htaccess`

**Via FTP:**

1. Download FileZilla or WinSCP
2. Connect to Hostinger FTP:
   - Host: `your-domain.com` or IP from cPanel
   - Username: FTP username from Hostinger
   - Password: FTP password
3. Upload `/server` to `/public_html/api/`
4. Upload `.env` and `.htaccess` to `/public_html/api/`

### Step 3: Upload Frontend Files

1. Build frontend locally:
   ```bash
   cd client
   npm install
   npm run build
   ```

2. Upload `client/dist/` contents to `public_html/public/`
3. Upload `.htaccess` to `public_html/public/.htaccess`

### Step 4: Set File Permissions

**Via Hostinger File Manager:**

1. Right-click `.env` → Change Permissions → Set to `600`
2. Right-click `api/` folder → Change Permissions → Set to `755`
3. Right-click `uploads/` folder → Change Permissions → Set to `755`

**Via SSH:**
```bash
chmod 600 public_html/api/.env
chmod 755 public_html/api
chmod 755 public_html/api/uploads
```

### Step 5: Create Database

1. Go to Hostinger Dashboard → cPanel → MySQL Databases
2. Create new database: `employsmart_db`
3. Create new user: `employsmart_user`
4. Set strong password and grant ALL privileges

### Step 6: Import Schema

1. Open phpMyAdmin from cPanel
2. Select your database
3. Click Import tab
4. Choose `database/employsmart_schema_infinityfree.sql`
5. Click Go

### Step 7: Configure .env

Update `.env` file on server with correct credentials:

```env
DB_HOST=localhost
DB_USER=employsmart_user
DB_PASS=your-strong-password
DB_NAME=employsmart_db
JWT_SECRET=your-32-char-random-secret
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
CORS_ALLOW_EMPTY_ORIGIN=false
APP_ENV=production
APP_DEBUG=false
```

---

## Step 8: Verify Deployment

### Test API Endpoint

Open in browser:
```
https://yourdomain.com/api/
```

Expected response:
```json
{"message":"EmploySmart API v1.0"}
```

### Test Frontend

Open in browser:
```
https://yourdomain.com/public/
```

Should see the EmploySmart login page.

### Test Database Connection

Try logging in with test credentials or making an API call.

---

## Troubleshooting

### Issue: "Cannot connect to database"

**Solutions:**
1. Verify `DB_HOST=localhost` (NOT an IP)
2. Check database credentials in `.env` match cPanel
3. Ensure database user has proper permissions:
   ```sql
   GRANT ALL PRIVILEGES ON employsmart_db.* TO 'employsmart_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Issue: "API 500 Error"

**Solutions:**
1. Check error log: `cPanel → Error Log`
2. Verify `.env` file exists in `/public_html/api/`
3. Check file permissions on `.env` (should be 600)
4. Verify PHP version is 8.1+ (check in cPanel)

### Issue: "Blank Frontend Page"

**Solutions:**
1. Verify files in `/public_html/public/` include `index.html`
2. Check `.htaccess` file exists in `/public_html/public/`
3. Open browser DevTools (F12) → Console for errors
4. Ensure API URL is correct in frontend config

### Issue: "CORS Error"

**Solutions:**
1. Update `CORS_ALLOWED_ORIGINS` in `.env` to include your domain:
   ```
   CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
   ```
2. Restart PHP: cPanel → Restart PHP
3. Clear browser cache (Ctrl+Shift+Delete)

---

## Continuous Deployment (Optional)

To automatically deploy from GitHub on push:

### Using GitHub Actions (requires Hostinger SSH)

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Hostinger

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    
    - name: Build Frontend
      run: |
        cd client
        npm install
        npm run build
    
    - name: Deploy via SSH
      uses: appleboy/ssh-action@master
      with:
        host: ${{ secrets.HOSTINGER_HOST }}
        username: ${{ secrets.HOSTINGER_USER }}
        key: ${{ secrets.HOSTINGER_SSH_KEY }}
        script: |
          cd ~/public_html/EmploySmart
          git pull origin main
          cd client && npm install && npm run build
          cd ..
          echo "Deployment complete!"
```

Then add secrets to GitHub:
1. Go to Settings → Secrets
2. Add: `HOSTINGER_HOST`, `HOSTINGER_USER`, `HOSTINGER_SSH_KEY`

---

## Production Checklist

After deployment, verify:

- [ ] HTTPS is working (green lock icon)
- [ ] API endpoint returns success response
- [ ] Frontend loads without errors
- [ ] Login works with test credentials
- [ ] Database queries work correctly
- [ ] Error logs are clean
- [ ] `.env` file permissions are 600
- [ ] Backups are enabled in Hostinger
- [ ] SSL certificate auto-renewal is enabled

---

## Updating Your Deployment

To update after changes:

### Via Git (if SSH available):
```bash
cd ~/public_html/EmploySmart
git pull origin main
cd client && npm install && npm run build
```

### Via File Manager:
1. Download latest files from GitHub
2. Upload changed files via File Manager or FTP
3. Clear browser cache to see frontend changes

---

## Support

- **Hostinger Support**: https://support.hostinger.com
- **cPanel Help**: Available in cPanel dashboard
- **This Project**: Check GitHub issues and discussions

---

## Security Notes

⚠️ **Important:**
- Never commit `.env` file to GitHub
- Use strong, unique passwords for all accounts
- Enable HTTPS only (no HTTP)
- Keep PHP and dependencies updated
- Regularly backup your database
- Monitor error logs for security issues
- Keep credentials secure and never share them

---

**Happy Deploying! 🚀**

For more detailed instructions, see:
- `HOSTINGER_DEPLOYMENT_GUIDE.md` - Complete deployment guide
- `HOSTINGER_CONFIG_REFERENCE.md` - Configuration templates
- `HOSTINGER_TROUBLESHOOTING.md` - Troubleshooting guide
