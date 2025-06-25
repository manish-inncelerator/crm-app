<?php
require_once 'database.php';

try {
    $columns = $database->query("SHOW COLUMNS FROM users")->fetchAll();
    echo "Users table structure:\n";
    print_r($columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
