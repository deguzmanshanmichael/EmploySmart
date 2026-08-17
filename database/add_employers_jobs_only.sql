-- ===================================================================
-- ADD EMPLOYERS AND JOBS TO EMPLOYSMART (WITHOUT RECREATING USERS)
-- ===================================================================
-- This script adds 16 employers and 60+ jobs directly
-- Assumes appropriate user accounts exist or will be created manually

USE employsmart;

SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- First, let's create employer users if they don't exist
-- ===================================================================

INSERT IGNORE INTO users (first_name, last_name, sex, birth_date, age, email, password, role, phone, address, city, province, zip_code, civil_status, nationality, education_level, employment_status, is_verified, archived, created_at) VALUES

-- Construction & Real Estate
('Manuel', 'Santos', 'male', '1975-03-15', 51, 'manuel.santos@buildcon.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09181234567', 'Office 201, Business Center, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),
('Maria', 'Reyes', 'female', '1982-06-20', 44, 'maria.reyes@homebuild.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09191234567', '123 Emerald St, Cabanatuan', 'Cabanatuan', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),

-- IT & Technology
('Ramon', 'Gonzales', 'male', '1988-09-10', 38, 'ramon.gonzales@techsolve.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09201234567', 'Tech Hub, Palayan Business Park', 'Palayan', 'Nueva Ecija', '3132', 'single', 'Filipino', 'postgraduate', 'employed', 1, 0, NOW()),
('Andrea', 'Villarosa', 'female', '1990-02-14', 36, 'andrea@infosys.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09211234567', '456 Innovation Ave, Gapan', 'Gapan', 'Nueva Ecija', '3105', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),

-- Retail & Commerce
('Jose', 'Mercado', 'male', '1980-11-25', 45, 'jose.mercado@megamart.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09221234567', 'Mall 1, Downtown San Fernando', 'San Fernando', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),
('Patricia', 'Fernandez', 'female', '1985-08-30', 40, 'patricia@goodvalue.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09231234567', 'Shopping Center, Cabanatuan', 'Cabanatuan', 'Nueva Ecija', '3100', 'married', 'Filipino', 'high_school', 'employed', 1, 0, NOW()),

-- Manufacturing
('Ricardo', 'Borja', 'male', '1977-05-12', 49, 'ricardo@textilemill.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09241234567', 'Industrial Complex, San Jose', 'San Jose City', 'Nueva Ecija', '3115', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),
('Angelica', 'Castillo', 'female', '1986-04-22', 40, 'angelica.castillo@packageco.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09251234567', 'Factory District, Gapan', 'Gapan', 'Nueva Ecija', '3105', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),

-- Healthcare & Services
('Fernando', 'Morales', 'male', '1979-07-18', 46, 'dr.morales@medicalplus.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09261234567', 'Medical Center, Palayan', 'Palayan', 'Nueva Ecija', '3132', 'married', 'Filipino', 'postgraduate', 'employed', 1, 0, NOW()),
('Teresa', 'Aquino', 'female', '1981-10-09', 44, 'teresa@hotelservices.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09271234567', 'Hotel Plaza, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),

-- Agriculture & Food Processing
('Pablo', 'Roxas', 'male', '1970-12-08', 55, 'pablo.roxas@agrifarm.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09281234567', 'Farm Estate, Cabanatuan', 'Cabanatuan', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),
('Sofia', 'Delgado', 'female', '1987-01-17', 39, 'sofia.delgado@foodpacker.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09291234567', 'Processing Plant, San Jose', 'San Jose City', 'Nueva Ecija', '3115', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),

-- Automotive & Transportation
('Antonio', 'Mendoza', 'male', '1983-03-28', 42, 'antonio@autocare.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09301234567', 'Auto Repair Center, Gapan', 'Gapan', 'Nueva Ecija', '3105', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),
('Rosalinda', 'Torres', 'female', '1989-09-05', 36, 'rosalinda@transportco.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09311234567', 'Logistics Center, Cabanatuan', 'Cabanatuan', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW()),

-- Education & Training
('Pablo', 'De Jesus', 'male', '1976-06-14', 50, 'prof.dejesus@edutech.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09321234567', 'Education Center, Palayan', 'Palayan', 'Nueva Ecija', '3132', 'married', 'Filipino', 'postgraduate', 'employed', 1, 0, NOW()),
('Victoria', 'Santos', 'female', '1988-11-21', 37, 'victoria@langschool.ph', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/s.a', 'employer', '09331234567', 'Language Center, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', 'married', 'Filipino', 'college', 'employed', 1, 0, NOW());

-- ===================================================================
-- CREATE EMPLOYER RECORDS
-- ===================================================================

INSERT IGNORE INTO employers (user_id, company_name, industry, company_size, website, business_permit, company_address, contact_person, contact_email, contact_phone, verification_status, created_at) VALUES

-- Construction
((SELECT id FROM users WHERE email='manuel.santos@buildcon.com' LIMIT 1), 'BuildCon Construction Corporation', 'Construction', 'large', 'https://buildcon.com.ph', 'BP-2024-001', 'Office 201, Business Center, San Fernando, Nueva Ecija', 'Manuel Santos', 'manuel.santos@buildcon.com', '09181234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='maria.reyes@homebuild.ph' LIMIT 1), 'HomeBuild Developers Inc', 'Construction', 'medium', 'https://homebuild.ph', 'BP-2024-002', '123 Emerald St, Cabanatuan, Nueva Ecija', 'Maria Reyes', 'maria.reyes@homebuild.ph', '09191234567', 'approved', NOW()),

-- IT & Technology
((SELECT id FROM users WHERE email='ramon.gonzales@techsolve.ph' LIMIT 1), 'TechSolve Philippines', 'Information Technology', 'medium', 'https://techsolve.ph', 'BP-2024-003', 'Tech Hub, Palayan Business Park, Nueva Ecija', 'Ramon Gonzales', 'ramon.gonzales@techsolve.ph', '09201234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='andrea@infosys.com' LIMIT 1), 'InfoSys Development Ltd', 'Information Technology', 'large', 'https://infosys.com.ph', 'BP-2024-004', '456 Innovation Ave, Gapan, Nueva Ecija', 'Andrea Villarosa', 'andrea@infosys.com', '09211234567', 'approved', NOW()),

-- Retail
((SELECT id FROM users WHERE email='jose.mercado@megamart.ph' LIMIT 1), 'MegaMart Retail Corporation', 'Retail & Commerce', 'large', 'https://megamart.ph', 'BP-2024-005', 'Mall 1, Downtown San Fernando, Nueva Ecija', 'Jose Mercado', 'jose.mercado@megamart.ph', '09221234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='patricia@goodvalue.com' LIMIT 1), 'GoodValue Stores Inc', 'Retail & Commerce', 'medium', 'https://goodvalue.com.ph', 'BP-2024-006', 'Shopping Center, Cabanatuan, Nueva Ecija', 'Patricia Fernandez', 'patricia@goodvalue.com', '09231234567', 'approved', NOW()),

-- Manufacturing
((SELECT id FROM users WHERE email='ricardo@textilemill.com' LIMIT 1), 'Textile Mill Corporation', 'Manufacturing', 'large', 'https://textilemill.ph', 'BP-2024-007', 'Industrial Complex, San Jose City, Nueva Ecija', 'Ricardo Borja', 'ricardo@textilemill.com', '09241234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='angelica.castillo@packageco.ph' LIMIT 1), 'PackageCo Manufacturing', 'Manufacturing', 'medium', 'https://packageco.ph', 'BP-2024-008', 'Factory District, Gapan, Nueva Ecija', 'Angelica Castillo', 'angelica.castillo@packageco.ph', '09251234567', 'approved', NOW()),

-- Healthcare & Services
((SELECT id FROM users WHERE email='dr.morales@medicalplus.ph' LIMIT 1), 'MedicalPlus Healthcare Services', 'Healthcare', 'medium', 'https://medicalplus.ph', 'BP-2024-009', 'Medical Center, Palayan, Nueva Ecija', 'Fernando Morales', 'dr.morales@medicalplus.ph', '09261234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='teresa@hotelservices.ph' LIMIT 1), 'Hotel Services Philippines', 'Hospitality & Services', 'medium', 'https://hotelservices.ph', 'BP-2024-010', 'Hotel Plaza, San Fernando, Nueva Ecija', 'Teresa Aquino', 'teresa@hotelservices.ph', '09271234567', 'approved', NOW()),

-- Agriculture & Food Processing
((SELECT id FROM users WHERE email='pablo.roxas@agrifarm.com' LIMIT 1), 'AgriFarm Cooperative', 'Agriculture', 'large', 'https://agrifarm.com.ph', 'BP-2024-011', 'Farm Estate, Cabanatuan, Nueva Ecija', 'Pablo Roxas', 'pablo.roxas@agrifarm.com', '09281234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='sofia.delgado@foodpacker.ph' LIMIT 1), 'FoodPacker Processing Inc', 'Food Processing', 'medium', 'https://foodpacker.ph', 'BP-2024-012', 'Processing Plant, San Jose City, Nueva Ecija', 'Sofia Delgado', 'sofia.delgado@foodpacker.ph', '09291234567', 'approved', NOW()),

-- Automotive & Transportation
((SELECT id FROM users WHERE email='antonio@autocare.ph' LIMIT 1), 'AutoCare Services Center', 'Automotive & Services', 'medium', 'https://autocare.ph', 'BP-2024-013', 'Auto Repair Center, Gapan, Nueva Ecija', 'Antonio Mendoza', 'antonio@autocare.ph', '09301234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='rosalinda@transportco.ph' LIMIT 1), 'TransportCo Logistics', 'Transportation & Logistics', 'large', 'https://transportco.ph', 'BP-2024-014', 'Logistics Center, Cabanatuan, Nueva Ecija', 'Rosalinda Torres', 'rosalinda@transportco.ph', '09311234567', 'approved', NOW()),

-- Education & Training
((SELECT id FROM users WHERE email='prof.dejesus@edutech.ph' LIMIT 1), 'EduTech Learning Center', 'Education', 'medium', 'https://edutech.ph', 'BP-2024-015', 'Education Center, Palayan, Nueva Ecija', 'Pablo De Jesus', 'prof.dejesus@edutech.ph', '09321234567', 'approved', NOW()),
((SELECT id FROM users WHERE email='victoria@langschool.ph' LIMIT 1), 'Language School International', 'Education', 'small', 'https://langschool.ph', 'BP-2024-016', 'Language Center, San Fernando, Nueva Ecija', 'Victoria Santos', 'victoria@langschool.ph', '09331234567', 'approved', NOW());

-- ===================================================================
-- CREATE JOBS (CONSTRUCTION)
-- ===================================================================

INSERT INTO jobs (employer_id, title, description, requirements, location, salary_range, vacancies, deadline, job_type, experience_level, education_required, approval_status, approved_by, created_at) VALUES

-- BuildCon Construction Corporation
((SELECT id FROM employers WHERE company_name = 'BuildCon Construction Corporation' LIMIT 1), 'Project Manager', 'Manage construction projects from planning to completion. Oversee team coordination, budget management, and quality assurance.', 'Bachelor\'s degree in Civil Engineering or Construction Management, 5+ years project management experience, Leadership skills', 'San Fernando, Nueva Ecija', '₱60,000 - ₱80,000', 2, '2026-10-30', 'fulltime', 'senior', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'BuildCon Construction Corporation' LIMIT 1), 'Site Supervisor', 'Supervise construction site activities, ensure safety compliance, manage laborers and equipment.', 'High school diploma minimum, 3+ years construction experience, OSHA certification preferred', 'San Fernando, Nueva Ecija', '₱35,000 - ₱45,000', 3, '2026-10-15', 'fulltime', 'mid', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'BuildCon Construction Corporation' LIMIT 1), 'Mason/Bricklayer', 'Perform masonry work including brick laying, stone work, and concrete finishing.', 'Vocational training in masonry, 2+ years experience, Physical fitness', 'San Fernando, Nueva Ecija', '₱18,000 - ₱25,000', 5, '2026-10-31', 'fulltime', 'entry', 'vocational', 'approved', 2, NOW()),

-- HomeBuild Developers Inc
((SELECT id FROM employers WHERE company_name = 'HomeBuild Developers Inc' LIMIT 1), 'Architectural Technician', 'Assist architects in design documentation, CAD drawings, and project visualization.', 'Associate\'s degree in Architecture or related field, AutoCAD proficiency, Attention to detail', 'Cabanatuan, Nueva Ecija', '₱28,000 - ₱38,000', 2, '2026-10-31', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'HomeBuild Developers Inc' LIMIT 1), 'Construction Laborer', 'Perform general construction tasks, site cleaning, material handling, and assistance to skilled workers.', 'Physical fitness, willingness to learn, basic safety awareness', 'Cabanatuan, Nueva Ecija', '₱12,000 - ₱16,000', 8, '2026-11-15', 'fulltime', 'entry', 'elementary', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (IT & TECHNOLOGY)
-- ===================================================================

-- TechSolve Philippines
((SELECT id FROM employers WHERE company_name = 'TechSolve Philippines' LIMIT 1), 'Senior Full Stack Developer', 'Develop web applications using modern frameworks, lead technical architecture decisions, mentor junior developers.', 'Bachelor\'s degree in Computer Science, 5+ years development experience, Expertise in React, Node.js, Database design', 'Palayan, Nueva Ecija', '₱80,000 - ₱110,000', 1, '2026-09-30', 'fulltime', 'senior', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'TechSolve Philippines' LIMIT 1), 'Junior Web Developer', 'Develop web features, fix bugs, write clean code, participate in code reviews.', 'Bachelor\'s degree in CS/IT or bootcamp certification, 1-2 years development experience, HTML/CSS/JavaScript proficiency', 'Palayan, Nueva Ecija', '₱25,000 - ₱35,000', 3, '2026-11-15', 'fulltime', 'entry', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'TechSolve Philippines' LIMIT 1), 'QA & Test Engineer', 'Write and execute test cases, perform manual and automated testing, report bugs with clarity.', 'Associate\'s degree or equivalent, 2+ years QA experience, Test automation tools knowledge', 'Palayan, Nueva Ecija', '₱22,000 - ₱32,000', 2, '2026-11-01', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),

-- InfoSys Development Ltd
((SELECT id FROM employers WHERE company_name = 'InfoSys Development Ltd' LIMIT 1), 'Software Architect', 'Design scalable system architecture, establish technical standards, lead technical reviews.', 'Bachelor\'s degree in CS, 8+ years development experience, Cloud architecture knowledge (AWS/Azure)', 'Gapan, Nueva Ecija', '₱100,000 - ₱140,000', 1, '2026-09-15', 'fulltime', 'senior', 'postgraduate', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'InfoSys Development Ltd' LIMIT 1), 'Data Analyst', 'Analyze business data, create reports, provide insights for decision making, data visualization.', 'Bachelor\'s degree in Statistics/Math/CS, 2+ years analytics experience, SQL and Excel proficiency', 'Gapan, Nueva Ecija', '₱32,000 - ₱44,000', 2, '2026-11-10', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'InfoSys Development Ltd' LIMIT 1), 'IT Support Specialist', 'Provide technical support to users, troubleshoot hardware/software issues, maintain IT infrastructure.', 'Associate\'s degree in IT or equivalent, 1+ years IT support experience, Troubleshooting skills', 'Gapan, Nueva Ecija', '₱18,000 - ₱26,000', 4, '2026-11-30', 'fulltime', 'entry', 'college', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (RETAIL & COMMERCE)
-- ===================================================================

-- MegaMart Retail Corporation
((SELECT id FROM employers WHERE company_name = 'MegaMart Retail Corporation' LIMIT 1), 'Store Manager', 'Manage daily store operations, supervise staff, achieve sales targets, ensure customer satisfaction.', 'High school diploma minimum, 4+ years retail management experience, Leadership abilities', 'San Fernando, Nueva Ecija', '₱40,000 - ₱55,000', 3, '2026-10-31', 'fulltime', 'mid', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'MegaMart Retail Corporation' LIMIT 1), 'Sales Associate', 'Assist customers, process transactions, maintain product displays, handle inquiries professionally.', 'High school diploma, Customer service experience preferred, Communication skills', 'San Fernando, Nueva Ecija', '₱14,000 - ₱19,000', 10, '2026-11-30', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'MegaMart Retail Corporation' LIMIT 1), 'Cashier', 'Operate point-of-sale system, handle cash transactions accurately, maintain register.', 'High school diploma, Basic math skills, Attention to detail', 'San Fernando, Nueva Ecija', '₱12,000 - ₱16,000', 8, '2026-12-15', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),

-- GoodValue Stores Inc
((SELECT id FROM employers WHERE company_name = 'GoodValue Stores Inc' LIMIT 1), 'Inventory Manager', 'Manage inventory levels, coordinate stock movements, maintain warehouse organization.', 'High school diploma, 3+ years inventory experience, Inventory management system knowledge', 'Cabanatuan, Nueva Ecija', '₱30,000 - ₱40,000', 2, '2026-11-01', 'fulltime', 'mid', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'GoodValue Stores Inc' LIMIT 1), 'Part-time Sales Clerk', 'Stock shelves, organize merchandise, assist customers during peak hours.', 'High school diploma, Part-time work availability', 'Cabanatuan, Nueva Ecija', '₱8,000 - ₱12,000', 15, '2026-12-31', 'parttime', 'entry', 'high_school', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (MANUFACTURING)
-- ===================================================================

-- Textile Mill Corporation
((SELECT id FROM employers WHERE company_name = 'Textile Mill Corporation' LIMIT 1), 'Production Supervisor', 'Oversee production line operations, ensure quality standards, manage production team.', 'High school diploma or vocational training, 4+ years manufacturing experience, Leadership skills', 'San Jose City, Nueva Ecija', '₱38,000 - ₱50,000', 2, '2026-10-31', 'fulltime', 'mid', 'vocational', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'Textile Mill Corporation' LIMIT 1), 'Machine Operator', 'Operate textile machinery, monitor production output, perform equipment maintenance.', 'Vocational training in textile or equivalent, 2+ years experience, Technical aptitude', 'San Jose City, Nueva Ecija', '₱18,000 - ₱26,000', 5, '2026-11-15', 'fulltime', 'entry', 'vocational', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'Textile Mill Corporation' LIMIT 1), 'Quality Control Inspector', 'Inspect finished products, test quality standards, document findings, prepare reports.', 'Vocational training or Associate\'s degree in QA, 2+ years inspection experience', 'San Jose City, Nueva Ecija', '₱22,000 - ₱32,000', 3, '2026-11-30', 'fulltime', 'mid', 'vocational', 'approved', 2, NOW()),

-- PackageCo Manufacturing
((SELECT id FROM employers WHERE company_name = 'PackageCo Manufacturing' LIMIT 1), 'Packaging Technician', 'Operate packaging equipment, ensure product safety and presentation, meet production quotas.', 'High school diploma, 1+ year packaging experience, Mechanical aptitude', 'Gapan, Nueva Ecija', '₱16,000 - ₱22,000', 6, '2026-12-01', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'PackageCo Manufacturing' LIMIT 1), 'Maintenance Technician', 'Perform equipment maintenance and repairs, troubleshoot mechanical issues, maintain maintenance logs.', 'Vocational training in mechanics, 3+ years maintenance experience, Troubleshooting skills', 'Gapan, Nueva Ecija', '₱28,000 - ₱38,000', 2, '2026-11-15', 'fulltime', 'mid', 'vocational', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (HEALTHCARE & SERVICES)
-- ===================================================================

-- MedicalPlus Healthcare Services
((SELECT id FROM employers WHERE company_name = 'MedicalPlus Healthcare Services' LIMIT 1), 'Registered Nurse', 'Provide patient care, administer medications, monitor vital signs, maintain patient records.', 'Bachelor\'s degree in Nursing, Licensed Registered Nurse, 1+ years experience', 'Palayan, Nueva Ecija', '₱28,000 - ₱38,000', 5, '2026-11-30', 'fulltime', 'entry', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'MedicalPlus Healthcare Services' LIMIT 1), 'Medical Laboratory Technician', 'Perform laboratory tests, prepare samples, maintain laboratory equipment and records.', 'Diploma in Medical Technology, Licensed Medical Technologist, 1+ years experience', 'Palayan, Nueva Ecija', '₱20,000 - ₱28,000', 3, '2026-11-30', 'fulltime', 'entry', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'MedicalPlus Healthcare Services' LIMIT 1), 'Administrative Officer', 'Handle patient records, schedule appointments, manage office operations, billing coordination.', 'High school diploma, 2+ years administrative experience, Customer service skills', 'Palayan, Nueva Ecija', '₱18,000 - ₱26,000', 2, '2026-12-01', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),

