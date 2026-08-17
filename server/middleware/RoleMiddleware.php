<?php
/**
 * RoleMiddleware.php
 * Standalone role-checking middleware.
 * Used as a layer on top of AuthMiddleware when role enforcement is needed.
 */
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/AuthMiddleware.php';

class RoleMiddleware {

    /**
     * Verify the request has a valid JWT AND the user holds one of $allowedRoles.
     * Returns the decoded JWT payload on success.
     *
     * @param string|array $allowedRoles
     * @return array decoded JWT payload
     */
    public static function check($allowedRoles) {
        return requireRole((array) $allowedRoles);
    }

    /**
     * Verify JWT and that the authenticated user's ID matches $resourceOwnerId,
     * OR that the user has an elevated role in $bypassRoles.
     *
     * @param int          $resourceOwnerId  e.g. the user_id of a profile/resource
     * @param string|array $bypassRoles      roles that can bypass ownership check
     * @return array decoded JWT payload
     */
    public static function checkOwnerOrRole($resourceOwnerId, $bypassRoles = ['admin']) {
        $payload = requireAuth();
        if (
            (int)$payload['sub'] !== (int)$resourceOwnerId &&
            !in_array($payload['role'], (array)$bypassRoles)
        ) {
            sendError('Forbidden. You do not own this resource.', 403);
        }
        return $payload;
    }

    /**
     * Lightweight check — returns true/false without aborting the request.
     *
     * @param array  $payload      decoded JWT payload
     * @param array  $allowedRoles
     * @return bool
     */
    public static function hasRole(array $payload, array $allowedRoles): bool {
        return in_array($payload['role'] ?? '', $allowedRoles);
    }
}