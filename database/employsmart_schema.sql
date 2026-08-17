
CREATE DATABASE IF NOT EXISTS employsmart;
USE employsmart;


SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS feedback_threads;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS job_matches;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS job_skills;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS training_skills;
DROP TABLE IF EXISTS user_training;
DROP TABLE IF EXISTS training_programs;
DROP TABLE IF EXISTS user_skills;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS employers;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    last_name VARCHAR(100),
    suffix VARCHAR(20),

    sex ENUM('male','female','other') NOT NULL,
    birth_date DATE,
    age INT,

    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),

    role ENUM('admin','peso','clcdo','employer','jobseeker') DEFAULT 'jobseeker',

    phone VARCHAR(30),
    alternate_phone VARCHAR(30),

    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    zip_code VARCHAR(20),

    profile_picture VARCHAR(255),

    civil_status ENUM('single','married','widowed','separated'),
    nationality VARCHAR(100) DEFAULT 'Filipino',

    education_level ENUM(
        'no_formal_education',
        'elementary',
        'high_school',
        'senior_high',
        'vocational',
        'college',
        'postgraduate'
    ),

    employment_status ENUM(
        'unemployed',
        'employed',
        'self_employed',
        'student'
    ) DEFAULT 'unemployed',

    resume_path VARCHAR(255),
    bio TEXT,

    is_verified BOOLEAN DEFAULT FALSE,
    archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(500) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_tokens (user_id),
    INDEX idx_token_expiry (expires_at)
);


CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_key (key_hash),
    INDEX idx_rate_created_at (created_at)
);


CREATE TABLE employers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    company_name VARCHAR(255),
    industry VARCHAR(150),

    company_size ENUM('small','medium','large'),
    website VARCHAR(255),

    business_permit VARCHAR(255),
    company_address TEXT,

    contact_person VARCHAR(150),
    contact_email VARCHAR(150),
    contact_phone VARCHAR(50),

    verification_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(150) UNIQUE,
    archived BOOLEAN DEFAULT FALSE
);


CREATE TABLE user_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    skill_id INT,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);


CREATE TABLE training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(255),
    description TEXT,
    created_by INT,

    start_date DATE,
    end_date DATE,

    max_participants INT,
    location VARCHAR(255),
    status ENUM('upcoming','ongoing','completed') DEFAULT 'upcoming',
    archived BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id)
);


CREATE TABLE user_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    training_id INT,

    status ENUM('enrolled','completed','dropped') DEFAULT 'enrolled',
    certificate_path VARCHAR(255),
    completion_date DATE,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES training_programs(id) ON DELETE CASCADE
);

CREATE TABLE training_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT,
    skill_id INT,

    FOREIGN KEY (training_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);


CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT,

    title VARCHAR(255),
    description TEXT,
    requirements TEXT,

    location VARCHAR(255),
    salary_range VARCHAR(100),

    vacancies INT DEFAULT 1,
    deadline DATE,

    job_type ENUM('fulltime','parttime','contract','internship'),
    experience_level ENUM('entry','mid','senior'),
    education_required VARCHAR(150),

    approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by INT,
    archived BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (employer_id) REFERENCES employers(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);


CREATE TABLE job_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    skill_id INT,

    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);


CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    user_id INT,

    application_status ENUM('pending','reviewed','accepted','rejected') DEFAULT 'pending',

    resume_used VARCHAR(255),
    cover_letter TEXT,
    interview_date DATETIME,
    remarks TEXT,

    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE job_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    job_id INT,
    match_score DECIMAL(5,2),
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);


CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE feedback_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    sender_role VARCHAR(50),
    target_type ENUM('employer','jobseeker','placement') NOT NULL,
    target_id INT NOT NULL,
    rating INT DEFAULT 5,
    feedback TEXT NOT NULL,
    status ENUM('open','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),

    ip_address VARCHAR(50),
    user_agent TEXT,

    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id)
);


CREATE INDEX idx_user_skills_user ON user_skills(user_id);
CREATE INDEX idx_job_skills_job ON job_skills(job_id);
CREATE INDEX idx_user_training_user ON user_training(user_id);
CREATE INDEX idx_applications_job ON applications(job_id);


INSERT INTO users 
(first_name,last_name,sex,email,password,role,is_verified)
VALUES
('System','Administrator','male','admin@employsmart.com','admin123','admin',1),
('Juan','Dela Cruz','male','peso@employsmart.com','peso123','peso',1),
('Maria','Santos','female','clcdo@employsmart.com','clcdo123','clcdo',1);

INSERT INTO skills (skill_name) VALUES
('Cooking'),
('Baking'),
('Customer Service'),
('Computer Literacy'),
('Carpentry'),
('Plumbing'),
('Electrical Repair'),
('Graphic Design'),
('Programming'),
('Accounting');

INSERT INTO training_programs
(program_name,description,created_by,location,max_participants)
VALUES
('Basic Cooking Training','Livelihood cooking program',3,'Cabanatuan City',30),
('Computer Skills Training','Basic IT training',3,'Cabanatuan City',25),
('Carpentry Skills Training','Woodworking training',3,'Nueva Ecija',20);

INSERT INTO training_skills (training_id,skill_id) VALUES
(1,1),
(1,3),
(2,4),
(2,9),
(3,5);