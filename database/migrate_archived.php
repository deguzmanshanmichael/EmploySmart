<?php
// Migration script to add archived columns
require_once __DIR__ . '/../server/config/database.php';

$db = getDB();

try {
    // Add archived column to users table
    $db->query("ALTER TABLE users ADD COLUMN archived BOOLEAN DEFAULT FALSE");
    echo "Added archived column to users table\n";

    // Add archived column to employers table
    $db->query("ALTER TABLE employers ADD COLUMN archived BOOLEAN DEFAULT FALSE");
    echo "Added archived column to employers table\n";

    // Add archived column to jobs table
    $db->query("ALTER TABLE jobs ADD COLUMN archived BOOLEAN DEFAULT FALSE");
    echo "Added archived column to jobs table\n";

    // Add archived column to training_programs table
    $db->query("ALTER TABLE training_programs ADD COLUMN archived BOOLEAN DEFAULT FALSE");
    echo "Added archived column to training_programs table\n";

    // Add archived column to skills table
    $db->query("ALTER TABLE skills ADD COLUMN archived BOOLEAN DEFAULT FALSE");
    echo "Added archived column to skills table\n";

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>