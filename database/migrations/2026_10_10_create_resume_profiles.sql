-- EmploySmart: resume-driven job recommendations
-- Stores one active uploaded resume per jobseeker together with the information
-- extracted from it and the version the jobseeker reviewed and confirmed.
--
-- The API also creates this table automatically on first request
-- (see server/config/database.php -> ensureRequiredTables), so running this file
-- manually in phpMyAdmin is optional. It is safe to run more than once.

CREATE TABLE IF NOT EXISTS resume_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    mime_type VARCHAR(100),
    file_size INT,
    file_hash CHAR(64),
    page_count INT,
    status ENUM('needs_ocr','parsed','confirmed') NOT NULL DEFAULT 'parsed',
    extraction_method VARCHAR(30),
    ai_used TINYINT(1) NOT NULL DEFAULT 0,
    raw_text MEDIUMTEXT,
    parsed_data LONGTEXT,
    confirmed_data LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
