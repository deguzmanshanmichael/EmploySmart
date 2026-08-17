<?php
/**
 * Matching Routes
 * Base: /matches
 *
 * GET  /matches/recommended        — jobseeker     — Get top matched jobs for current user
 * GET  /matches/{userId}/{jobId}   — auth          — Get match score for a user-job pair
 * POST /matches/{userId}           — admin, peso   — Recompute and store all matches for a user
 */

require_once __DIR__ . '/../controllers/MatchController.php';

/** @var MatchController $ctrl */
$ctrl   = new MatchController();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $GLOBALS['route_id']     ?? null;   // "recommended" or userId
$action = $GLOBALS['route_action'] ?? null;   // jobId (for score lookup)

// ─── GET /matches/recommended ─────────────────────────────────────────────────
if ($id === 'recommended' && $method === 'GET') {
    $ctrl->getRecommendedJobs();
}

// ─── GET /matches/{userId}/{jobId} ────────────────────────────────────────────
elseif ($id !== null && $action !== null && $method === 'GET') {
    $ctrl->getMatchScore($id, $action);
}

// ─── POST /matches/{userId} ───────────────────────────────────────────────────
elseif ($id !== null && $action === null && $method === 'POST') {
    $ctrl->runMatchForUser($id);
}

else {
    sendError('Match route not found', 404);
}