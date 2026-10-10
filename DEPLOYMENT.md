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
| Node.js | Node 18+ and npm 9+ | Used by Hostinger's GitHub deployment to run `npm run build` (or on your computer for manual uploads). |
| Composer (optional) | 2.x | `server/vendor/` is committed and bundled by the build, so Composer is only needed to update dependencies. |

### What runs where (resume feature)

| Capability | Where it runs | Hostinger shared hosting |
|---|---|---|
| PDF text extraction, multi-column/table layout reconstruction | PHP (`smalot/pdfparser`, bundled in `server/vendor`) | Works. No server software to install. |
| `pdftotext` fallback | Server shell | Used only if `shell_exec` is allowed and poppler is installed. Shared plans usually allow neither; the feature works without it. |
| OCR for scanned PDFs | **Jobseeker's browser** (PDF.js + Tesseract.js) | Works on any plan. The browser downloads about 10 MB of language data from the jsDelivr CDN on first use. |
| AI-assisted reading (optional) | Claude API over HTTPS | Needs outbound HTTPS and an Anthropic API key. Off by default. |

Tesseract or other OCR engines cannot be installed on shared hosting, so OCR deliberately runs in the browser. If AI assistance is enabled, scanned PDFs are read by the AI on the server instead, and browser OCR remains the fallback.

---

## 2. How a redeploy works now

Hostinger's GitHub deployment runs the build and **replaces `public_html` with the build output (`dist/`)**. Anything uploaded into `public_html` by hand, such as an `api/` folder or its `.env`, is deleted on the next deploy. That is why the API used to disappear.

The build now produces the whole site:

```
dist/                       → published to public_html on every deploy
├── index.html, assets/     ← React frontend
├── .htaccess               ← routes /api to the PHP API, blocks .env/.git
└── api/                    ← the PHP API (copied from server/, including vendor/)
```

Everything that must **survive** deploys lives in a private folder beside `public_html`, which deployments never touch:

```
/home/uXXXXXXXXX/domains/YOUR_DOMAIN/
├── employsmart_private/    ← persistent, created automatically
│   ├── .env                ← database password, JWT secret, API keys
│   ├── uploads/            ← avatars, certificates, permits, landing images
│   └── storage/resumes/    ← uploaded resumes (never web-accessible)
└── public_html/            ← replaced on every deploy
```

Uploaded files keep their old URLs (`/api/uploads/avatars/...`); the API serves them from the private folder. Resumes are only available through the logged-in download route.

### Hostinger deployment settings **[hPanel]**

In **Websites → your site → GitHub deployment** (or the Node.js/frontend app settings), use:

| Setting | Value |
|---|---|
| Repository / branch | `deguzmanshanmichael/EmploySmart` / `main` |
| Install command | `npm install` (default) |
| Build command | `npm run build` |
| Output / publish directory | `dist` |
| Node version | 18 or newer |

`npm run build` builds the frontend and copies `server/` into `dist/api` (see `client/scripts/bundleApi.js`). After this, **Deploy** is the only step for code changes: no File Manager uploads.

---

## 3. One-time setup of the private folder

Do this once. Later deploys need nothing.

**Option A (easiest):** deploy once, then upload your `.env` to `public_html/api/.env` with File Manager as before and open `https://YOUR_DOMAIN/api/` in a browser. The API copies the file to `employsmart_private/.env` automatically, and later deploys no longer remove it.

**Option B:** in File Manager, open `/home/uXXXXXXXXX/domains/YOUR_DOMAIN/` (the folder that contains `public_html`), create the folder `employsmart_private` (permission 750), and create `.env` inside it (permission 600).

Check: after the next deploy, the site and login still work, and `employsmart_private/` still contains `.env`.

Notes:
- If the API cannot create `employsmart_private` (unusual hosting restrictions), set `DATA_DIR` in `.env` to any writable folder outside `public_html`.
- To change a setting later, edit `employsmart_private/.env`, not `public_html/api/.env`; the private file takes priority.
- Files uploaded before this change were stored inside `public_html/api/uploads` and were already removed by earlier deploys. Affected users need to upload their photo or resume again.

---

## 4. Manual deployment (only without GitHub deployment)

On your computer: `npm run build`, then upload the **contents** of `dist/` (including `.htaccess` and `api/`) to `public_html/` **[hPanel File Manager or SFTP]**. Do the one-time setup in section 3.

**[SSH]** alternative: `git pull && npm run build && rsync -a --delete dist/ ~/domains/YOUR_DOMAIN/public_html/`

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

Edit `employsmart_private/.env` **[hPanel File Manager]** (start from `.env.example`). Never commit this file, and never put it in the repository.

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

# Persistent data: leave empty to use employsmart_private/ automatically
DATA_DIR=

# Resume feature (empty RESUME_STORAGE_DIR = employsmart_private/storage/resumes)
RESUME_STORAGE_DIR=
RESUME_MAX_SIZE_MB=5
RESUME_MAX_PAGES=10
RESUME_USE_PDFTOTEXT=auto

