<?php
/**
 * User Routes
 * Base: /users
 *
 * GET    /users                 — admin, peso   — List all users (paginated, filterable)
 * GET    /users/stats           — admin, peso   — Platform dashboard statistics
 * GET    /users/logs            — admin         — System audit logs
 * GET    /users/{id}            — auth (own or admin) — Get user profile
 * PUT    /users/{id}            — auth (own or admin) — Update profile fields
 * PATCH  /users/{id}            — auth (own or admin) — Partial update
 * DELETE /users/{id}            — admin         — Delete user
 * PATCH  /users/{id}/verify     — admin, peso   — Mark user as verified
 * PATCH  /users/{id}/password   — own           — Change password
 * POST   /users/{id}/resume     — own           — Upload resume file
 * POST   /users/{id}/avatar     — own           — Upload profile picture
 */

require_once __DIR__ . '/../controllers/UserController.php';

/** @var UserController $ctrl */
$ctrl   = new UserController();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $GLOBALS['route_id']     ?? null;
$action = $GLOBALS['route_action'] ?? null;

// ─── GET /users/stats ─────────────────────────────────────────────────────────
if ($id === 'stats') {
    $ctrl->getDashboardStats();

// ─── GET /users/logs ──────────────────────────────────────────────────────────
} elseif ($id === 'logs') {
    $ctrl->getSystemLogs();

// ─── GET /users ───────────────────────────────────────────────────────────────
} elseif ($id === null && $method === 'GET') {
    $ctrl->getAll();

// ─── Routes with action on a user ID ─────────────────────────────────────────
} elseif ($id !== null && $action === 'verify') {
    $ctrl->verifyUser($id);

} elseif ($id !== null && $action === 'password') {
    $ctrl->updatePassword($id);

} elseif ($id !== null && $action === 'resume') {
    $ctrl->uploadResume($id);

} elseif ($id !== null && $action === 'avatar') {
    $ctrl->uploadProfilePicture($id);

// ─── Routes with a numeric user ID, no action ────────────────────────────────
} elseif ($id !== null && $action === null) {
    if ($method === 'GET') {
        $ctrl->getOne($id);
    } elseif ($method === 'PUT' || $method === 'PATCH') {
        $ctrl->update($id);
    } elseif ($method === 'DELETE') {
        $ctrl->deleteUser($id);
    } else {
        sendError('Method not allowed', 405);
    }

} else {
    sendError('User route not found', 404);
}