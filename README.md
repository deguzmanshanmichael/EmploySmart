# EmploySmart - Job Placement Management System

A full-stack application for managing job placement, employer profiles, job seekers, and applications.

## Features

- **Job Seeker Management**: User registration, profile management, application tracking
- **Employer Management**: Company profiles, job posting, applicant tracking
- **Job Board**: Browse, search, and apply for jobs
- **Application System**: Apply for jobs, track applications, manage status
- **Messaging System**: Communication between employers and job seekers
- **Notifications**: Real-time notifications for important events
- **Feedback System**: Rate and provide feedback on placements
- **Admin Dashboard**: System administration and monitoring
- **Security**: JWT authentication, CORS protection, input validation

## Tech Stack

### Frontend
- React 18
- Vite (build tool)
- Tailwind CSS
- React Router v6
- Axios for API calls
- Capacitor for mobile support

### Backend
- PHP 8.1+
- RESTful API
- MySQL Database
- JWT Authentication
- CORS Support

### Deployment
- Hostinger Shared Hosting
- cPanel with PHP 8.1+
- MySQL Database

## Quick Start

### Prerequisites
- Node.js 16+
- PHP 8.1+
- MySQL 5.7+
- npm or yarn

### Local Development

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/EmploySmart.git
   cd EmploySmart
   ```

2. **Setup environment**
   ```bash
   # Windows
   powershell -ExecutionPolicy Bypass -File setup.ps1
   
   # Mac/Linux
   bash setup.sh
   ```

3. **Configure database**
   - Create MySQL database: `employsmart`
   - Create user: `root` or your preferred user
   - Update `.env` file with credentials

4. **Import database schema**
   ```bash
   mysql -u root -p employsmart < database/employsmart_schema_infinityfree.sql
   ```

5. **Start development server**
   ```bash
   # Terminal 1: Backend (PHP)
   cd server
   php -S localhost:8000
   
   # Terminal 2: Frontend (Vite dev server)
   cd client
   npm run dev
   ```

6. **Access application**
   - Frontend: http://localhost:5173
   - API: http://localhost:8000

## Deployment

### Deploy to Hostinger

EmploySmart is ready for production deployment on Hostinger Shared Hosting!

**Quick Setup:**
1. Push this repository to GitHub
2. Follow the deployment guide below

**Deployment Options:**

#### Option 1: Using GitHub + SSH (Recommended)
```bash
# SSH into your Hostinger server
git clone https://github.com/yourusername/EmploySmart.git
cd EmploySmart
bash setup.sh
# Then configure .env and database
```

#### Option 2: Using File Manager/FTP
1. Download files from GitHub as ZIP
2. Extract and upload to Hostinger via File Manager or FTP
3. Run the setup script locally or follow manual steps

**📚 Complete Deployment Guides:**
- [`HOSTINGER_DEPLOYMENT_GUIDE.md`](HOSTINGER_DEPLOYMENT_GUIDE.md) - Step-by-step production guide
- [`GITHUB_TO_HOSTINGER_DEPLOY.md`](GITHUB_TO_HOSTINGER_DEPLOY.md) - Deploy from GitHub
- [`HOSTINGER_DEPLOYMENT_CHECKLIST.md`](HOSTINGER_DEPLOYMENT_CHECKLIST.md) - Progress tracker
- [`HOSTINGER_CONFIG_REFERENCE.md`](HOSTINGER_CONFIG_REFERENCE.md) - Configuration templates
- [`HOSTINGER_QUICK_REFERENCE.md`](HOSTINGER_QUICK_REFERENCE.md) - Quick lookup
- [`HOSTINGER_TROUBLESHOOTING.md`](HOSTINGER_TROUBLESHOOTING.md) - Problem solutions

### Deployment Checklist
- [ ] Domain registered with Hostinger
- [ ] MySQL database created in cPanel
- [ ] Database schema imported
- [ ] Backend files uploaded to `public_html/api/`
- [ ] Frontend files built and uploaded to `public_html/public/`
- [ ] `.env` file configured with production credentials
- [ ] `.htaccess` files in place for routing
- [ ] File permissions set correctly (`.env` = 600)
- [ ] SSL certificate enabled
- [ ] API and frontend accessible and working
- [ ] Backups enabled in Hostinger

## Project Structure

```
EmploySmart/
├── client/                          # React frontend
│   ├── src/
│   │   ├── components/              # React components
│   │   ├── pages/                   # Page components
│   │   ├── services/                # API services
│   │   ├── config/                  # Configuration
│   │   └── utils/                   # Utility functions
│   ├── dist/                        # Built frontend (after npm run build)
│   ├── vite.config.js               # Vite configuration
│   └── package.json
├── server/                          # PHP backend
│   ├── controllers/                 # API controllers
│   ├── models/                      # Database models
│   ├── services/                    # Business logic
│   ├── config/                      # Configuration files
│   ├── middleware/                  # Middleware functions
│   ├── routes/                      # API routes
│   ├── index.php                    # API entry point
│   ├── .htaccess                    # Apache routing
│   └── uploads/                     # User uploads
├── database/                        # Database files
│   ├── employsmart_schema_infinityfree.sql  # Schema
│   └── seed_data.sql                # Sample data
├── docs/                            # Documentation
├── .env.example                     # Environment template
├── .gitignore                       # Git ignore file
├── setup.sh                         # Setup script (Mac/Linux)
├── setup.ps1                        # Setup script (Windows)
└── README.md                        # This file
```

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```env
# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=your-password
DB_NAME=employsmart

