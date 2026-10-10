# EmploySmart Deployment (Hostinger)

This guide covers redeploying EmploySmart, including the resume-driven job recommendation feature, on Hostinger shared or cloud hosting. Older guides in this repository (`HOSTINGER_*.md`) remain for reference; where they differ, follow this file.

Legend: **[hPanel]** = Hostinger control panel or File Manager, no SSH needed. **[SSH]** = needs SSH access (Premium/Business shared plans, Cloud and VPS).

---

## 1. Requirements

| Component | Version / setting | Notes |
|---|---|---|
| PHP | **8.1 or newer** (8.2/8.3 recommended) | Set in **[hPanel] → Advanced → PHP Configuration**. Resume parsing runs on 8.0+, but the optional AI feature's SDK needs 8.1+. |
| PHP extensions | `mysqli`, `mbstring`, `json`, `fileinfo`, `zlib`, `iconv`, `curl` | Usually on by default. `zip` is needed only for DOCX resumes. `curl` is needed only for AI assistance. |
| PHP limits | `upload_max_filesize` and `post_max_size` ≥ 8M, `memory_limit` ≥ 256M, `max_execution_time` ≥ 120 | **[hPanel] → PHP Configuration → PHP options**. |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.3+ (InnoDB) | |
| Node.js (build machine only) | Node 18+ and npm 9+ | Used to build the React frontend. Not needed on Hostinger. |
| Composer (optional) | 2.x | `server/vendor/` is committed, so Composer is only needed to update dependencies. |

### What runs where (resume feature)

| Capability | Where it runs | Hostinger shared hosting |
|---|---|---|
| PDF text extraction, multi-column/table layout reconstruction | PHP (`smalot/pdfparser`, bundled in `server/vendor`) | Works. No server software to install. |
| `pdftotext` fallback | Server shell | Used only if `shell_exec` is allowed and poppler is installed. Shared plans usually allow neither; the feature works without it. |
| OCR for scanned PDFs | **Jobseeker's browser** (PDF.js + Tesseract.js) | Works on any plan. The browser downloads about 10 MB of language data from the jsDelivr CDN on first use. |
| AI-assisted reading (optional) | Claude API over HTTPS | Needs outbound HTTPS and an Anthropic API key. Off by default. |

Tesseract or other OCR engines cannot be installed on shared hosting, so OCR deliberately runs in the browser. If AI assistance is enabled, scanned PDFs are read by the AI on the server instead, and browser OCR remains the fallback.

---

## 2. Get the latest code

**[SSH]**
```bash
cd ~/domains/YOUR_DOMAIN/          # or wherever your clone lives
git pull origin main
```

**[hPanel]** If you connected the repository in **Advanced → Git**, click **Deploy** (or enable auto-deployment). Otherwise download the repository ZIP from GitHub on your computer and upload the files with File Manager as described below.

Never upload your local `.env`, `server/storage/`, or `server/uploads/` folders; they hold secrets and personal data.

---

## 3. Build and deploy the frontend

On your computer (or over SSH if Node is available):
```bash
npm run build          # from the repository root; installs client deps and builds
```
The build is written to `dist/` at the repository root.

Upload **the contents** of `dist/` to `public_html/` **[hPanel File Manager or SFTP]**, together with the repository's root `.htaccess` (it routes `/api/*` to the PHP backend, blocks secrets, and serves the React app).

The frontend calls the API at `https://YOUR_DOMAIN/api` automatically. Set `VITE_API_URL` before building only if the API lives on a different domain.

---

## 4. Deploy the PHP backend

Recommended layout:
```
public_html/
├── .htaccess              ← repository root .htaccess
├── index.html, assets/    ← contents of dist/
└── api/                   ← contents of server/ (including vendor/ and .htaccess)
    └── .env               ← production settings (permission 600)
```

1. Upload everything inside `server/` to `public_html/api/` **[hPanel]**, including `vendor/`, `storage/.htaccess`, `uploads/resumes/.htaccess` and `.htaccess`. If you use Git, a symlink or copy step over **[SSH]** does the same: `rsync -a --exclude storage/resumes server/ ~/public_html/api/`.
2. Make `api/storage/` and `api/uploads/` writable (755 for folders) **[hPanel → File Manager → Permissions]**.
3. Create the private resume folder **outside** `public_html` **[hPanel File Manager]**, e.g. `/home/uXXXXXXXXX/employsmart_private/resumes`, permission 750. Put its absolute path in `RESUME_STORAGE_DIR` (step 6).

If `RESUME_STORAGE_DIR` is empty, resumes are stored in `api/storage/resumes/`, which is protected by `api/storage/.htaccess` (`Require all denied`). Storing them outside `public_html` is safer because it does not depend on `.htaccess` support.

---

## 5. Apply database migrations

The new `resume_profiles` table is created automatically the first time the API connects to the database. To create it yourself:

**[hPanel]** phpMyAdmin → select your database → **Import** → `database/migrations/2026_10_10_create_resume_profiles.sql` → **Go**.

**[SSH]**
```bash
mysql -u DB_USER -p DB_NAME < database/migrations/2026_10_10_create_resume_profiles.sql
```

