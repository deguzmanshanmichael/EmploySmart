<?php
/**
 * Enhanced Validation Functions
 * Provides robust server-side validation for all inputs
 */

function validateRequired($data, $fields) {
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

function validateEmail($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (strlen($email) > 254) {
        return false;
    }
    return true;
}

function validatePassword($password) {
    if (!is_string($password) || strlen($password) < 8) {
        return false;
    }
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/', $password);
    
    return $hasUpper && $hasLower && $hasNumber && $hasSpecial;
}

function validatePasswordBasic($password) {
    return is_string($password) && strlen($password) >= 6;
}

function validatePhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = \DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateEnum($value, $allowedValues) {
    return in_array($value, (array)$allowedValues, true);
}

function validateInteger($value) {
    return is_int($value) || (is_string($value) && is_numeric($value) && strpos($value, '.') === false);
}

function validateNumericRange($value, $min, $max) {
    if (!is_numeric($value)) return false;
    $val = (float)$value;
    return $val >= $min && $val <= $max;
}

function validateStringLength($value, $min = 1, $max = 255) {
    if (!is_string($value)) return false;
    $len = strlen($value);
    return $len >= $min && $len <= $max;
}

function validateArrayLength($value, $max = 100) {
    return is_array($value) && count($value) <= $max;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    if (is_string($data)) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $data;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = sanitizeInput($value);
        }
        return $sanitized;
    }
    return sanitize($data);
}

function getJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return sanitizeInput($data ?? []);
}

function getQueryParam($key, $default = null) {
    return isset($_GET[$key]) ? sanitizeInput($_GET[$key]) : $default;
}

function sanitizeInt($value) {
    return is_numeric($value) ? (int)$value : 0;
}

function getPaginationParams() {
    $page = max(1, sanitizeInt($_GET['page'] ?? 1));
    $limit = min(100, max(1, sanitizeInt($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    return [$page, $limit, $offset];
}