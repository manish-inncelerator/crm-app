<?php
require_once __DIR__ . '/database.php';
$pdo = $database->pdo;
try {
    $pdo->query("ALTER TABLE tickets_unified ADD COLUMN amount DECIMAL(10,2) DEFAULT 0.00");
    echo "Success amount\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->query("ALTER TABLE tickets_unified ADD COLUMN currency VARCHAR(10) DEFAULT 'USD'");
    echo "Success currency\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->query("ALTER TABLE tickets_unified ADD COLUMN sla_due_date DATETIME DEFAULT NULL");
    echo "Success sla_due_date\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->query("ALTER TABLE users ADD COLUMN account_balance DECIMAL(10,2) DEFAULT 0.00");
    echo "Success account_balance\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->query("
        CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) DEFAULT 'USD',
            status ENUM('DRAFT', 'ISSUED', 'PAID', 'VOID') DEFAULT 'DRAFT',
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets_unified(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Success invoices\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

echo "Done\n";