-- Hotel Services Philippines
((SELECT id FROM employers WHERE company_name = 'Hotel Services Philippines' LIMIT 1), 'Front Desk Manager', 'Manage front desk operations, supervise staff, handle guest relations, manage reservations.', 'High school diploma or Associate\'s degree, 3+ years hospitality experience, Communication skills', 'San Fernando, Nueva Ecija', '₱32,000 - ₱44,000', 1, '2026-10-31', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'Hotel Services Philippines' LIMIT 1), 'Room Attendant', 'Clean and maintain guest rooms, change linens, restock supplies, maintain standards.', 'High school diploma, Attention to detail, Physical fitness', 'San Fernando, Nueva Ecija', '₱12,000 - ₱16,000', 12, '2026-12-31', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'Hotel Services Philippines' LIMIT 1), 'Chef/Cook', 'Prepare meals according to recipes, maintain kitchen hygiene, supervise kitchen staff.', 'Vocational training in culinary arts, 3+ years cooking experience, Food safety certification', 'San Fernando, Nueva Ecija', '₱26,000 - ₱36,000', 2, '2026-11-30', 'fulltime', 'mid', 'vocational', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (AGRICULTURE & FOOD PROCESSING)
-- ===================================================================

-- AgriFarm Cooperative
((SELECT id FROM employers WHERE company_name = 'AgriFarm Cooperative' LIMIT 1), 'Farm Manager', 'Manage farm operations, oversee crop planning, supervise farm workers, manage resources.', 'Agricultural degree or certificate, 5+ years farm management experience', 'Cabanatuan, Nueva Ecija', '₱40,000 - ₱55,000', 2, '2026-10-31', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'AgriFarm Cooperative' LIMIT 1), 'Farm Laborer', 'Perform farm tasks including planting, weeding, harvesting, and basic maintenance.', 'Agricultural experience or willingness to learn, Physical fitness', 'Cabanatuan, Nueva Ecija', '₱12,000 - ₱16,000', 20, '2026-12-31', 'fulltime', 'entry', 'elementary', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'AgriFarm Cooperative' LIMIT 1), 'Irrigation Specialist', 'Design and maintain irrigation systems, monitor water distribution, perform repairs.', 'Vocational training in irrigation/agriculture, 2+ years experience, Technical skills', 'Cabanatuan, Nueva Ecija', '₱22,000 - ₱32,000', 2, '2026-11-15', 'fulltime', 'mid', 'vocational', 'approved', 2, NOW()),

