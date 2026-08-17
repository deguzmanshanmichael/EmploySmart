<?php
/**
 * Minimal .env loader for PHP without external dependencies.
 * Loads values into getenv(), $_ENV, and $_SERVER.
 */
function loadEnv($path = null) {
    if ($path === null) {
        $root = dirname(__DIR__, 2);
        $path = $root . '/.env';
        if (!file_exists($path)) {
            $path = __DIR__ . '/../.env';
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

loadEnv();
