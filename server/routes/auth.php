<?php
/**
 * Auth Routes
 * Base: /auth
 *
 * POST /auth/register   — Public   — Register jobseeker or employer
 * POST /auth/login      — Public   — Login and receive JWT pair
 * POST /auth/refresh    — Public   — Refresh access token (rotate refresh token)
 * POST /auth/logout     — Public   — Revoke refresh token
 * GET  /auth/me         — Auth     — Get current authenticated user
 */

require_once __DIR__ . '/../controllers/AuthController.php';

/** @var AuthController $ctrl */
$ctrl    = new AuthController();
$method  = $_SERVER['REQUEST_METHOD'];
$routeId = $GLOBALS['route_id'] ?? null;

if ($routeId === 'register') {
    $ctrl->register();

} elseif ($routeId === 'login') {
    $ctrl->login();

} elseif ($routeId === 'refresh') {
    $ctrl->refresh();

} elseif ($routeId === 'logout') {
    $ctrl->logout();

} elseif ($routeId === 'me') {
    $ctrl->me();

} else {
    sendError('Auth route not found', 404);
}