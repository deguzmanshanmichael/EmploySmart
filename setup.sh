#!/bin/bash

# EmploySmart - Hostinger Deployment Setup Script
# This script prepares the application for deployment on Hostinger

echo "================================"
echo "EmploySmart Hostinger Setup"
echo "================================"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Creating .env from .env.example${NC}"
    cp .env.example .env
    echo -e "${GREEN}✓ .env created${NC}"
    echo -e "${YELLOW}⚠️  Please update .env with your Hostinger database credentials${NC}"
else
    echo -e "${GREEN}✓ .env already exists${NC}"
fi

# Check if we're in the right directory
if [ ! -f "server/index.php" ] || [ ! -d "client" ]; then
    echo -e "${RED}✗ Error: Run this script from the EmploySmart root directory${NC}"
    exit 1
fi

# Install frontend dependencies
echo ""
echo -e "${YELLOW}Installing frontend dependencies...${NC}"
cd client
npm install
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Frontend dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install frontend dependencies${NC}"
    exit 1
fi

# Build frontend
echo ""
echo -e "${YELLOW}Building frontend...${NC}"
npm run build
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Frontend built successfully${NC}"
    echo -e "${GREEN}✓ Build files available in: client/dist${NC}"
else
    echo -e "${RED}✗ Failed to build frontend${NC}"
    exit 1
fi

cd ..

# Create necessary directories
echo ""
echo -e "${YELLOW}Creating necessary directories...${NC}"
mkdir -p server/uploads
chmod 755 server/uploads
echo -e "${GREEN}✓ Upload directory created${NC}"

# Verify database schema exists
if [ -f "database/employsmart_schema_infinityfree.sql" ]; then
    echo -e "${GREEN}✓ Database schema file found${NC}"
else
    echo -e "${RED}✗ Database schema file not found${NC}"
    exit 1
fi

echo ""
echo "================================"
echo -e "${GREEN}Setup Complete!${NC}"
echo "================================"
echo ""
echo "Next steps for Hostinger deployment:"
echo "1. Update .env with your Hostinger database credentials"
echo "2. Upload 'server/' folder to public_html/api/ on Hostinger"
echo "3. Upload 'client/dist/' contents to public_html/public/ on Hostinger"
echo "4. Import 'database/employsmart_schema_infinityfree.sql' via phpMyAdmin"
echo "5. Set .env file permissions to 600 (chmod 600)"
echo "6. Test: https://yourdomain.com/api/ and https://yourdomain.com/public/"
echo ""
echo "For detailed instructions, see: HOSTINGER_DEPLOYMENT_GUIDE.md"
echo ""
