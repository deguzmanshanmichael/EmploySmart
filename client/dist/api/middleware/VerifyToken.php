<?php
/**
 * VerifyToken.php
 * Token verification middleware — decodes and validates a JWT
 * without enforcing any specific role.
 * Used as a lightweight guard for endpoints that need authentication
 * but not role enforcement.
 */
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

class VerifyToken {

    /**
     * Extracts and verifies the Bearer token from the Authorization header.
     * Aborts with 401 on failure; returns the decoded payload on success.
     *
     * @return array decoded JWT payload, e.g. ['sub'=>1,'role'=>'jobseeker',...]
     */
    public static function check(): array {
        $headers   = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            sendError('Unauthorized. Bearer token required.', 401);
        }

        $token   = trim($matches[1]);
        $payload = verifyJWT($token);

        if (!$payload) {
            sendError('Token is invalid or has expired. Please refresh your session.', 401);
        }

        return $payload;
    }

    /**
     * Same as check() but returns null instead of aborting if no token is present.
     * Useful for optional-auth endpoints (e.g. public job listings that show match
     * scores only when logged in).
     *
     * @return array|null
     */
    public static function tryCheck(): ?array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        return verifyJWT(trim($matches[1])) ?: null;
    }
}