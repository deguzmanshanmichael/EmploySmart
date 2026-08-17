<?php
/**
 * Employer Routes
 * Base: /employers
 *
 * GET    /employers                    — admin, peso   — List all employers (filterable by status)
 * GET    /employers/user/{userId}      — auth (own or staff) — Get employer profile by user ID
 * GET    /employers/{id}               — auth          — Get employer by employer ID
 * PUT    /employers/{id}               — employer (own), admin — Update company profile
 * PATCH  /employers/{id}               — employer (own), admin — Partial update
 * PATCH  /employers/{id}/verify        — peso, admin   — Approve or reject employer
 * GET    /employers/{id}/analytics     — employer (own), admin — Job + application stats
 * POST   /employers/{id}/document      — employer (own) — Upload business permit
 * GET    /employers/{id}/jobs          — auth          — Get jobs by employer ID
 */

require_once __DIR__ . '/../controllers/EmployerController.php';
require_once __DIR__ . '/../controllers/JobController.php';

/** @var EmployerController $empCtrl */
$empCtrl = new EmployerController();

/** @var JobController $jobCtrl */
$jobCtrl = new JobController();

$method  = $_SERVER['REQUEST_METHOD'];
$id      = $GLOBALS['route_id']     ?? null;
$action  = $GLOBALS['route_action'] ?? null;

// ─── GET /employers ───────────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $empCtrl->getAll();

// ─── GET /employers/user/{userId} ─────────────────────────────────────────────
} elseif ($id === 'user' && $action !== null) {
    $empCtrl->getByUser($action);

// ─── Routes with a numeric employer ID + action ───────────────────────────────
} elseif ($id !== null && $action === 'verify') {
    $empCtrl->verify($id);

} elseif ($id !== null && $action === 'analytics') {
    $empCtrl->getAnalytics($id);

} elseif ($id !== null && $action === 'document') {
    $empCtrl->uploadDocument($id);

} elseif ($id !== null && $action === 'jobs') {
    $jobCtrl->getByEmployer($id);

// ─── Routes with numeric employer ID, no action ──────────────────────────────
} elseif ($id !== null && $action === null) {
    if ($method === 'GET') {
        $empCtrl->getOne($id);
    } elseif ($method === 'PUT' || $method === 'PATCH') {
        $empCtrl->update($id);
    } else {
        sendError('Method not allowed', 405);
    }

} else {
    sendError('Employer route not found', 404);
}