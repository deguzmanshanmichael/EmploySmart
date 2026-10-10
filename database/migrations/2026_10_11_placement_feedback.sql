-- EmploySmart: PESO ↔ employer placement feedback
-- Applied automatically by the API on first request (server/config/database.php).
-- To apply manually in phpMyAdmin, run each statement once; skip an ALTER whose
-- column already exists (phpMyAdmin will report "Duplicate column name").

CREATE TABLE IF NOT EXISTS feedback_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    sender_id INT NULL,
    sender_role VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feedback_replies_thread (thread_id),
    FOREIGN KEY (thread_id) REFERENCES feedback_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE feedback_threads ADD COLUMN outcome VARCHAR(40) NULL AFTER rating;
ALTER TABLE feedback_threads ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL;