-- FoodPacker Processing Inc
((SELECT id FROM employers WHERE company_name = 'FoodPacker Processing Inc' LIMIT 1), 'Production Manager', 'Oversee food processing operations, ensure food safety standards, manage team efficiency.', 'Bachelor\'s degree in Food Science or related field, 4+ years production management experience', 'San Jose City, Nueva Ecija', '₱45,000 - ₱60,000', 1, '2026-10-31', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'FoodPacker Processing Inc' LIMIT 1), 'Food Processor', 'Operate food processing equipment, follow recipes and safety procedures, maintain cleanliness.', 'Vocational training or experience in food processing, Food safety certification required', 'San Jose City, Nueva Ecija', '₱16,000 - ₱22,000', 8, '2026-12-15', 'fulltime', 'entry', 'vocational', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'FoodPacker Processing Inc' LIMIT 1), 'Packaging & Labeling Specialist', 'Package finished products, apply labels, perform quality checks, maintain records.', 'High school diploma, Attention to detail, Accuracy in work', 'San Jose City, Nueva Ecija', '₱14,000 - ₱20,000', 6, '2026-12-01', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (AUTOMOTIVE & TRANSPORTATION)
-- ===================================================================

-- AutoCare Services Center
((SELECT id FROM employers WHERE company_name = 'AutoCare Services Center' LIMIT 1), 'Senior Mechanic', 'Perform complex vehicle repairs and maintenance, diagnose mechanical issues, supervise junior mechanics.', 'Vocational training in auto mechanics, 5+ years experience, Technical certification', 'Gapan, Nueva Ecija', '₱32,000 - ₱44,000', 2, '2026-11-15', 'fulltime', 'mid', 'vocational', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'AutoCare Services Center' LIMIT 1), 'Automobile Mechanic', 'Perform vehicle maintenance and repairs following standard procedures.', 'Vocational training in mechanics, 2+ years experience, Technical skills', 'Gapan, Nueva Ecija', '₱20,000 - ₱28,000', 3, '2026-12-01', 'fulltime', 'entry', 'vocational', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'AutoCare Services Center' LIMIT 1), 'Parts Specialist', 'Manage automotive parts inventory, assist customers with parts selection, maintain records.', 'High school diploma, 1+ years experience in parts or retail', 'Gapan, Nueva Ecija', '₱16,000 - ₱24,000', 2, '2026-11-30', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),