# JWT
JWT_SECRET=your-32-char-random-secret
JWT_ACCESS_EXPIRE=900
JWT_REFRESH_EXPIRE=604800

# CORS
CORS_ALLOWED_ORIGINS=http://localhost,http://127.0.0.1
CORS_ALLOW_EMPTY_ORIGIN=false

# Application
APP_ENV=development
APP_DEBUG=true
```

### API Configuration

The frontend automatically detects and connects to the API:
- **Development**: Uses `/api` (proxied by Vite)
- **Production**: Uses `https://yourdomain.com/api` (auto-detected)

## Database

### Schema
The database schema includes:
- Users (job seekers and employers)
- Jobs (job listings)
- Applications (job applications)
- Messages (messaging system)
- Notifications
- Feedback

### Import Schema
```bash
mysql -u root -p employsmart < database/employsmart_schema_infinityfree.sql
```

### Seed Data
```bash
mysql -u root -p employsmart < database/seed_data.sql
```

## API Documentation

API endpoints are documented in `/docs/API_DOCUMENTATION.md`

Base URL:
- Development: `http://localhost:8000`
- Production: `https://yourdomain.com/api`

### Example Endpoints
- `GET /jobs` - List all jobs
- `POST /auth/login` - User login
- `POST /applications` - Submit application
- `GET /employers/{id}` - Get employer profile

## Security

- ✅ JWT token-based authentication
- ✅ Password hashing with bcrypt
- ✅ CORS protection
- ✅ CSRF token validation
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection
- ✅ Security headers configured
- ✅ HTTPS enforced in production
- ✅ Rate limiting on API endpoints

See [`docs/SECURITY_HARDENING.md`](docs/SECURITY_HARDENING.md) for details.

## Performance

- Frontend optimized with Vite
- Gzip compression enabled
- Browser caching configured
- Database indexes on key columns
- API response times: < 1 second typical

## Scripts

### Frontend
```bash
cd client

# Install dependencies
npm install

# Development server
npm run dev

# Build for production
npm run build

# Preview built site
npm run preview
```

### Setup
```bash
# Windows
powershell -ExecutionPolicy Bypass -File setup.ps1

# Mac/Linux
bash setup.sh
```

## Troubleshooting

### Common Issues

**"Cannot connect to database"**
- Check `.env` has correct credentials
- Verify MySQL server is running
- Ensure database and user exist

**"API 500 Error"**
- Check `/server/error_log` for PHP errors
- Verify `.env` file exists and is readable
- Check database connection in `/server/config/database.php`

**"Blank frontend page"**
- Check browser console for errors
- Verify frontend was built: `npm run build`
- Check `.htaccess` file routing

**"CORS Error"**
- Update `CORS_ALLOWED_ORIGINS` in `.env`
- Restart PHP server
- Clear browser cache

See [`HOSTINGER_TROUBLESHOOTING.md`](HOSTINGER_TROUBLESHOOTING.md) for more solutions.

## Development

### Adding New Features

1. Create feature branch
2. Implement changes
3. Test locally
4. Create pull request
5. After merge, deploy to Hostinger

### File Structure for New Features

- Add React component to `client/src/components/`
- Add API service to `client/src/services/`
- Add backend controller to `server/controllers/`
- Add database model to `server/models/`
- Update database schema if needed

### Testing

```bash
# Frontend
cd client
npm run test

# Backend (manual testing with cURL or Postman)
curl -X GET http://localhost:8000/api/jobs
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support & Documentation

- **API Docs**: [`docs/API_DOCUMENTATION.md`](docs/API_DOCUMENTATION.md)
- **Architecture**: [`docs/SYSTEM_ARCHITECTURE.md`](docs/SYSTEM_ARCHITECTURE.md)
- **Security**: [`docs/SECURITY_HARDENING.md`](docs/SECURITY_HARDENING.md)
- **Deployment**: [`GITHUB_TO_HOSTINGER_DEPLOY.md`](GITHUB_TO_HOSTINGER_DEPLOY.md)

## Deployment Support

For Hostinger deployment issues:
1. Check the [Troubleshooting Guide](HOSTINGER_TROUBLESHOOTING.md)
2. Review the [Deployment Guide](HOSTINGER_DEPLOYMENT_GUIDE.md)
3. Contact Hostinger support (24/7 live chat)

## Roadmap

- [ ] Mobile app improvements
- [ ] Advanced job filters and search
- [ ] Real-time notifications
- [ ] Video interview integration
- [ ] Resume analysis AI
- [ ] Analytics dashboard

## Authors

- Your Name - Initial work

## Acknowledgments

- React and Vite communities
- Hostinger for reliable hosting
- All contributors

---

**Ready to deploy? Start with [`GITHUB_TO_HOSTINGER_DEPLOY.md`](GITHUB_TO_HOSTINGER_DEPLOY.md)** 🚀
