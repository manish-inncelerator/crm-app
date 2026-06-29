<?php
require_once 'database.php';
try {
    $database->query("ALTER TABLE users ADD COLUMN receive_emails TINYINT(1) DEFAULT 1;");
    echo "Column receive_emails added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
