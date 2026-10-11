<?php
/**
 * Training Routes
 * Base: /training
 *
 * GET    /training                          — auth          — List all programs (status filter, paginated)
 * POST   /training                          — clcdo, admin  — Create a new training program
 * GET    /training/user/{userId}            — auth          — Get training history of a user
 * GET    /training/{id}                     — auth          — Get full program details + participants
 * PUT    /training/{id}                     — clcdo, admin  — Update training program
 * PATCH  /training/{id}                     — clcdo, admin  — Partial update
 * DELETE /training/{id}                     — clcdo, admin  — Cancel training program
 * POST   /training/{id}/enroll              — clcdo,peso,admin — Enroll a participant
 * POST   /training/{id}/complete/{userId}   — clcdo, admin  — Mark completion + issue certificate
 */

require_once __DIR__ . '/../controllers/TrainingController.php';

/** @var TrainingController $ctrl */
$ctrl   = new TrainingController();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $GLOBALS['route_id']     ?? null;
$action = $GLOBALS['route_action'] ?? null;
$extra  = $GLOBALS['route_extra']  ?? null;

// ─── GET /training  |  POST /training ────────────────────────────────────────
if ($id === null) {
    if ($method === 'GET') {
        $ctrl->getAll();
    } elseif ($method === 'POST') {
        $ctrl->create();
    } else {
        sendError('Method not allowed', 405);
    }

// ─── GET /training/user/{userId} ──────────────────────────────────────────────
} elseif ($id === 'user' && $action !== null) {
    $ctrl->getUserTrainings($action);

// ─── POST /training/{id}/enroll ───────────────────────────────────────────────
} elseif ($id !== null && $action === 'enroll') {
    $ctrl->enroll($id);

// ─── POST /training/{id}/complete/{userId} ────────────────────────────────────
} elseif ($id !== null && $action === 'complete' && $extra !== null) {
    $ctrl->completeEnrollment($id, $extra);

// ─── GET|PUT|PATCH|DELETE /training/{id} ─────────────────────────────────────────────
} elseif ($id !== null && $action === null) {
    if ($method === 'GET') {
        $ctrl->getOne($id);
    } elseif ($method === 'PUT' || $method === 'PATCH') {
        $ctrl->update($id);
    } elseif ($method === 'DELETE') {
        $ctrl->delete($id);
    } else {
        sendError('Method not allowed', 405);
    }

} else {
    sendError('Training route not found', 404);
}