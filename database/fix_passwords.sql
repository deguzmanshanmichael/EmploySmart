-- Fix password hashes for admin and peso accounts
USE employsmart;

UPDATE users SET password = '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi' WHERE id = 16;
UPDATE users SET password = '$2y$10$vOiDlU2Ce/D6hTvePiXq9eTCXpBve0ISP0JMDA2lrpFLzMJHPHVHi' WHERE id = 17;

SELECT 'Password hashes updated successfully!' AS status;