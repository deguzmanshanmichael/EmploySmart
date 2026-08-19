-- Restore missing demo accounts
USE employsmart;

SET FOREIGN_KEY_CHECKS = 0;

-- Insert missing demo accounts
INSERT INTO users (id, first_name, last_name, suffix, sex, birth_date, age, email, password, role, phone, alternate_phone, address, city, province, zip_code, profile_picture, civil_status, nationality, education_level, employment_status, resume_path, bio, is_verified, archived, created_at, updated_at) VALUES
(16, 'Admin', 'User', NULL, 'male', '1980-01-01', 46, 'admin@employsmart.com', '$2y$10$rkfjo3FQhgna0IP6RvkCWOFLZN0whnzk6dYNy2IwKKpDxCs7Q29Ay', 'admin', '09123456789', NULL, 'Admin Office, City Hall', 'Palayan City', 'Nueva Ecija', '3132', NULL, 'married', 'Filipino', 'college', 'employed', NULL, NULL, 1, 0, NOW(), NULL),
(17, 'PESO', 'Staff', NULL, 'female', '1985-03-15', 41, 'peso@employsmart.com', '$2y$10$Zzl/fWfahphZeT9VYbQkeeAjzPakrMUiIxDXAsY9Wd4swz8RSpHNi', 'peso', '09187654321', NULL, 'PESO Office, Municipal Hall', 'Cabanatuan City', 'Nueva Ecija', '3100', NULL, 'married', 'Filipino', 'college', 'employed', NULL, NULL, 1, 0, NOW(), NULL);

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Demo accounts restored successfully!' AS status;