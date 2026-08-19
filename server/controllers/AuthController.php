<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';

class AuthController {

    public function register() {
        $data = getJsonBody();
        $errors = validateRequired($data, ['first_name','last_name','email','password','sex','role']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        
        if (!validateEmail($data['email'])) sendError('Invalid email address', 422);
        
        // Validate password strength - require minimum 8 chars with uppercase, lowercase, number, special char
        if (!validatePassword($data['password'])) {
            sendError('Password must be at least 8 characters with uppercase, lowercase, number, and special character', 422);
        }

        if (!throttleRequest('register:' . md5($data['email'] . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')), 5, 900)) {
            sendError('Too many registration attempts. Please try again later.', 429);
        }

        $allowedRoles = ['jobseeker', 'employer', 'peso', 'clcdo', 'admin'];
        if (!validateEnum($data['role'], $allowedRoles)) sendError('Invalid role', 422);

        // Validate name fields
        if (!validateStringLength($data['first_name'], 2, 50)) sendError('First name must be 2-50 characters', 422);
        if (!validateStringLength($data['last_name'], 2, 50)) sendError('Last name must be 2-50 characters', 422);
        
        // Validate sex field
        $normalizedSex = strtolower(trim((string)($data['sex'] ?? '')));
        if (!in_array($normalizedSex, ['male', 'female', 'other', 'm', 'f', 'o'], true)) {
            sendError('Invalid sex value', 422);
        }
        if ($normalizedSex === 'm') { $normalizedSex = 'male'; }
        elseif ($normalizedSex === 'f') { $normalizedSex = 'female'; }
        elseif ($normalizedSex === 'o') { $normalizedSex = 'other'; }

        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) sendError('Email already registered', 409);

        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
        
        // Prepare variables for bind_param (must be variables, not expressions)
        $firstName = $data['first_name'];
        $middleName = $data['middle_name'] ?? null;
        $lastName = $data['last_name'];
        $suffix = $data['suffix'] ?? null;
        $sex = $normalizedSex;
        $birthDate = $data['birth_date'] ?? null;
        $email = $data['email'];
        $phone = $data['phone'] ?? null;
        $address = $data['address'] ?? null;
        $city = $data['city'] ?? null;
        $province = $data['province'] ?? null;
        $zipCode = $data['zip_code'] ?? null;
        $educationLevel = $data['education_level'] ?? null;
        $isVerified = $data['is_verified'] ?? false;
        
        $stmt = $db->prepare("INSERT INTO users (first_name,middle_name,last_name,suffix,sex,birth_date,email,password,role,phone,address,city,province,zip_code,education_level,is_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            'sssssssssssssssi',
            $firstName, $middleName, $lastName,
            $suffix, $sex, $birthDate,
            $email, $hashed, $data['role'], $phone,
            $address, $city, $province,
            $zipCode, $educationLevel, $isVerified
        );
        if (!$stmt->execute()) sendError('Registration failed: ' . $stmt->error, 500);
        $userId = $db->insert_id;

        // If employer, create employer record
        if ($data['role'] === 'employer') {
            $companyName = $data['company_name'] ?? '';
            $industry = $data['industry'] ?? '';
            $companyAddress = $data['company_address'] ?? '';
            
            $stmt2 = $db->prepare("INSERT INTO employers (user_id, company_name, industry, company_address, contact_email) VALUES (?,?,?,?,?)");
            $stmt2->bind_param('issss', $userId, $companyName, $industry, $companyAddress, $email);
            if (!$stmt2->execute()) {
                sendError('Failed to create employer record: ' . $stmt2->error, 500);
            }
        }

        $this->logAction($userId, 'REGISTER');
        if ($isVerified) {
            sendSuccess('Staff account created successfully.', null, 201);
        } else {
            sendSuccess('Registration successful. Please wait for account verification.', null, 201);
        }
    }

    public function login() {
        $data = getJsonBody();
        $errors = validateRequired($data, ['email', 'password']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        $data['email'] = strtolower(trim((string)$data['email']));
        if (!validateEmail($data['email'])) sendError('Invalid email address', 422);

        $clientKey = 'login:' . md5(($data['email'] ?? '') . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!throttleRequest($clientKey, 8, 900)) {
            sendError('Too many login attempts. Please wait before retrying.', 429);
        }

        $portal = strtolower(trim((string)($data['portal'] ?? '')));
        if ($portal !== '' && !in_array($portal, ['user', 'staff'], true)) {
            sendError('Invalid login portal', 422);
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, e.id as employer_record_id, e.verification_status as employer_status FROM users u LEFT JOIN employers e ON e.user_id = u.id WHERE u.email = ?");
        $stmt->bind_param('s', $data['email']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $passwordValid = false;
        if ($user && password_verify($data['password'], $user['password'])) {
            $passwordValid = true;
        } elseif ($user && hash_equals($user['password'], $data['password'])) {
            // Legacy seed data may store plain text passwords. Auto-migrate to bcrypt on first successful login.
            $passwordValid = true;
            $newHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $userId = $user['id'];
            $rehashStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($rehashStmt) {
                $rehashStmt->bind_param('si', $newHash, $userId);
                $rehashStmt->execute();
            }
        }

        if (!$passwordValid) {
            $this->logAction($user['id'] ?? null, 'FAILED_LOGIN');
            sendError('Invalid email or password', 401);
        }

        if ($portal === 'user' && in_array($user['role'], ['peso', 'clcdo', 'admin'], true)) {
            sendError('This portal is for users only. Please use the staff login page.', 403);
        }

        if ($portal === 'staff' && in_array($user['role'], ['jobseeker', 'employer'], true)) {
            sendError('This portal is for staff only. Please use the user login page.', 403);
        }

        if (!$user['is_verified'] && $user['role'] === 'jobseeker') {
            sendError('Account not yet verified. Please contact PESO office.', 403);
        }

        $payload = [
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'name'  => $user['first_name'] . ' ' . $user['last_name'],
        ];

        $accessToken  = generateJWT($payload);
        $refreshToken = generateRefreshToken($user['id']);
        $expiresAt    = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRE);
        $csrfToken    = bin2hex(random_bytes(32));

        // Store refresh token - MUST use variables, not array elements for bind_param
        $userId = $user['id'];
        $stmt = $db->prepare("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?,?,?)");
        if (!$stmt) {
            sendError('Database error: ' . $db->error, 500);
        }
        $stmt->bind_param('iss', $userId, $refreshToken, $expiresAt);
        if (!$stmt->execute()) {
            sendError('Failed to store refresh token: ' . $stmt->error, 500);
        }

        // Clean old tokens
        $stmt = $db->prepare("DELETE FROM refresh_tokens WHERE expires_at < NOW()");
        $stmt->execute();

        $this->logAction($user['id'], 'LOGIN');

        sendSuccess('Login successful', [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'csrf_token'    => $csrfToken,
            'expires_in'    => JWT_ACCESS_EXPIRE,
            'user' => [
                'id'              => $user['id'],
                'name'            => $user['first_name'] . ' ' . $user['last_name'],
                'first_name'      => $user['first_name'],
                'last_name'       => $user['last_name'],
                'email'           => $user['email'],
                'role'            => $user['role'],
                'profile_picture' => $user['profile_picture'],
                'is_verified'     => (bool)$user['is_verified'],
                'employer_status' => $user['employer_status'] ?? null,
            ]
        ]);
    }

    public function refresh() {
        $data = getJsonBody();
        if (empty($data['refresh_token'])) sendError('Refresh token required', 400);
        if (!throttleRequest('refresh:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 20, 900)) {
            sendError('Too many refresh attempts. Please retry later.', 429);
        }

        $db = getDB();
        $token = $data['refresh_token'];
        $stmt = $db->prepare("SELECT rt.*, u.id as uid, u.email, u.role, u.first_name, u.last_name, u.profile_picture FROM refresh_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.token = ? AND rt.expires_at > NOW()");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) sendError('Invalid or expired refresh token', 401);

        $payload = [
            'sub'   => $result['uid'],
            'email' => $result['email'],
            'role'  => $result['role'],
            'name'  => $result['first_name'] . ' ' . $result['last_name'],
        ];

        $newAccessToken  = generateJWT($payload);
        $newRefreshToken = generateRefreshToken($result['uid']);
        $expiresAt       = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRE);
        $newCsrfToken    = bin2hex(random_bytes(32));

        // Rotate refresh token
        $stmt = $db->prepare("DELETE FROM refresh_tokens WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();

        // MUST use variables, not array elements for bind_param
        $userId = $result['uid'];
        $stmt = $db->prepare("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?,?,?)");
        if (!$stmt) {
            sendError('Database error: ' . $db->error, 500);
        }
        $stmt->bind_param('iss', $userId, $newRefreshToken, $expiresAt);
        if (!$stmt->execute()) {
            sendError('Failed to store new refresh token: ' . $stmt->error, 500);
        }

        sendSuccess('Token refreshed', [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'csrf_token'    => $newCsrfToken,
            'expires_in'    => JWT_ACCESS_EXPIRE,
            'user' => [
                'id'              => $result['uid'],
                'name'            => $result['first_name'] . ' ' . $result['last_name'],
                'first_name'      => $result['first_name'],
                'last_name'       => $result['last_name'],
                'email'           => $result['email'],
                'role'            => $result['role'],
                'profile_picture' => $result['profile_picture'],
            ]
        ]);
    }

    public function logout() {
        $data = getJsonBody();
        if (!empty($data['refresh_token'])) {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM refresh_tokens WHERE token = ?");
            $stmt->bind_param('s', $data['refresh_token']);
            $stmt->execute();
        }
        sendSuccess('Logged out successfully');
    }

    public function me() {
        $payload = requireAuth();
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, e.id as employer_id, e.company_name, e.verification_status FROM users u LEFT JOIN employers e ON e.user_id = u.id WHERE u.id = ?");
        $stmt->bind_param('i', $payload['sub']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) sendError('User not found', 404);
        unset($user['password']);
        sendSuccess('User data', $user);
    }

    private function logAction($userId, $action) {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!empty($userId) && is_numeric($userId)) {
            $userId = (int) $userId;
        } else {
            $userId = null;
        }

        $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, ip_address, user_agent) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $userId, $action, $ip, $ua);
        $stmt->execute();
    }
}