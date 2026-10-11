<?php
require_once __DIR__ . '/env.php';

function setCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = envList('CORS_ALLOWED_ORIGINS', ['http://localhost', 'http://127.0.0.1', 'http://localhost:5173']);
    $allowEmptyOrigin = envBool('CORS_ALLOW_EMPTY_ORIGIN', false);  // Disable empty origin in production
    $allowedOrigin = '';

    if ($origin && in_array($origin, $allowedOrigins, true)) {
        $allowedOrigin = $origin;
    } elseif (!$origin && $allowEmptyOrigin) {
        $allowedOrigin = '*';
    } elseif (empty($allowedOrigins)) {
        $allowedOrigin = '*';
    }

    if ($allowedOrigin !== '') {
        header("Access-Control-Allow-Origin: $allowedOrigin");
    }

    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: false');
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; base-uri 'self';");
    header('X-XSS-Protection: 0');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}