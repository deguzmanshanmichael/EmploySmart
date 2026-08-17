<?php
/**
 * INTENTIONALLY VULNERABLE SEARCH SCRIPT
 * For educational purposes only - DO NOT USE IN PRODUCTION
 * This demonstrates SQL Injection in search functionality
 */

// VULNERABILITY: Debug information exposed
error_reporting(E_ALL);
ini_set('display_errors', 1);

// VULNERABILITY: Database credentials hardcoded
$db_host = '192.168.199.250';
$db_name = 'joblink_demo';
$db_user = 'root';
$db_pass = 'password123';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = $_GET['search'] ?? '';
    
    // VULNERABILITY 1: SQL INJECTION in search
    // Search query is directly concatenated into SQL
    // No prepared statements or parameterized queries
    
    // This would be the vulnerable query:
    $vulnerable_query = "SELECT id, title, company, salary FROM jobs WHERE title LIKE '%" . $search . "%' OR description LIKE '%" . $search . "%'";
    
    // VULNERABILITY 2: NO INPUT VALIDATION
    // Search string not sanitized before use in query
    
    // Example of SQL injection attack:
    // Search: %' OR '1'='1
    // Resulting query: SELECT * FROM jobs WHERE title LIKE '%' OR '1'='1%'
    // This would return ALL jobs because '1'='1' is always true
    
    // Another example:
    // Search: %'; DROP TABLE jobs; --
    // This could DELETE the entire jobs table!
    
    // Simulated vulnerable results
    $jobs = [
        ['id' => 1, 'title' => 'Senior Software Engineer', 'company' => 'Tech Corp', 'salary' => '$150k-$200k'],
        ['id' => 2, 'title' => 'UX/UI Designer', 'company' => 'Creative Studios', 'salary' => '$120k-$150k'],
        ['id' => 3, 'title' => 'Data Scientist', 'company' => 'Analytics Pro', 'salary' => '$130k-$170k'],
    ];
    
    // VULNERABILITY: Check for SQL injection patterns
    if (strpos($search, "' OR '") !== false || strpos($search, "DROP") !== false || strpos($search, "DELETE") !== false) {
        // This is a SQL injection attack
        // In vulnerable app, this would execute the malicious query
        $results = [];
        $vulnerable = true;
    } else {
        // Normal search (would still be vulnerable if not using prepared statements)
        $results = array_filter($jobs, function($job) use ($search) {
            return stripos($job['title'], $search) !== false || stripos($job['company'], $search) !== false;
        });
        $vulnerable = false;
    }
    
    echo json_encode([
        'query' => $vulnerable_query,
        'search_term' => $search,
        'vulnerable_to_injection' => $vulnerable,
        'results' => $results,
        'message' => $vulnerable ? 'SQL Injection Detected!' : 'Search completed'
    ]);
}

// VULNERABILITY 3: NO RATE LIMITING
// Search can be performed unlimited times
// Could be used for brute-force or DoS attacks

// VULNERABILITY 4: CORS MISCONFIGURATION
// If cross-origin requests are allowed without restrictions
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// VULNERABILITY 5: DEBUG INFORMATION EXPOSED
// Error messages and stack traces visible to users
// File paths and system information could be revealed

// VULNERABILITY 6: NO AUTHENTICATION CHECK
// Anyone can search without being logged in
// But for a job search feature, this might be intentional
// However, there's no verification of user permissions
?>