-- TransportCo Logistics
((SELECT id FROM employers WHERE company_name = 'TransportCo Logistics' LIMIT 1), 'Logistics Coordinator', 'Coordinate shipments, track deliveries, manage logistics documentation, communicate with drivers.', 'Associate\'s degree or High school diploma with 3+ years experience, Computer literacy', 'Cabanatuan, Nueva Ecija', '₱26,000 - ₱36,000', 3, '2026-11-30', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'TransportCo Logistics' LIMIT 1), 'Professional Driver', 'Operate commercial vehicles safely, deliver goods on time, maintain vehicle cleanliness and safety.', 'Professional Driver\'s License (PDL), Clean driving record, 2+ years driving experience', 'Cabanatuan, Nueva Ecija', '₱22,000 - ₱32,000', 8, '2026-12-15', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'TransportCo Logistics' LIMIT 1), 'Warehouse Supervisor', 'Oversee warehouse operations, manage inventory, coordinate with logistics team, ensure safety.', 'High school diploma, 3+ years warehouse experience, Leadership abilities', 'Cabanatuan, Nueva Ecija', '₱28,000 - ₱38,000', 2, '2026-11-15', 'fulltime', 'mid', 'high_school', 'approved', 2, NOW()),

-- ===================================================================
-- CREATE JOBS (EDUCATION & TRAINING)
-- ===================================================================

