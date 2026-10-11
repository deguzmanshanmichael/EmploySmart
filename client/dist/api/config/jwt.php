<?php
require_once __DIR__ . '/env.php';

$jwtSecret = env('JWT_SECRET', 'employsmart_super_secret_key_2024_change_in_production');
$jwtAccessExpire = (int)env('JWT_ACCESS_EXPIRE', 900);
$jwtRefreshExpire = (int)env('JWT_REFRESH_EXPIRE', 604800);

define('JWT_SECRET', $jwtSecret);
define('JWT_ACCESS_EXPIRE', $jwtAccessExpire);
define('JWT_REFRESH_EXPIRE', $jwtRefreshExpire);

define('JWT_ALGO', 'HS256');

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function generateJWT($payload) {
    $header = base64UrlEncode(json_encode(['alg' => JWT_ALGO, 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_ACCESS_EXPIRE;
    $encodedPayload = base64UrlEncode(json_encode($payload));
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$encodedPayload", JWT_SECRET, true));
    return "$header.$encodedPayload.$signature";
}

function generateRefreshToken($userId) {
    return base64UrlEncode(random_bytes(64)) . '.' . $userId . '.' . time();
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header, $payload, $signature] = $parts;
    $expectedSig = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expectedSig, $signature)) return false;
    $data = json_decode(base64UrlDecode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return false;
    return $data;
}