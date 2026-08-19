-- EmploySmart Database Reset and Seed Script
-- Adds realistic demo records for a live deployment: 3 staff, 10 regular accounts, 20+ jobs, 20+ trainings.


SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO users (id, first_name, last_name, suffix, sex, birth_date, age, email, password, role, phone, alternate_phone, address, city, province, zip_code, profile_picture, civil_status, nationality, education_level, employment_status, resume_path, bio, is_verified, archived, created_at, updated_at) VALUES
(1, 'System', 'Administrator', NULL, 'male', '1980-01-15', 46, 'admin@employsmart.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'admin', '09000000001', NULL, 'Provincial Government Center', 'Palayan City', 'Nueva Ecija', '3132', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'System administrator overseeing platform users and approvals.', 1, 0, NOW(), NULL),
(2, 'Juan', 'Dela Cruz', NULL, 'male', '1985-02-19', 41, 'peso@employsmart.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'peso', '09000000002', NULL, 'PESO Office, City Hall', 'Cabanatuan City', 'Nueva Ecija', '3100', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'PESO staff supporting job placements and employment programs.', 1, 0, NOW(), NULL),
(3, 'Maria', 'Santos', NULL, 'female', '1988-09-28', 37, 'clcdo@employsmart.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'clcdo', '09000000003', NULL, 'CLCDO Office, Municipal Hall', 'San Jose City', 'Nueva Ecija', '3121', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'CLCDO officer coordinating livelihood training and community programs.', 1, 0, NOW(), NULL);
UPDATE users SET password = '$2y$10$rkfjo3FQhgna0IP6RvkCWOFLZN0whnzk6dYNy2IwKKpDxCs7Q29Ay' WHERE email = 'admin@employsmart.com';
UPDATE users SET password = '$2y$10$Zzl/fWfahphZeT9VYbQkeeAjzPakrMUiIxDXAsY9Wd4swz8RSpHNi' WHERE email = 'peso@employsmart.com';
UPDATE users SET password = '$2y$10$gQRbkUeq5awxvNiJO1fFduSYKV2JYfd0Zwn0AJiUbbLnKSdugVboC' WHERE email = 'clcdo@employsmart.com';

-- Regular user accounts: 10 total (5 employers + 5 jobseekers)
INSERT INTO users (id, first_name, last_name, suffix, sex, birth_date, age, email, password, role, phone, alternate_phone, address, city, province, zip_code, profile_picture, civil_status, nationality, education_level, employment_status, resume_path, bio, is_verified, archived, created_at, updated_at) VALUES
(4, 'Alicia', 'Ramos', NULL, 'female', '1987-03-18', 39, 'alicia.ramos@employsmart.test', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'employer', '09150000001', NULL, 'Brgy. Rizal', 'Palayan City', 'Nueva Ecija', '3132', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'Business owner focused on service and retail operations.', 1, 0, NOW(), NULL),
(5, 'Jerome', 'Villanueva', NULL, 'male', '1983-05-24', 43, 'jerome.villanueva@employsmart.test', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'employer', '09150000002', NULL, 'Brgy. Sta. Rosa', 'Cabanatuan City', 'Nueva Ecija', '3100', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'Operations manager supporting hospitality and services businesses.', 1, 0, NOW(), NULL),
(6, 'Melissa', 'Torres', NULL, 'female', '1991-11-12', 34, 'melissa.torres@employsmart.test', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'employer', '09150000003', NULL, 'Brgy. San Juan', 'Gapan City', 'Nueva Ecija', '3105', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'Employer promoting local talent in food and retail services.', 1, 0, NOW(), NULL),
(7, 'Rafael', 'Castro', NULL, 'male', '1989-08-07', 36, 'rafael.castro@employsmart.test', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'employer', '09150000004', NULL, 'Brgy. Santo Cristo', 'San Jose City', 'Nueva Ecija', '3121', NULL, 'married', 'Filipino', 'college', 'employed', NULL, 'Contractor and entrepreneur with multiple service-based teams.', 1, 0, NOW(), NULL),
(8, 'Toni', 'Mendoza', NULL, 'female', '1984-12-02', 41, 'toni.mendoza@employsmart.test', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'employer', '09150000005', NULL, 'Brgy. San Agustin', 'Muñoz', 'Nueva Ecija', '3119', NULL, 'married', 'Filipino', 'postgraduate', 'employed', NULL, 'Agribusiness and training advocate hiring local workers.', 1, 0, NOW(), NULL),
(9, 'Reneboy', 'Manapol', NULL, 'male', '1995-05-15', 29, 'reneboymanapol@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09123456789', NULL, 'Brgy. San Jose, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', NULL, 'single', 'Filipino', 'college', 'unemployed', 'uploads/resumes/resume_9.pdf', 'Skilled worker looking for construction and carpentry opportunities.', 1, 0, NOW(), NULL),
(10, 'Shan Michael', 'De Guzman', NULL, 'male', '1998-08-22', 26, 'shanmichaeldeguzman@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09187654321', NULL, 'Brgy. Santo Tomas, Cabanatuan', 'Cabanatuan', 'Nueva Ecija', '3100', NULL, 'single', 'Filipino', 'senior_high', 'employed', 'uploads/resumes/resume_10.pdf', 'Customer-focused professional with tech and service experience.', 1, 0, NOW(), NULL),
(11, 'Brian Jules', 'Asuncion', NULL, 'male', '1996-12-10', 28, 'brianjulesasuncion@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09234567890', NULL, 'Brgy. San Isidro, Gapan', 'Gapan', 'Nueva Ecija', '3105', NULL, 'married', 'Filipino', 'college', 'self_employed', 'uploads/resumes/resume_11.pdf', 'Looking for steady employment in operations, service, and maintenance.', 1, 0, NOW(), NULL),
(12, 'Ariana', 'Acibar', NULL, 'female', '1997-03-18', 27, 'arianaacibar@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09345678901', NULL, 'Brgy. Poblacion, Palayan', 'Palayan', 'Nueva Ecija', '3132', NULL, 'single', 'Filipino', 'college', 'unemployed', 'uploads/resumes/resume_12.pdf', 'Food service and hospitality worker aiming for better job opportunities.', 1, 0, NOW(), NULL),
(13, 'Cris Norman', 'Olipas', NULL, 'male', '1985-06-20', 39, 'crisnormanolipas@gmail.com', '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi', 'jobseeker', '09456789012', NULL, 'Brgy. San Jose, San Fernando', 'San Fernando', 'Nueva Ecija', '3100', NULL, 'married', 'Filipino', 'college', 'employed', 'uploads/resumes/resume_13.pdf', 'Career-minded professional with hands-on project coordination experience.', 1, 0, NOW(), NULL);

INSERT INTO employers (id, user_id, company_name, industry, company_size, website, business_permit, company_address, contact_person, contact_email, contact_phone, verification_status, archived, created_at) VALUES
(1, 4, 'Ramos Food & Services Co.', 'Hospitality', 'small', 'https://ramosfoodservices.test', 'BP-2025-001', 'Brgy. Rizal, Palayan City', 'Alicia Ramos', 'alicia.ramos@employsmart.test', '09150000001', 'approved', 0, NOW()),
(2, 5, 'Villanueva Construction Supply', 'Construction', 'medium', 'https://villanueva-supply.test', 'BP-2025-002', 'Brgy. Sta. Rosa, Cabanatuan City', 'Jerome Villanueva', 'jerome.villanueva@employsmart.test', '09150000002', 'approved', 0, NOW()),
(3, 6, 'Torres Retail Partners', 'Retail', 'medium', 'https://torresretail.test', 'BP-2025-003', 'Brgy. San Juan, Gapan City', 'Melissa Torres', 'melissa.torres@employsmart.test', '09150000003', 'approved', 0, NOW()),
(4, 7, 'Castro Works & Maintenance', 'Services', 'small', 'https://castroworks.test', 'BP-2025-004', 'Brgy. Santo Cristo, San Jose City', 'Rafael Castro', 'rafael.castro@employsmart.test', '09150000004', 'approved', 0, NOW()),
(5, 8, 'Mendoza Agribusiness Group', 'Agriculture', 'medium', 'https://mendozaagri.test', 'BP-2025-005', 'Brgy. San Agustin, Muñoz', 'Toni Mendoza', 'toni.mendoza@employsmart.test', '09150000005', 'approved', 0, NOW());

INSERT INTO skills (id, skill_name, archived) VALUES
(1, 'Carpentry', 0),
(2, 'Masonry', 0),
(3, 'Electrical Work', 0),
(4, 'Plumbing', 0),
(5, 'Welding', 0),
(6, 'Painting', 0),
(7, 'Computer Skills', 0),
(8, 'Customer Service', 0),
(9, 'Bookkeeping', 0),
(10, 'Driving', 0),
(11, 'Cooking', 0),
(12, 'Baking', 0),
(13, 'Sewing', 0),
(14, 'Tailoring', 0),
(15, 'Agriculture', 0),
(16, 'Animal Husbandry', 0),
(17, 'Farming', 0),
(18, 'Gardening', 0),
(19, 'Beekeeping', 0),
(20, 'Food Processing', 0),
(21, 'Inventory Management', 0),
(22, 'Sales', 0),
(23, 'Project Coordination', 0),
(24, 'Hospitality', 0),
(25, 'Mechanics', 0);

INSERT INTO user_skills (user_id, skill_id) VALUES
(9, 1), (9, 7), (9, 23),
(10, 8), (10, 7), (10, 10),
(11, 9), (11, 7), (11, 21),
(12, 11), (12, 12), (12, 24),
(13, 23), (13, 3), (13, 21);

INSERT INTO training_programs (id, program_name, description, created_by, start_date, end_date, max_participants, location, status, archived, created_at) VALUES
(1, 'Carpentry and Woodworking Skills Training', 'Hands-on carpentry basics for residential repairs, furniture work, and small construction projects.', 3, '2026-01-10', '2026-02-28', 25, 'TESDA Training Center, San Fernando', 'ongoing', 0, NOW()),
(2, 'Basic Electrical Installation and Maintenance', 'Learn wiring, troubleshooting, and safe installation for residential systems.', 3, '2026-01-12', '2026-03-05', 20, 'Nueva Ecija Technical Institute', 'ongoing', 0, NOW()),
(3, 'Plumbing and Pipe Fitting Course', 'Install water lines, fix leaks, and maintain effective drainage systems.', 3, '2026-02-02', '2026-03-25', 18, 'San Fernando City Technical School', 'upcoming', 0, NOW()),
(4, 'Food Processing and Preservation Workshop', 'Boost product quality and shelf life through processing and safe preservation techniques.', 3, '2026-01-20', '2026-03-10', 30, 'Department of Trade and Industry, Cabanatuan', 'ongoing', 0, NOW()),
(5, 'Beekeeping and Honey Production Training', 'Understand colony care, harvesting methods, and beekeeping safety.', 3, '2026-02-15', '2026-04-15', 15, 'Agriculture Office, Muñoz', 'upcoming', 0, NOW()),
(6, 'Tailoring and Dressmaking Skills Development', 'Pattern making, garment fitting, and sewing for start-up clothing work.', 3, '2026-02-18', '2026-04-30', 22, 'Women\'s Center, Palayan City', 'upcoming', 0, NOW()),
(7, 'Organic Vegetable Farming Course', 'Practical crop management, composting, and organic production techniques.', 3, '2026-01-25', '2026-03-28', 35, 'Nueva Ecija State University Extension', 'ongoing', 0, NOW()),
(8, 'Baking and Pastry Arts Program', 'Bread, pastry, and cake fundamentals for bakery and food entrepreneurship.', 3, '2026-03-01', '2026-05-05', 20, 'Culinary Arts Center, Gapan City', 'upcoming', 0, NOW()),
(9, 'Motorcycle Repair and Maintenance Training', 'Basic repairs, inspections, and preventive maintenance for motorcycle units.', 3, '2026-02-10', '2026-04-02', 16, 'Auto Technical Institute, San Jose City', 'upcoming', 0, NOW()),
(10, 'Computer Literacy and Digital Skills Workshop', 'Core digital skills for office work, e-commerce, and online tasks.', 3, '2026-01-15', '2026-02-15', 40, 'Public Employment Service Office, Nueva Ecija', 'completed', 0, NOW()),
(11, 'Customer Service Excellence', 'Improve communication, complaint handling, and sales support for retail and service jobs.', 3, '2026-03-10', '2026-04-20', 28, 'City Hall Training Room, Cabanatuan', 'upcoming', 0, NOW()),
(12, 'Bookkeeping for Small Businesses', 'Master basic accounting records, cash flow, and transaction management.', 3, '2026-02-20', '2026-04-10', 24, 'PESO Training Hub, Guimba', 'upcoming', 0, NOW()),
(13, 'Hospitality and Front Desk Skills', 'Training on guest relations, reservations, and service professionalism.', 3, '2026-03-05', '2026-04-25', 26, 'Nayon Hotel Training Center, Palayan', 'upcoming', 0, NOW()),
(14, 'Inventory and Warehouse Operations', 'Learn stock handling, order processing, and warehouse documentation.', 3, '2026-01-18', '2026-03-15', 20, 'Industrial Learning Center, San Jose', 'ongoing', 0, NOW()),
(15, 'Food Service and Barista Basics', 'Prepare for cafe work with customer service and beverage fundamentals.', 3, '2026-02-22', '2026-04-12', 18, 'Café Skills Training Room, Gapan', 'upcoming', 0, NOW()),
(16, 'Project Coordination Essentials', 'Build coordination skills for operations teams, siteworks, and project scheduling.', 3, '2026-02-05', '2026-03-22', 20, 'Provincial Planning Office, Palayan', 'upcoming', 0, NOW()),
(17, 'Agriculture Value Chain Basics', 'Understand farm planning, produce handling, and market-ready product preparation.', 3, '2026-03-08', '2026-05-08', 30, 'Agriculture Learning Hub, Muñoz', 'upcoming', 0, NOW()),
(18, 'Home Gardening and Urban Farming', 'Grow vegetables and herbs using simple techniques for home or community spaces.', 3, '2026-01-30', '2026-03-15', 32, 'Municipal Community Garden, San Jose', 'ongoing', 0, NOW()),
(19, 'Welding and Metal Fabrication', 'Practical training for welding fundamentals, safety standards, and fabrication tasks.', 3, '2026-03-12', '2026-05-10', 18, 'Metalworks Technology Center, Cabanatuan', 'upcoming', 0, NOW()),
(20, 'Sales and Merchandising Bootcamp', 'Learn effective selling, visual merchandising, and client engagement.', 3, '2026-02-12', '2026-03-30', 24, 'Retail Academy, Gapan City', 'ongoing', 0, NOW()),
(21, 'Basic Mechanics for Service Work', 'Develop practical troubleshooting and preventative repair skills for equipment and vehicles.', 3, '2026-03-18', '2026-05-18', 22, 'Service Skills Center, Palayan', 'upcoming', 0, NOW()),
(22, 'Community Livelihood Starter Kit', 'Build entrepreneurial confidence with product planning, costing, and local market entry.', 3, '2026-01-17', '2026-02-27', 25, 'PESO Community Hall, San Fernando', 'completed', 0, NOW());

INSERT INTO training_skills (training_id, skill_id) VALUES
(1,1),(1,6),(2,3),(3,4),(4,20),(5,19),(6,13),(6,14),(7,15),(7,17),(7,18),(8,11),(8,12),(9,25),(10,7),(11,8),(12,9),(13,8),(13,24),(14,21),(15,11),(15,8),(16,23),(17,15),(17,17),(18,18),(19,5),(20,22),(21,25),(22,23),(22,22);

INSERT INTO user_training (user_id, training_id, status, certificate_path, completion_date) VALUES
(9, 1, 'completed', 'certificates/reneboy_carpentry.pdf', '2026-02-20'),
(10, 10, 'completed', 'certificates/shan_digital.pdf', '2026-02-15'),
(11, 12, 'enrolled', NULL, NULL),
(12, 15, 'enrolled', NULL, NULL),
(13, 2, 'completed', 'certificates/cris_electrical.pdf', '2026-03-01');

INSERT INTO jobs (id, employer_id, title, description, requirements, location, salary_range, vacancies, deadline, job_type, experience_level, education_required, approval_status, approved_by, archived, created_at) VALUES
(1, 1, 'Food Service Crew', 'Serve customers, prepare menu items, and maintain cleanliness in the dining area.', 'Customer service attitude, ability to work flexible shifts, high school graduate preferred', 'Palayan City, Nueva Ecija', '₱14,000 - ₱18,000', 4, '2026-08-30', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(2, 1, 'Cashier and Sales Associate', 'Handle customer transactions and assist with point-of-sale operations.', 'Basic math skills, customer service experience, good communication', 'Palayan City, Nueva Ecija', '₱15,000 - ₱19,000', 3, '2026-08-25', 'fulltime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(3, 2, 'Construction Worker', 'Participate in site preparation, building tasks, and material handling.', 'Physically fit, willing to work onsite, basic carpentry or masonry knowledge', 'Cabanatuan City, Nueva Ecija', '₱16,000 - ₱22,000', 6, '2026-08-22', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(4, 2, 'Carpenter Helper', 'Assist carpenters with form work, finishing, and on-site repairs.', 'Basic carpentry skills, tool familiarity, safety awareness', 'Cabanatuan City, Nueva Ecija', '₱15,000 - ₱20,000', 4, '2026-08-27', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(5, 2, 'Electrician Apprentice', 'Work under licensed electricians on small installation and maintenance tasks.', 'Basic electrical skills, willingness to learn, comfortable with physical work', 'Cabanatuan City, Nueva Ecija', '₱16,000 - ₱21,000', 3, '2026-08-18', 'fulltime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(6, 3, 'Retail Sales Agent', 'Promote products and provide customer assistance in a retail environment.', 'Salesability, flexible schedule, customer service knowledge', 'Gapan City, Nueva Ecija', '₱14,500 - ₱18,500', 5, '2026-08-29', 'fulltime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(7, 3, 'Store Inventory Clerk', 'Track stock movement and ensure product availability in store shelves.', 'Organized, detail-oriented, basic Microsoft Office proficiency', 'Gapan City, Nueva Ecija', '₱15,000 - ₱18,000', 2, '2026-08-23', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(8, 3, 'Customer Support Associate', 'Answer inquiries and assist customers through sales and service channels.', 'Good communication skills, patience, basic computer literacy', 'Gapan City, Nueva Ecija', '₱15,500 - ₱19,000', 3, '2026-08-28', 'fulltime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(9, 4, 'Maintenance Technician', 'Perform minor repairs and routine upkeep for facilities and equipment.', 'Mechanical aptitude, problem solving, safety awareness', 'San Jose City, Nueva Ecija', '₱18,000 - ₱24,000', 2, '2026-08-19', 'fulltime', 'mid', 'senior_high', 'approved', 2, 0, NOW()),
(10, 4, 'General Services Worker', 'Support cleaning, facilities, and basic maintenance tasks across sites.', 'Dependable, physically fit, willingness to work on schedule', 'San Jose City, Nueva Ecija', '₱14,000 - ₱17,500', 5, '2026-08-31', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(11, 4, 'Plumbing Helper', 'Assist with water system installations, pipe fitting, and repair tasks.', 'Basic plumbing knowledge, physically fit, work safety conscious', 'San Jose City, Nueva Ecija', '₱15,000 - ₱19,000', 3, '2026-08-21', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(12, 5, 'Farm Worker', 'Support field operations, harvesting, soil preparation, and crop care.', 'Interest in agriculture, physically fit, comfortable outdoors', 'Muñoz, Nueva Ecija', '₱13,500 - ₱17,000', 7, '2026-08-26', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(13, 5, 'Agricultural Technician', 'Assist with farm planning, crop monitoring, and sustainability programs.', 'Agriculture knowledge, attention to detail, basic report writing', 'Muñoz, Nueva Ecija', '₱18,000 - ₱24,000', 2, '2026-08-24', 'fulltime', 'mid', 'college', 'approved', 2, 0, NOW()),
(14, 5, 'Poultry Farm Assistant', 'Support feeding, cleaning, and daily care tasks for poultry operations.', 'Teamwork, willingness to work early shifts, basic record keeping', 'Muñoz, Nueva Ecija', '₱14,000 - ₱17,500', 4, '2026-08-27', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(15, 1, 'Bakery Production Helper', 'Assist in ingredient preparation, baking, and packaging of bakery items.', 'Willingness to work in food production, attention to detail', 'Palayan City, Nueva Ecija', '₱14,000 - ₱18,000', 3, '2026-09-02', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(16, 2, 'Warehouse Staff', 'Handle loading, stock organization, and documentation of inventory.', 'Organized, dependable, able to lift moderately heavy goods', 'Cabanatuan City, Nueva Ecija', '₱16,000 - ₱20,000', 2, '2026-08-20', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(17, 3, 'Merchandising Assistant', 'Assist in product display, stock arrangement, and customer-facing presentation.', 'Creative, neat, customer service oriented', 'Gapan City, Nueva Ecija', '₱14,000 - ₱17,000', 2, '2026-08-30', 'parttime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(18, 4, 'Machine Operator', 'Operate small machinery and ensure smooth process execution in production work.', 'Mechanical skills, alertness, safety compliance', 'San Jose City, Nueva Ecija', '₱17,000 - ₱22,000', 2, '2026-08-18', 'fulltime', 'mid', 'senior_high', 'approved', 2, 0, NOW()),
(19, 5, 'Garden and Nursery Worker', 'Support nursery work, planting, and plant care for farm and retail use.', 'Comfortable outdoors, interest in gardening and plants', 'Muñoz, Nueva Ecija', '₱13,000 - ₱16,000', 5, '2026-08-28', 'fulltime', 'entry', 'high_school', 'approved', 2, 0, NOW()),
(20, 1, 'Hotel Front Desk Receptionist', 'Assist guests, handle check-ins, and manage front desk service operations.', 'Good communication, customer service, basic computer literacy', 'Palayan City, Nueva Ecija', '₱16,000 - ₱21,000', 2, '2026-09-05', 'fulltime', 'entry', 'senior_high', 'approved', 2, 0, NOW()),
(21, 2, 'Site Engineer Assistant', 'Support field engineers with documentation, monitoring, and on-site coordination.', 'Problem solving, organized, civil or related educational background preferred', 'Cabanatuan City, Nueva Ecija', '₱20,000 - ₱28,000', 2, '2026-08-24', 'contract', 'mid', 'college', 'approved', 2, 0, NOW()),
(22, 3, 'Office Administrative Assistant', 'Support daily office operations, scheduling, and administrative records.', 'Organized, good communication, basic clerical skills', 'Gapan City, Nueva Ecija', '₱15,000 - ₱18,500', 3, '2026-08-26', 'fulltime', 'entry', 'college', 'approved', 2, 0, NOW());

INSERT INTO job_skills (job_id, skill_id) VALUES
(1,8),(1,24),(2,8),(2,22),(3,1),(3,2),(4,1),(5,3),(6,8),(6,22),(7,21),(8,8),(8,7),(9,25),(10,21),(11,4),(12,15),(12,17),(13,15),(13,17),(14,16),(15,11),(15,12),(16,21),(17,8),(17,22),(18,25),(19,18),(20,8),(20,7),(21,23),(22,7),(22,8);

INSERT INTO applications (id, job_id, user_id, application_status, resume_used, cover_letter, applied_at) VALUES
(1, 3, 9, 'pending', 'uploads/resumes/resume_9.pdf', 'I am interested in construction work and can work on-site with the team.', NOW()),
(2, 1, 10, 'reviewed', 'uploads/resumes/resume_10.pdf', 'I am friendly and comfortable serving customers in fast-paced settings.', NOW()),
(3, 6, 10, 'pending', 'uploads/resumes/resume_10.pdf', 'I have good customer communication skills and sales experience.', NOW()),
(4, 4, 9, 'accepted', 'uploads/resumes/resume_9.pdf', 'I am eager to learn carpentry and help with finishing works.', NOW()),
(5, 12, 11, 'pending', 'uploads/resumes/resume_11.pdf', 'I am ready to support agriculture tasks and farm operations.', NOW()),
(6, 13, 13, 'reviewed', 'uploads/resumes/resume_13.pdf', 'I have experience in coordination and technical tasks relevant to agriculture support.', NOW()),
(7, 22, 12, 'pending', 'uploads/resumes/resume_12.pdf', 'I enjoy office work and customer support in organized environments.', NOW()),
(8, 15, 12, 'pending', 'uploads/resumes/resume_12.pdf', 'I am interested in bakery and food production work, especially hands-on tasks.', NOW()),
(9, 20, 10, 'accepted', 'uploads/resumes/resume_10.pdf', 'I am comfortable in guest service roles and digital communication.', NOW()),
(10, 8, 11, 'pending', 'uploads/resumes/resume_11.pdf', 'I am organized and can help customers with store needs and product support.', NOW());

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'EmploySmart demo data seeded successfully. 3 staff, 10 user accounts, 22 jobs, 22 trainings, and supporting records created.' AS status;
