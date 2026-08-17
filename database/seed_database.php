<?php
// Database seeding script for EmploySmart
require_once __DIR__ . '/../server/config/database.php';

$db = getDB();

try {
    echo "Starting database seeding...\n";

    // Read and execute the seed data SQL file
    $sql = file_get_contents(__DIR__ . '/seed_data.sql');

    // Execute the entire SQL file
    echo "Executing seed data SQL file...\n";
    if ($db->multi_query($sql)) {
        do {
            // Consume all result sets
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());

        echo "Database seeding completed successfully!\n";
        echo "Demo accounts preserved and new data added.\n";
    } else {
        echo "ERROR: " . $db->error . "\n";
    }

} catch (Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
?>