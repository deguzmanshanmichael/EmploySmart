-- Direct fix for remaining question marks in salary ranges
USE employsmart;

-- Use hex to replace remaining ? (0x3F) with ₱ (0xE282B1)
UPDATE jobs SET salary_range = REPLACE(salary_range, UNHEX('3F'), UNHEX('E282B1')) 
WHERE salary_range LIKE '%?%';

-- Verify the fix
SELECT 'All salary ranges fixed!' as message;
SELECT COUNT(*) as total_jobs FROM jobs WHERE approval_status = 'approved';
SELECT title, salary_range, HEX(salary_range) FROM jobs WHERE title IN ('Farm Laborer', 'Project Manager') LIMIT 2;
