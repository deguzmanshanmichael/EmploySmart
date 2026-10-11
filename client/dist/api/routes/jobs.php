<?php
/**
 * Job Routes
 * Base: /jobs
 *
 * GET    /jobs                  — public       — List approved jobs (search, filter, paginate)
 * POST   /jobs                  — employer     — Post a new job (requires verified employer)
 * GET    /jobs/pending          — peso, admin  — List jobs awaiting approval
 * GET    /jobs/{id}             — public       — Get full job details + skills
 * PUT    /jobs/{id}             — employer (own), peso, admin — Update job
 * PATCH  /jobs/{id}             — employer (own), peso, admin — Partial update
 * DELETE /jobs/{id}             — employer (own), peso, admin — Delete job
 * PATCH  /jobs/{id}/approve     — peso, admin  — Approve or reject a job
 * GET    /jobs/{id}/applicants  — employer (own), peso, admin — List applicants
 */

require_once __DIR__ . '/../controllers/JobController.php';

/** @var JobController $ctrl */
$ctrl   = new JobController();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $GLOBALS['route_id']     ?? null;
$action = $GLOBALS['route_action'] ?? null;

// ─── GET /jobs/pending ────────────────────────────────────────────────────────
if ($id === 'pending' && $method === 'GET') {
    $ctrl->getPendingJobs();

// ─── GET /jobs  |  POST /jobs ─────────────────────────────────────────────────
} elseif ($id === null) {
    if ($method === 'GET') {
        $ctrl->getAll();
    } elseif ($method === 'POST') {
        $ctrl->create();
    } else {
        sendError('Method not allowed', 405);
    }

// ─── PATCH /jobs/{id}/approve ─────────────────────────────────────────────────
} elseif ($id !== null && $action === 'approve') {
    $ctrl->approve($id);

// ─── GET /jobs/{id}/applicants ────────────────────────────────────────────────
} elseif ($id !== null && $action === 'applicants') {
    $ctrl->getJobApplicants($id);

// ─── Routes with a numeric job ID, no action ─────────────────────────────────
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
    sendError('Job route not found', 404);
}