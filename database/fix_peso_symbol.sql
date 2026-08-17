-- Comprehensive fix for all peso symbols in salary ranges
USE employsmart;

-- Fix all salary ranges to have proper peso symbols (replace ALL ? with ₱)
UPDATE jobs SET salary_range = REPLACE(salary_range, '?', '₱') 
WHERE salary_range LIKE '%?%';

-- Verify the fix
SELECT 'All salary ranges fixed with peso symbol!' as status;
SELECT COUNT(*) as total_jobs, 
       COUNT(CASE WHEN salary_range LIKE '₱%' THEN 1 END) as jobs_with_peso
FROM jobs WHERE approval_status = 'approved';
