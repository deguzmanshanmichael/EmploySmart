<?php
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

/**
 * Enhanced Authentication and Authorization Middleware
 * Provides secure token verification, CSRF protection, and role-based access control
 */

function requireAuth() {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    if (!is_array($headers)) {
        $headers = [];
    }
    $authHeader = '';
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $authHeader = trim((string)$value);
            break;
        }
    }
    if ($authHeader === '') {
        $authHeader = trim((string)(
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        ));
    }
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        sendError('Unauthorized. Token required.', 401);
    }
    $token = $matches[1];
    
    // Validate token format (prevent oversized tokens)
    if (strlen($token) > 2048) {
        sendError('Invalid token format', 401);
    }
    
    $payload = verifyJWT($token);
    if (!$payload) {
        sendError('Token expired or invalid.', 401);
    }

    // CSRF check for mutation requests
    $method = $_SERVER['REQUEST_METHOD'];
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        $csrfHeader = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? $headers['x-csrf-token'] ?? '';
        if (empty($csrfHeader)) {
            $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        $requestedWith = $headers['X-Requested-With'] ?? $headers['x-requested-with'] ?? '';
        $origin = $headers['Origin'] ?? $headers['origin'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocalHost = $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || preg_match('/^localhost(:\d+)?$/i', $host);
        $isSameOrigin = empty($origin) || $origin === 'http://' . $host || $origin === 'https://' . $host;
        $skipCsrf = !empty($requestedWith) || $isLocalHost || $isSameOrigin;

        if (!$skipCsrf && empty($csrfHeader)) {
            sendError('CSRF token required for this operation.', 403);
        }
        if (!$skipCsrf && !empty($csrfHeader) && strlen($csrfHeader) < 32) {
            sendError('Invalid CSRF token format', 403);
        }
    }

    return $payload;
}

function requireRole($allowedRoles) {
    $payload = requireAuth();
    $role = strtolower(trim((string)($payload['role'] ?? '')));
    $allowedRoles = array_map(static fn($allowedRole) => strtolower(trim((string)$allowedRole)), (array)$allowedRoles);
    if (!in_array($role, $allowedRoles, true)) {
        sendError('Forbidden. Insufficient permissions.', 403);
    }
    $payload['role'] = $role;
    return $payload;
}

/**
 * Verify user owns the resource or is admin
 * @param int $userId - The user ID from the resource
 * @param array $payload - JWT payload from requireAuth()
 * @param array $allowedRoles - Additional roles that can bypass ownership check
 */
function verifyOwnership($userId, $payload, $allowedRoles = ['admin', 'peso']) {
    if ($payload['sub'] != $userId && !in_array($payload['role'], (array)$allowedRoles, true)) {
        sendError('Forbidden. Access denied.', 403);
    }
    return true;
}

/**
 * Verify resource ownership by checking database
 * @param string $table - Database table name
 * @param int $resourceId - Resource ID to check
 * @param int $userId - User ID to verify ownership
 * @param string $userColumn - Column name for user_id (default: 'user_id')
 */
function verifyResourceOwnership($table, $resourceId, $userId, $userColumn = 'user_id') {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM $table WHERE id = ? AND $userColumn = ?");
    if (!$stmt) {
        sendError('Database error', 500);
    }
    $stmt->bind_param('ii', $resourceId, $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        sendError('Forbidden. Resource not found or access denied.', 403);
    }
    return true;
}

/**
 * Get current user payload safely
 */
function getCurrentPayload() {
    return requireAuth();
}

/**
 * Check if user has at least one of the specified roles
 */
function hasRole($roles, $payload = null) {
    if ($payload === null) {
        $payload = getCurrentPayload();
    }
    return in_array($payload['role'], (array)$roles, true);
}