The migration only adds a table; existing tables and data are not changed. It is safe to run more than once.

---

## 6. Environment variables

Edit `public_html/api/.env` **[hPanel File Manager]** (start from `.env.example`). Never commit this file.

```env
DB_HOST=localhost
DB_USER=uXXXXXXXXX_employsmart
DB_PASS=********
DB_NAME=uXXXXXXXXX_employsmart
JWT_SECRET=<at least 32 random characters>
CORS_ALLOWED_ORIGINS=https://YOUR_DOMAIN,https://www.YOUR_DOMAIN
CORS_ALLOW_EMPTY_ORIGIN=false
APP_ENV=production
APP_DEBUG=false

# Resume feature
RESUME_STORAGE_DIR=/home/uXXXXXXXXX/employsmart_private/resumes
RESUME_MAX_SIZE_MB=5
RESUME_MAX_PAGES=10
RESUME_USE_PDFTOTEXT=auto

# Optional AI assistance
RESUME_AI_ENABLED=false
ANTHROPIC_API_KEY=
RESUME_AI_MODEL=claude-opus-5-5
RESUME_AI_TIMEOUT=60
```

`.env` is read from `public_html/.env` first and then `public_html/api/.env`. Keep it in `api/`, which the API's `.htaccess` protects, and set permission **600**.

### Optional: AI-assisted resume reading

1. Create an API key in the Anthropic Console.
2. Set `RESUME_AI_ENABLED=true` and `ANTHROPIC_API_KEY=...` in `api/.env` only. The key is never sent to the browser.
3. Resume text is sent to the Claude API for processing when this is on. Tell your users so, and turn it off if your data-handling policy does not allow it.

Every AI-extracted value is checked against the resume text before it is shown. If the API is slow, rate-limited, or unreachable, the system falls back to the built-in parser automatically.

---

## 7. Test the deployed workflow

1. `https://YOUR_DOMAIN/api/` should return `{"success":true,"message":"EmploySmart API v1.0"}`.
2. Log in as a jobseeker → **My Resume** → upload a text PDF. You should see "Waiting for your review" with extracted details.
3. Correct anything that is wrong → **Confirm & Update Profile** → check **My Profile** and **My Skills**.
4. Recommendations labelled **"Based on Your Uploaded Resume"** should appear on **My Resume**, the **Dashboard** and **Recommended**. **Apply Now** opens the normal application form.
5. Upload a scanned PDF → **Recognise Text** should run in the browser.
6. **Delete** the resume → the file and extracted data are removed.
7. Security checks: opening `https://YOUR_DOMAIN/api/storage/`, `https://YOUR_DOMAIN/api/vendor/composer.json` and `https://YOUR_DOMAIN/.env` must return **403** or **404**.

Automated tests (local or **[SSH]**, never against the production database):
```bash
php server/tests/resume_parsing_test.php
php server/tests/resume_matching_test.php
php server/tests/job_matching_service_test.php
php -S 127.0.0.1:8099 server/index.php &       # then, against a development database:
php server/tests/resume_api_integration_test.php http://127.0.0.1:8099/api
```

---

## 8. Common problems

| Symptom | Cause and fix |
|---|---|
| "Resume processing is not installed on the server (missing server/vendor)" | `vendor/` was not uploaded. Upload `server/vendor/` to `public_html/api/vendor/`, or run `composer install --no-dev` in `api/` **[SSH]**. |
| "Resume storage is not available on the server" | `RESUME_STORAGE_DIR` does not exist or is not writable. Create it and set permission 750/755 **[hPanel]**. |
| "The file is too large" for files under 5 MB | Raise `upload_max_filesize` and `post_max_size` **[hPanel → PHP Configuration]**. |
| Upload hangs, then shows a 500 or 504 error | Raise `max_execution_time` to 120 or more; if AI assistance is on, lower `RESUME_AI_TIMEOUT` or disable it. |
| Every PDF is reported as scanned | The PDF really has no text layer, or uses fonts without a Unicode map. Use **Recognise Text**, or export the resume again from Word, Google Docs or Canva as a "standard" PDF. |
| Scanned-resume OCR never finishes | The browser cannot reach `cdn.jsdelivr.net`, or the device has little memory. Try a desktop browser or upload a text PDF. |
| AI assistance never used | Check `RESUME_AI_ENABLED=true`, the API key, PHP 8.1+, the `curl` extension, and `api/error_log` (messages start with "Resume AI extraction"). |
| API calls return 404 or the HTML page | The root `.htaccess` is missing, or `mod_rewrite` routing does not reach `api/index.php`. Re-upload both `.htaccess` files. |
| CORS errors in the browser console | Set `CORS_ALLOWED_ORIGINS` to your exact `https://` domains. |
| "View Uploaded Resume" says no resume | Resume files stored in another folder were not migrated. Ask the jobseeker to upload again. |

Do not deploy straight to the live site without a backup: export the database **[hPanel → phpMyAdmin → Export]** and download `public_html/api/storage` (or your private resume folder) first.
