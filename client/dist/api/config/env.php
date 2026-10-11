<?php
/**
 * Minimal .env loader for PHP without external dependencies.
 * Loads values into getenv(), $_ENV, and $_SERVER.
 */
function loadEnv($path = null) {
    if ($path === null) {
        $apiRoot = dirname(__DIR__);
        // The private folder beside public_html survives redeploys (see config/paths.php);
        // the repository root and the API folder are kept for local development.
        $candidates = array_filter([
            getenv('EMPLOYSMART_ENV_FILE') ?: null,
            dirname($apiRoot, 2) . '/employsmart_private/.env',
            dirname($apiRoot) . '/.env',
            $apiRoot . '/.env',
        ]);
        $path = '';
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) { $path = $candidate; break; }
        }
    }
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];
        $value = trim($matches[2]);
        if ($value === 'null') {
            $value = '';
        } elseif (preg_match('/^"(.*)"$/s', $value, $sub)) {
            $value = stripcslashes($sub[1]);
        } elseif (preg_match('/^\'(.*)\'$/s', $value, $sub)) {
            $value = $sub[1];
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

function envBool($key, $default = false) {
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function envList($key, $default = []) {
    $value = env($key);
    if ($value === null || $value === '') {
        return $default;
    }
    return array_filter(array_map('trim', explode(',', $value)));
}

/**
 * One-time move of an uploaded api/.env into the private folder beside public_html,
 * so the next GitHub deployment (which replaces public_html) cannot delete it.
 */
function migrateEnvToPrivateDir() {
    $apiRoot = dirname(__DIR__);
    $deployed = basename($apiRoot) === 'api' && is_file(dirname($apiRoot) . '/index.html');
    $source = $apiRoot . '/.env';
    $privateDir = dirname($apiRoot, 2) . '/employsmart_private';
    if (!$deployed || !is_file($source) || is_file($privateDir . '/.env')) return;
    if (!is_dir($privateDir) && !@mkdir($privateDir, 0750, true)) return;
    if (@copy($source, $privateDir . '/.env')) @chmod($privateDir . '/.env', 0600);
}

migrateEnvToPrivateDir();
loadEnv();
require_once __DIR__ . '/paths.php';