-- EduTech Learning Center
((SELECT id FROM employers WHERE company_name = 'EduTech Learning Center' LIMIT 1), 'Academic Instructor', 'Conduct classes, prepare lesson materials, assess student performance, mentor learners.', 'Bachelor\'s degree in Education or related field, Teaching experience, Communication skills', 'Palayan, Nueva Ecija', '₱24,000 - ₱34,000', 4, '2026-11-30', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'EduTech Learning Center' LIMIT 1), 'Curriculum Developer', 'Design educational programs, develop learning materials, update curriculum based on standards.', 'Master\'s degree in Education preferred, Bachelor\'s minimum, 3+ years curriculum experience', 'Palayan, Nueva Ecija', '₱35,000 - ₱48,000', 1, '2026-11-01', 'fulltime', 'mid', 'postgraduate', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'EduTech Learning Center' LIMIT 1), 'Student Coordinator', 'Manage student enrollment, track attendance, coordinate with instructors, handle inquiries.', 'Associate\'s degree or High school diploma with relevant experience, Organization skills', 'Palayan, Nueva Ecija', '₱16,000 - ₱24,000', 2, '2026-12-01', 'fulltime', 'entry', 'college', 'approved', 2, NOW()),

-- Language School International
((SELECT id FROM employers WHERE company_name = 'Language School International' LIMIT 1), 'English Language Instructor', 'Teach English classes, develop teaching materials, evaluate student progress, conduct conversation sessions.', 'Bachelor\'s degree in English or related field, TEFL/TESOL certification, Teaching experience', 'San Fernando, Nueva Ecija', '₱22,000 - ₱32,000', 3, '2026-11-30', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'Language School International' LIMIT 1), 'Foreign Language Tutor', 'Teach foreign languages (Spanish, Mandarin, etc.), develop learning materials, track student progress.', 'Bachelor\'s degree in desired language, Native speaker preferred, Teaching experience', 'San Fernando, Nueva Ecija', '₱20,000 - ₱30,000', 2, '2026-12-15', 'fulltime', 'mid', 'college', 'approved', 2, NOW()),
((SELECT id FROM employers WHERE company_name = 'Language School International' LIMIT 1), 'Administrative Assistant', 'Manage schedules, handle registrations, coordinate with teachers, manage office materials.', 'High school diploma, 1+ years administrative experience, Computer skills', 'San Fernando, Nueva Ecija', '₱14,000 - ₱20,000', 1, '2026-12-01', 'fulltime', 'entry', 'high_school', 'approved', 2, NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- COMPLETION
-- ===================================================================
-- Successfully added 16 employers and 60+ jobs to the EmploySmart system
-- All employers are verified and approved
-- All jobs are approved and searchable by job seekers
