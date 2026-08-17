-- EmploySmart Database Reset and Seed Script
-- This script wipes all data except demo accounts and adds new realistic data
-- Execute this script in MySQL/phpMyAdmin or command line

USE employsmart;

-- Disable foreign key checks for deletion
SET FOREIGN_KEY_CHECKS = 0;

-- Delete all data and rebuild the seed state from scratch
DELETE FROM system_logs;
DELETE FROM job_matches;
DELETE FROM applications;
DELETE FROM job_skills;
DELETE FROM jobs;
DELETE FROM training_skills;
DELETE FROM user_training;
DELETE FROM training_programs;
DELETE FROM user_skills;
DELETE FROM skills;
DELETE FROM refresh_tokens;
DELETE FROM employers;
DELETE FROM users;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Disable foreign key checks for INSERT operations
SET FOREIGN_KEY_CHECKS = 0;

-- Restore core staff accounts for login and administration
INSERT INTO users (id, first_name, last_name, suffix, sex, birth_date, age, email, password, role, phone, alternate_phone, address, city, province, zip_code, profile_picture, civil_status, nationality, education_level, employment_status, resume_path, bio, is_verified, archived, created_at, updated_at) VALUES
(1, 'System', 'Administrator', NULL, 'male', NULL, NULL, 'admin@employsmart.com', 'admin123', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'single', 'Filipino', 'college', 'employed', NULL, NULL, 1, 0, NOW(), NULL),
(2, 'Juan', 'Dela Cruz', NULL, 'male', NULL, NULL, 'peso@employsmart.com', 'peso123', 'peso', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'single', 'Filipino', 'college', 'employed', NULL, NULL, 1, 0, NOW(), NULL),
(3, 'Maria', 'Santos', NULL, 'female', NULL, NULL, 'clcdo@employsmart.com', 'clcdo123', 'clcdo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'single', 'Filipino', 'college', 'employed', NULL, NULL, 1, 0, NOW(), NULL);

-- Insert new job seekers with Gmail accounts
INSERT INTO users (id, first_name, last_name, suffix, sex, birth_date, age, email, password, role, phone, alternate_phone, address, city, province, zip_code, profile_picture, civil_status, nationality, education_level, employment_status, resume_path, bio, is_verified, archived, created_at, updated_at) VALUES
(6, 'Reneboy', 'Manapol', NULL, 'male', '1995-05-15', 29, 'reneboymanapol@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09123456789', NULL, 'Brgy. San Jose, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', NULL, 'single', 'Filipino', 'college', 'unemployed', NULL, NULL, 1, 0, NOW(), NULL),
(7, 'Shan Michael', 'De Guzman', NULL, 'male', '1998-08-22', 26, 'shanmichaeldeguzman@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09187654321', NULL, 'Brgy. Santo Tomas, Cabanatuan', 'Cabanatuan', 'Nueva Ecija', '3100', NULL, 'single', 'Filipino', 'senior_high', 'employed', NULL, NULL, 1, 0, NOW(), NULL),
(8, 'Brian Jules', 'Asuncion', NULL, 'male', '1996-12-10', 28, 'brianjulesasuncion@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09234567890', NULL, 'Brgy. San Isidro, Gapan', 'Gapan', 'Nueva Ecija', '3105', NULL, 'married', 'Filipino', 'college', 'self_employed', NULL, NULL, 1, 0, NOW(), NULL),
(9, 'Ariana', 'Acibar', NULL, 'female', '1997-03-18', 27, 'arianaacibar@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09345678901', NULL, 'Brgy. Poblacion, Palayan', 'Palayan', 'Nueva Ecija', '3132', NULL, 'single', 'Filipino', 'college', 'unemployed', NULL, NULL, 1, 0, NOW(), NULL),
(10, 'Cris Norman', 'Olipas', NULL, 'male', '1985-06-20', 39, 'crisnormanolipas@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'employer', '09456789012', NULL, 'Brgy. San Jose, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', NULL, 'married', 'Filipino', 'college', 'employed', NULL, NULL, 1, 0, NOW(), NULL);

-- Create employer record for Cris Norman Olipas
INSERT INTO employers (user_id, company_name, industry, company_size, website, business_permit, company_address, contact_person, contact_email, contact_phone, verification_status, created_at) VALUES
(10, 'Olipas Construction Services', 'Construction', 'medium', 'https://olipasconstruction.com', 'BP-2024-001', 'Brgy. San Jose, San Fernando, Nueva Ecija', 'Cris Norman Olipas', 'crisnormanolipas@gmail.com', '09456789012', 'approved', NOW());

SET @employer_id = LAST_INSERT_ID();

-- Insert skills
INSERT INTO skills (skill_name, archived) VALUES
('Carpentry', 0),
('Masonry', 0),
('Electrical Work', 0),
('Plumbing', 0),
('Welding', 0),
('Painting', 0),
('Computer Skills', 0),
('Customer Service', 0),
('Bookkeeping', 0),
('Driving', 0),
('Cooking', 0),
('Baking', 0),
('Sewing', 0),
('Tailoring', 0),
('Agriculture', 0),
('Animal Husbandry', 0),
('Farming', 0),
('Gardening', 0),
('Beekeeping', 0),
('Food Processing', 0);

-- Insert livelihood training programs
INSERT INTO training_programs (program_name, description, created_by, start_date, end_date, max_participants, location, status, archived, created_at) VALUES
('Carpentry and Woodworking Skills Training', 'Comprehensive training program covering basic to advanced carpentry techniques, safety procedures, and tool usage for aspiring carpenters and woodworking enthusiasts.', 3, '2026-05-01', '2026-07-01', 25, 'TESDA Training Center, San Fernando, Nueva Ecija', 'upcoming', 0, NOW()),
('Basic Electrical Installation and Maintenance', 'Learn fundamental electrical concepts, wiring techniques, safety protocols, and basic troubleshooting for residential and small commercial electrical work.', 3, '2026-05-15', '2026-06-15', 20, 'Nueva Ecija Electrical Training Institute', 'upcoming', 0, NOW()),
('Plumbing and Pipe Fitting Course', 'Hands-on training in plumbing systems, pipe installation, water supply systems, drainage, and basic plumbing repairs and maintenance.', 3, '2026-06-01', '2026-07-15', 18, 'San Fernando City Technical School', 'upcoming', 0, NOW()),
('Food Processing and Preservation Workshop', 'Learn modern food processing techniques, preservation methods, packaging, and quality control for starting a small food business.', 3, '2026-04-20', '2026-05-20', 30, 'Department of Trade and Industry, Cabanatuan', 'upcoming', 0, NOW()),
('Beekeeping and Honey Production Training', 'Complete course on beekeeping practices, hive management, honey extraction, and marketing for sustainable livelihood.', 3, '2026-05-10', '2026-06-10', 15, 'Nueva Ecija Agricultural Training Center', 'upcoming', 0, NOW()),
('Tailoring and Dressmaking Skills Development', 'Professional training in pattern making, sewing techniques, garment construction, and fashion design for aspiring tailors and designers.', 3, '2026-06-15', '2026-08-15', 22, 'Women\'s Center, Palayan City', 'upcoming', 0, NOW()),
('Organic Vegetable Farming Course', 'Learn organic farming methods, crop management, pest control, and marketing strategies for sustainable vegetable production.', 3, '2026-04-25', '2026-06-25', 35, 'Nueva Ecija State University Extension', 'upcoming', 0, NOW()),
('Baking and Pastry Arts Program', 'Comprehensive baking course covering bread making, cake decoration, pastry techniques, and business management for food entrepreneurs.', 3, '2026-05-20', '2026-07-20', 20, 'Culinary Arts Center, Gapan City', 'upcoming', 0, NOW()),
('Motorcycle Repair and Maintenance Training', 'Technical training in motorcycle mechanics, engine repair, electrical systems, and diagnostic procedures.', 3, '2026-06-05', '2026-07-25', 16, 'Auto Technical Institute, San Jose City', 'upcoming', 0, NOW()),
('Computer Literacy and Digital Skills Workshop', 'Essential computer skills training including MS Office, internet usage, basic programming, and online business tools.', 3, '2026-04-15', '2026-05-15', 40, 'Public Employment Service Office, Nueva Ecija', 'upcoming', 0, NOW());

-- Insert training skills relationships
INSERT INTO training_skills (training_id, skill_id) VALUES
(1, 1), -- Carpentry program - Carpentry
(2, 3), -- Electrical program - Electrical Work
(3, 4), -- Plumbing program - Plumbing
(4, 20), -- Food Processing program - Food Processing
(5, 19), -- Beekeeping program - Beekeeping
(6, 13), -- Tailoring program - Sewing
(6, 14), -- Tailoring program - Tailoring
(7, 15), -- Organic Farming - Agriculture
(7, 16), -- Organic Farming - Farming
(7, 18), -- Organic Farming - Gardening
(8, 11), -- Baking program - Cooking
(8, 12), -- Baking program - Baking
(9, 10), -- Motorcycle Repair - Driving
(10, 7); -- Computer Literacy - Computer Skills

-- Insert user skills for job seekers
INSERT INTO user_skills (user_id, skill_id) VALUES
(6, 1), -- Reneboy - Carpentry
(6, 7), -- Reneboy - Computer Skills
(7, 3), -- Shan Michael - Electrical Work
(7, 7), -- Shan Michael - Computer Skills
(8, 4), -- Brian Jules - Plumbing
(8, 9), -- Brian Jules - Bookkeeping
(9, 11), -- Ariana - Cooking
(9, 12), -- Ariana - Baking
(9, 13); -- Ariana - Sewing

-- Insert sample jobs from the new employer
INSERT INTO jobs (employer_id, title, description, requirements, location, salary_range, vacancies, deadline, job_type, experience_level, education_required, approval_status, approved_by, archived, created_at) VALUES
(@employer_id, 'Carpenter Assistant', 'Looking for motivated individuals to join our construction team as carpenter assistants. Training will be provided for the right candidates.', 'Basic knowledge of tools, willingness to learn, physically fit', 'San Fernando, Nueva Ecija', '12,000 - 15,000', 3, '2026-05-30', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(@employer_id, 'Electrician Helper', 'Assist licensed electricians in residential and commercial electrical installations and maintenance work.', 'Basic electrical knowledge preferred, safety conscious, reliable transportation', 'Cabanatuan, Nueva Ecija', '14,000 - 18,000', 2, '2026-06-15', 'fulltime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(@employer_id, 'Plumbing Apprentice', 'Learn plumbing trade under experienced plumbers. Opportunity for career advancement in construction industry.', 'Mechanical aptitude, good work ethic, willing to work in various weather conditions', 'Gapan, Nueva Ecija', '13,000 - 16,000', 2, '2026-05-20', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW());

-- Insert job skills
INSERT INTO job_skills (job_id, skill_id)
SELECT id, 1 FROM jobs WHERE title = 'Carpenter Assistant';

INSERT INTO job_skills (job_id, skill_id)
SELECT id, 3 FROM jobs WHERE title = 'Electrician Helper';

INSERT INTO job_skills (job_id, skill_id)
SELECT id, 4 FROM jobs WHERE title = 'Plumbing Apprentice';

-- Insert sample applications
INSERT INTO applications (user_id, job_id, application_status, resume_used, cover_letter, applied_at) VALUES
(6, 1, 'pending', 'resume_reneboy.pdf', 'I am eager to learn carpentry and have basic tool knowledge.', NOW()),
(7, 2, 'pending', 'resume_shan.pdf', 'I have some electrical experience and am safety-conscious.', NOW()),
(8, 3, 'pending', 'resume_brian.pdf', 'I am mechanically inclined and willing to learn plumbing.', NOW()),
(9, 1, 'pending', 'resume_ariana.pdf', 'I am detail-oriented and would like to pursue a career in plumbing.', NOW());

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Database reset and seeding completed successfully! Demo accounts preserved, new data added.' AS status;