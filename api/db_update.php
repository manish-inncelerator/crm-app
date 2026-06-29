<?php
require_once '../vendor/autoload.php';
require_once '../database.php';

try {
    // Add receive_emails column to users table
    $database->query("ALTER TABLE users ADD COLUMN receive_emails TINYINT(1) DEFAULT 0");
    echo "Successfully added 'receive_emails' column to users table.<br>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'receive_emails' already exists.<br>";
    } else {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}
?>
