# EmploySmart - Hostinger Deployment Setup Script (Windows)
# This script prepares the application for deployment on Hostinger

Write-Host "================================" -ForegroundColor Cyan
Write-Host "EmploySmart Hostinger Setup" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan

# Check if we're in the right directory
if (-not (Test-Path "server\index.php") -or -not (Test-Path "client")) {
    Write-Host "✗ Error: Run this script from the EmploySmart root directory" -ForegroundColor Red
    exit 1
}

# Check if .env exists
if (-not (Test-Path ".env")) {
    Write-Host "Creating .env from .env.example" -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    Write-Host "✓ .env created" -ForegroundColor Green
    Write-Host "⚠️  Please update .env with your Hostinger database credentials" -ForegroundColor Yellow
} else {
    Write-Host "✓ .env already exists" -ForegroundColor Green
}

# Install frontend dependencies
Write-Host ""
Write-Host "Installing frontend dependencies..." -ForegroundColor Yellow
Push-Location client
npm install
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Frontend dependencies installed" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to install frontend dependencies" -ForegroundColor Red
    Pop-Location
    exit 1
}

# Build frontend
Write-Host ""
Write-Host "Building frontend..." -ForegroundColor Yellow
npm run build
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Frontend built successfully" -ForegroundColor Green
    Write-Host "✓ Build files available in: client\dist" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to build frontend" -ForegroundColor Red
    Pop-Location
    exit 1
}

Pop-Location

# Create necessary directories
Write-Host ""
Write-Host "Creating necessary directories..." -ForegroundColor Yellow
if (-not (Test-Path "server\uploads")) {
    New-Item -ItemType Directory -Path "server\uploads" | Out-Null
}
Write-Host "✓ Upload directory created" -ForegroundColor Green

# Verify database schema exists
if (Test-Path "database\employsmart_schema_infinityfree.sql") {
    Write-Host "✓ Database schema file found" -ForegroundColor Green
} else {
    Write-Host "✗ Database schema file not found" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "================================" -ForegroundColor Green
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps for Hostinger deployment:" -ForegroundColor Yellow
Write-Host "1. Update .env with your Hostinger database credentials"
Write-Host "2. Upload 'server\' folder to public_html\api\ on Hostinger"
Write-Host "3. Upload 'client\dist\' contents to public_html\public\ on Hostinger"
Write-Host "4. Import 'database\employsmart_schema_infinityfree.sql' via phpMyAdmin"
Write-Host "5. Set .env file permissions to 600 (chmod 600)"
Write-Host "6. Test: https://yourdomain.com/api/ and https://yourdomain.com/public/"
Write-Host ""
Write-Host "For detailed instructions, see: HOSTINGER_DEPLOYMENT_GUIDE.md" -ForegroundColor Cyan
Write-Host ""
