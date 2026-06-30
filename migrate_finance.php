<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'database.php';

echo "<pre>";
echo "Starting Finance CRM database migration...\n\n";

$tables = $database->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables in DB:\n";
print_r($tables);

try {
    // 1. Add columns to tickets_unified
    $columns = $database->query("SHOW COLUMNS FROM tickets_unified")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    if (!in_array('amount', $columnNames)) {
        $database->query("ALTER TABLE tickets_unified ADD COLUMN amount DECIMAL(10,2) DEFAULT 0.00");
        echo "✔ Added 'amount' column to tickets_unified.\n";
    }
    if (!in_array('currency', $columnNames)) {
        $database->query("ALTER TABLE tickets_unified ADD COLUMN currency VARCHAR(10) DEFAULT 'USD'");
        echo "✔ Added 'currency' column to tickets_unified.\n";
    }
    if (!in_array('sla_due_date', $columnNames)) {
        $database->query("ALTER TABLE tickets_unified ADD COLUMN sla_due_date DATETIME DEFAULT NULL");
        echo "✔ Added 'sla_due_date' column to tickets_unified.\n";
    }

    // 2. Add columns to users
    $userColumns = $database->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $userColumnNames = array_column($userColumns, 'Field');

    if (!in_array('account_balance', $userColumnNames)) {
        $database->query("ALTER TABLE users ADD COLUMN account_balance DECIMAL(10,2) DEFAULT 0.00");
        echo "✔ Added 'account_balance' column to users.\n";
    }

    // 3. Create invoices table
    $database->query("
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
    echo "✔ Table 'invoices' created or verified.\n";

    // 4. Migrate old JSON amounts to new columns
    echo "\nMigrating amounts from JSON metadata to dedicated columns...\n";
    $tickets = $database->select('tickets_unified', ['id', 'metadata']);
    $updated = 0;
    foreach ($tickets as $t) {
        $meta = json_decode($t['metadata'], true);
        if ($meta && isset($meta['total_amount']) && is_numeric($meta['total_amount'])) {
            $database->update('tickets_unified', [
                'amount' => $meta['total_amount']
            ], ['id' => $t['id']]);
            $updated++;
        } elseif ($meta && isset($meta['complete_costing']) && is_numeric($meta['complete_costing'])) {
            $database->update('tickets_unified', [
                'amount' => $meta['complete_costing']
            ], ['id' => $t['id']]);
            $updated++;
        }
    }
    echo "✔ Extracted amounts for $updated tickets.\n";

    echo "\n==========================================\n";
    echo "SUCCESS: FINANCE MIGRATIONS COMPLETED!\n";
    echo "==========================================\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "\n❌ Error during migration: " . $e->getMessage() . "\n";
    echo "</pre>";
}
