<?php
/**
 * Skill Routes
 * Base: /skills
 *
 * GET    /skills                  — public        — List all skills
 * POST   /skills                  — admin,peso,clcdo — Create a new skill
 * DELETE /skills/{id}             — admin         — Delete a skill
 * GET    /skills/user/{userId}    — auth          — Get skills for a specific user
 * PUT    /skills/user/{userId}    — own, admin    — Replace all skills for a user
 * POST   /skills/user/{userId}    — own, admin    — Same (alias for PUT)
 */

require_once __DIR__ . '/../controllers/SkillController.php';

/** @var SkillController $ctrl */
$ctrl   = new SkillController();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $GLOBALS['route_id']     ?? null;
$action = $GLOBALS['route_action'] ?? null;

// ─── GET /skills  |  POST /skills ─────────────────────────────────────────────
if ($id === null) {
    if ($method === 'GET') {
        $ctrl->getAll();
    } elseif ($method === 'POST') {
        $ctrl->create();
    } else {
        sendError('Method not allowed', 405);
    }

// ─── GET /skills/user/{userId}  |  PUT|POST /skills/user/{userId} ─────────────
} elseif ($id === 'user' && $action !== null) {
    if ($method === 'GET') {
        $ctrl->getUserSkills($action);
    } elseif ($method === 'PUT' || $method === 'POST') {
        $ctrl->updateUserSkills($action);
    } else {
        sendError('Method not allowed', 405);
    }

// ─── DELETE /skills/{id} ──────────────────────────────────────────────────────
} elseif ($id !== null && $action === null && $method === 'DELETE') {
    $ctrl->delete($id);

} else {
    sendError('Skill route not found', 404);
}