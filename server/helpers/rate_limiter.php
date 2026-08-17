<?php
/**
 * Database-backed rate limiter.
 * Tracks request attempts per key with time windows.
 * More reliable than file-based on Windows systems.
 */

function throttleRequest($key, $limit = 10, $interval = 900) {
    try {
        $db = getDB();
        if (!$db) return true; // Fail open if DB unavailable
        
        $safeKey = hash('sha256', $key); // Use hash for consistency
        $now = time();
        $windowStart = $now - $interval;
        
        // Clean up old records (older than interval)
        $cleanStmt = $db->prepare("DELETE FROM rate_limits WHERE key_hash = ? AND created_at < FROM_UNIXTIME(?)");
        if ($cleanStmt) {
            $cleanStmt->bind_param('si', $safeKey, $windowStart);
            @$cleanStmt->execute();
        }
        
        // Count recent attempts
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE key_hash = ? AND created_at >= FROM_UNIXTIME(?)");
        if (!$countStmt) return true; // Fail open
        
        $countStmt->bind_param('si', $safeKey, $windowStart);
        $countStmt->execute();
        $result = $countStmt->get_result()->fetch_assoc();
        $attemptCount = (int)($result['count'] ?? 0);
        
        if ($attemptCount >= $limit) {
            return false; // Rate limited
        }
        
        // Record this attempt
        $insertStmt = $db->prepare("INSERT INTO rate_limits (key_hash, created_at) VALUES (?, NOW())");
        if ($insertStmt) {
            $insertStmt->bind_param('s', $safeKey);
            @$insertStmt->execute();
        }
        
        return true; // Allow request
    } catch (Exception $e) {
        error_log('Rate limiter error: ' . $e->getMessage());
        return true; // Fail open - don't block on errors
    }
}
