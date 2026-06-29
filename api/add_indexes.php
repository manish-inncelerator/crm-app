<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database.php';

try {
    echo "Starting to add indexes...\n";

    // tickets_unified indexes
    $queries = [
        "ALTER TABLE tickets_unified ADD INDEX idx_status (status)",
        "ALTER TABLE tickets_unified ADD INDEX idx_owner_id (owner_id)",
        "ALTER TABLE tickets_unified ADD INDEX idx_priority (priority)",
        "ALTER TABLE tickets_unified ADD INDEX idx_created_at (created_at)",
        "ALTER TABLE ticket_comments ADD INDEX idx_ticket_id (ticket_id)",
        "ALTER TABLE ticket_comments ADD INDEX idx_created_at (created_at)",
        "ALTER TABLE notifications ADD INDEX idx_user_id_read (user_id, is_read)"
    ];

    foreach ($queries as $query) {
        try {
            $database->query($query);
            echo "Successfully executed: $query\n";
        } catch (Exception $e) {
            // Index might already exist, which is fine
            echo "Skipped or failed (might already exist): $query\n";
        }
    }

    echo "Finished adding indexes!\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}
