<?php
require_once '../database.php';

try {
    $database->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_ex_employee TINYINT(1) DEFAULT 0");
    echo json_encode(['success' => true, 'message' => 'Column is_ex_employee added successfully or already exists']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