# Optional AI assistance
RESUME_AI_ENABLED=false
ANTHROPIC_API_KEY=
RESUME_AI_MODEL=claude-opus-5-5
RESUME_AI_TIMEOUT=60
```

`.env` is read from `employsmart_private/.env` first, then `public_html/.env`, then `public_html/api/.env`. Only the first location survives deploys. Set permission **600**.

### Optional: AI-assisted resume reading

1. Create an API key in the Anthropic Console.
2. Set `RESUME_AI_ENABLED=true` and `ANTHROPIC_API_KEY=...` in `employsmart_private/.env` only. The key is never sent to the browser.
3. Resume text is sent to the Claude API for processing when this is on. Tell your users so, and turn it off if your data-handling policy does not allow it.

Every AI-extracted value is checked against the resume text before it is shown. If the API is slow, rate-limited, or unreachable, the system falls back to the built-in parser automatically.

---

### Using EmploySmart in another municipality

No code changes are needed. Log in as admin → **Municipality Settings** and set the municipality name, province, office address, contact email/phone, office hours, landing page text, logo and colours. Registration, verification instructions, the PESO dashboard, and default job/training locations all use these values. Each LGU should use its own deployment and database.

### New PESO tools (no extra setup)

- **PESO → Skills Gap** (also under CLCDO): skills demanded by open jobs vs. skills of jobseekers, available trainings per gap, a 12-month progression trend, applicants needing support, and one-click training recommendations sent as notifications.
- **PESO / Employer → Placement Feedback**: ratings, placement outcomes and reply threads for accepted applicants, plus a PESO outcome summary by employer.
- Database changes (`feedback_replies` table, `outcome`/`updated_at` columns) are applied automatically; the manual script is `database/migrations/2026_10_11_placement_feedback.sql`.

---

## 7. Test the deployed workflow

1. `https://YOUR_DOMAIN/api/` should return `{"success":true,"message":"EmploySmart API v1.0"}`.
   - After every deploy, File Manager should show `public_html/api/` (shipped by the build) and `employsmart_private/` beside `public_html`.
2. Log in as a jobseeker → **My Resume** → upload a text PDF. You should see "Waiting for your review" with extracted details.
3. Correct anything that is wrong → **Confirm & Update Profile** → check **My Profile** and **My Skills**.
4. Recommendations labelled **"Based on Your Uploaded Resume"** should appear on **My Resume**, the **Dashboard** and **Recommended**. **Apply Now** opens the normal application form.
5. Upload a profile photo, click **Deploy** again in hPanel, and confirm the photo and resume are still there.
6. Upload a scanned PDF → **Recognise Text** should run in the browser.
7. **Delete** the resume → the file and extracted data are removed.
8. Security checks: opening `https://YOUR_DOMAIN/api/storage/`, `https://YOUR_DOMAIN/api/vendor/composer.json` and `https://YOUR_DOMAIN/.env` must return **403** or **404**.

Automated tests (local or **[SSH]**, never against the production database):
```bash
php server/tests/resume_parsing_test.php
php server/tests/resume_contact_test.php
php server/tests/resume_matching_test.php
php server/tests/job_matching_service_test.php
php -S 127.0.0.1:8099 server/index.php &       # then, against a development database:
php server/tests/resume_api_integration_test.php http://127.0.0.1:8099/api
php server/tests/peso_features_integration_test.php http://127.0.0.1:8099/api
```

---

## 8. Common problems

| Symptom | Cause and fix |
|---|---|
| `public_html/api` is missing after a deploy | The build did not run `client/scripts/bundleApi.js`. Check that the build command is `npm run build` and the output directory is `dist`, and read the deploy log for `[employsmart] PHP API bundled into dist/api`. |
| Database errors / "Database connection failed" right after a deploy | No `.env` was found. Create `employsmart_private/.env` (section 3). |
| Photos or resumes disappear after a deploy | They were uploaded before this version, into `public_html/api/uploads`. New uploads go to `employsmart_private/` and are kept. If `employsmart_private/` was never created, check `api/error_log` and set `DATA_DIR`. |
| "Resume processing is not installed on the server (missing server/vendor)" | `server/vendor/` is missing from the repository or the build. It is committed; redeploy, or run `composer install --no-dev` in `server/` before building. |
| "Resume storage is not available on the server" | `employsmart_private/storage/resumes` (or `RESUME_STORAGE_DIR`) is not writable. Fix the folder permission (750/755) **[hPanel]**. |
| "The file is too large" for files under 5 MB | Raise `upload_max_filesize` and `post_max_size` **[hPanel → PHP Configuration]**. |
| Upload hangs, then shows a 500 or 504 error | Raise `max_execution_time` to 120 or more; if AI assistance is on, lower `RESUME_AI_TIMEOUT` or disable it. |
| Every PDF is reported as scanned | The PDF really has no text layer, or uses fonts without a Unicode map. Use **Recognise Text**, or export the resume again from Word, Google Docs or Canva as a "standard" PDF. |
| Scanned-resume OCR never finishes | The browser cannot reach `cdn.jsdelivr.net`, or the device has little memory. Try a desktop browser or upload a text PDF. |
| AI assistance never used | Check `RESUME_AI_ENABLED=true`, the API key, PHP 8.1+, the `curl` extension, and `api/error_log` (messages start with "Resume AI extraction"). |
| API calls return 404 or the HTML page | `public_html/.htaccess` or `public_html/api/.htaccess` is missing. Both come from the build; make sure the deployment publishes dotfiles from `dist/`. |
| CORS errors in the browser console | Set `CORS_ALLOWED_ORIGINS` to your exact `https://` domains. |
| "View Uploaded Resume" says no resume | Resume files stored in another folder were not migrated. Ask the jobseeker to upload again. |

Do not deploy straight to the live site without a backup: export the database **[hPanel → phpMyAdmin → Export]** and download `employsmart_private/` first.
