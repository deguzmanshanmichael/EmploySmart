<?php
/**
 * INTENTIONALLY VULNERABLE LOGIN SCRIPT
 * For educational purposes only - DO NOT USE IN PRODUCTION
 * This demonstrates SQL Injection vulnerability
 */

// Simulated database connection (in real scenario, would be actual DB)
$db_host = '192.168.199.250';
$db_name = 'joblink_demo';
$db_user = 'root';
$db_pass = 'password123';

// VULNERABILITY: Database credentials hardcoded (should use environment variables)
// In real app: use $_ENV or $_SERVER to load from .env file

// Simulate database with vulnerable function
function simulateVulnerableLogin($email, $password) {
    // VULNERABILITY 1: SQL INJECTION
    // User input is directly concatenated into SQL query
    // No prepared statements used
    $query = "SELECT id, email, role FROM users WHERE email = '" . $email . "' AND password = '" . $password . "'";
    
    // This would be: SELECT * FROM users WHERE email = '' OR '1'='1' AND password = 'anything'
    // The '1'='1' always evaluates to true, bypassing authentication
    
    // Simulated vulnerable database lookup
    $users = [
        ['id' => 1, 'email' => 'admin@joblink.com', 'password' => 'admin123', 'role' => 'admin'],
        ['id' => 2, 'email' => 'employer@joblink.com', 'password' => 'password123', 'role' => 'employer'],
        ['id' => 3, 'email' => 'john@example.com', 'password' => 'pass123', 'role' => 'jobseeker'],
    ];
    
    // VULNERABILITY: Check if email OR password injection bypasses authentication
    if (strpos($email, "' OR '1'='1") !== false || strpos($password, "' OR '1'='1") !== false) {
        // SQL injection detected - in vulnerable app, this would succeed
        return $users[0]; // Return admin user
    }
    
    // Normal query (would be vulnerable in real scenario)
    foreach ($users as $user) {
        if ($user['email'] == $email && $user['password'] == $password) {
            return $user;
        }
    }
    
    return null;
}

// VULNERABILITY 2: NO RATE LIMITING
// Login can be attempted unlimited times
// No CAPTCHA or account lockout mechanism

// Process login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = $_POST['remember'] ?? '';
    
    // VULNERABILITY 3: NO INPUT VALIDATION
    // User input not validated before use
    
    // VULNERABILITY 4: AUTHENTICATION FAILURE
    // Weak password checking (string comparison, not bcrypt)
    $user = simulateVulnerableLogin($email, $password);
    
    if ($user) {
        // VULNERABILITY 5: SESSION MANAGEMENT ISSUES
        // No session token generation or validation
        
        // VULNERABILITY 6: REMEMBER ME - INSECURE
        // Sets long-term cookie without proper validation
        if ($remember) {
            setcookie('user_id', $user['id'], time() + (30 * 24 * 60 * 60)); // 30 days
            setcookie('user_email', $user['email'], time() + (30 * 24 * 60 * 60)); // Insecure!
        }
        
        // VULNERABILITY 7: NO SECURE COOKIE FLAGS
        // Cookies not marked as HttpOnly, Secure, or SameSite
        
        // VULNERABILITY 8: SESSION NOT REGENERATED
        // Susceptible to session fixation attacks
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        // Should regenerate session ID here!
        // session_regenerate_id();
        
        // VULNERABILITY 9: REDIRECT WITHOUT VERIFICATION
        // No verification that redirect is to safe URL
        header('Location: ../frontend/dashboard.html');
        exit;
    } else {
        // VULNERABILITY 10: ERROR MESSAGE REVEALS INFORMATION
        // "Invalid email or password" - but should not reveal which field is wrong
        // Attacker can enumerate valid emails
        $error = 'Invalid credentials. Email or password is incorrect.';
    }
}

// If coming from GET (also vulnerable)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = $_GET['email'] ?? '';
    // VULNERABILITY: Using GET for sensitive data (should be POST)
    // Credentials could be logged in server logs, browser history, etc.
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Response</title>
    <style>
        body { font-family: Arial; margin: 50px; }
        .error { background: #ffcccc; padding: 20px; border-radius: 5px; color: #cc0000; }
        .success { background: #ccffcc; padding: 20px; border-radius: 5px; color: #00cc00; }
        .info { background: #ccccff; padding: 20px; border-radius: 5px; color: #0000cc; margin-top: 20px; }
    </style>
</head>
<body>
    <?php if (isset($error)): ?>
        <div class="error">
            <h3>❌ Login Failed</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <a href="../frontend/login.html">← Back to Login</a>
        </div>
    <?php endif; ?>
    
    <div class="info">
        <h3>🔍 Vulnerability Information</h3>
        <p><strong>File:</strong> backend/login.php</p>
        <p><strong>Vulnerabilities in this script:</strong></p>
        <ul>
            <li>SQL Injection - User input concatenated directly into SQL</li>
            <li>No Rate Limiting - Brute force attacks possible</li>
            <li>Weak Authentication - Plain text password comparison</li>
            <li>Information Disclosure - Error messages reveal too much</li>
            <li>Insecure Session - No session token regeneration</li>
            <li>Insecure Remember Me - No secure cookie flags</li>
            <li>No Input Validation - User input not sanitized</li>
        </ul>
    </div>
</body>
</html>
