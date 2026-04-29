<?php
require_once '../database.php';

header('Content-Type: application/json');

try {
    // Check if column exists
    $columns = $database->query("SHOW COLUMNS FROM users LIKE 'is_ex_employee'")->fetchAll();
    
    if (empty($columns)) {
        $database->query("ALTER TABLE users ADD COLUMN is_ex_employee TINYINT(1) DEFAULT 0");
        echo json_encode(['success' => true, 'message' => 'Column is_ex_employee added successfully']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Column is_ex_employee already exists']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
