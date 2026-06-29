<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'database.php';

echo "<pre>";
echo "Starting comprehensive database migration...\n\n";

try {
    // ==========================================
    // PHASE 1: CREATE TICKETS UNIFIED TABLE
    // ==========================================
    echo "--- Phase 1: Unified Tickets Schema ---\n";
    $database->query("
        CREATE TABLE IF NOT EXISTS tickets_unified (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            owner_id INT,
            type VARCHAR(50) NOT NULL,
            subtype VARCHAR(100),
            booking_reference VARCHAR(100),
            priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL DEFAULT 'MEDIUM',
            status ENUM('SUBMITTED', 'OPEN', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED', 'PROCESSED', 'IN_PROGRESS', 'RESOLVED', 'REJECTED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
            expected_timeline DATETIME,
            delay_reason TEXT,
            description TEXT,
            metadata JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✔ Table 'tickets_unified' created or verified.\n";

    $database->query("
        CREATE TABLE IF NOT EXISTS ticket_migration_map (
            old_id INT,
            old_type VARCHAR(50),
            new_id INT,
            PRIMARY KEY (old_type, old_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Table 'ticket_migration_map' created or verified.\n\n";

    // ==========================================
    // PHASE 2: MIGRATE TICKET DATA
    // ==========================================
    echo "--- Phase 2: Data Migration ---\n";
    $database->query("TRUNCATE TABLE ticket_migration_map");
    // $database->query("TRUNCATE TABLE tickets_unified"); // Uncomment if you want to wipe unified table before re-running

    // 1. Estimate Tickets
    $estimateTickets = $database->select('estimate_tickets', '*');
    $countEst = count($estimateTickets);
    foreach ($estimateTickets as $t) {
        $meta = [
            'customer_name' => $t['customer_name'] ?? null,
            'billing_address' => $t['billing_address'] ?? null,
            'email' => $t['email'] ?? null,
            'contact_number' => $t['contact_number'] ?? null,
            'consultant_name' => $t['consultant_name'] ?? null,
            'service_date' => $t['service_date'] ?? null,
            'package_details' => $t['package_details'] ?? null,
            'number_of_persons' => $t['number_of_persons'] ?? null,
            'rate_per_person' => $t['rate_per_person'] ?? null,
            'total_amount' => $t['total_amount'] ?? null,
            'estimate_message' => $t['estimate_message'] ?? null,
            'supplier_id' => $t['supplier_id'] ?? null,
            'estimated_time' => $t['estimated_time'] ?? null
        ];
        
        $database->insert('tickets_unified', [
            'user_id' => $t['user_id'],
            'owner_id' => $t['owner_id'] ?? $t['user_id'],
            'type' => 'estimate',
            'subtype' => 'Estimate Creation',
            'booking_reference' => $t['booking_reference'] ?? '',
            'priority' => $t['priority'],
            'status' => $t['status'],
            'expected_timeline' => $t['expected_timeline'] ?? null,
            'delay_reason' => $t['delay_reason'] ?? null,
            'description' => $t['description'] ?? '',
            'metadata' => json_encode($meta),
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at']
        ]);
        
        $database->insert('ticket_migration_map', [
            'old_id' => $t['id'], 'old_type' => 'estimate', 'new_id' => $database->id()
        ]);
    }
    echo "✔ Migrated $countEst estimate tickets.\n";

    // 2. Supplier Tickets
    $supplierTickets = $database->select('supplier_tickets', '*');
    $countSup = count($supplierTickets);
    foreach ($supplierTickets as $t) {
        $meta = [
            'supplier_id' => $t['supplier_id'] ?? null,
            'travel_date' => $t['travel_date'] ?? null,
            'due_date' => $t['due_date'] ?? null,
            'payment_type' => $t['payment_type'] ?? null,
            'bank_details' => $t['bank_details'] ?? null,
            'complete_costing' => $t['complete_costing'] ?? null,
            'supplier_invoice_currency' => $t['supplier_invoice_currency'] ?? null,
            'supplier_local_currency' => $t['supplier_local_currency'] ?? null,
            'supplier_invoice_path' => $t['supplier_invoice_path'] ?? null,
            'customer_invoice_path' => $t['customer_invoice_path'] ?? null,
            'payment_proof_path' => $t['payment_proof_path'] ?? null,
            'supplier_message' => $t['supplier_message'] ?? null,
            'estimated_time' => $t['estimated_time'] ?? null
        ];
        
        $database->insert('tickets_unified', [
            'user_id' => $t['user_id'],
            'owner_id' => $t['owner_id'] ?? $t['user_id'],
            'type' => 'supplier',
            'subtype' => 'Supplier Payment',
            'booking_reference' => $t['booking_reference'] ?? '',
            'priority' => $t['priority'],
            'status' => $t['status'],
            'expected_timeline' => $t['expected_timeline'] ?? null,
            'delay_reason' => $t['delay_reason'] ?? null,
            'description' => $t['description'] ?? '',
            'metadata' => json_encode($meta),
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at']
        ]);
        
        $database->insert('ticket_migration_map', [
            'old_id' => $t['id'], 'old_type' => 'supplier', 'new_id' => $database->id()
        ]);
    }
    echo "✔ Migrated $countSup supplier tickets.\n";

    // 3. General Tickets
    $generalTickets = $database->select('general_tickets', '*');
    $countGen = count($generalTickets);
    foreach ($generalTickets as $t) {
        $meta = [
            'supporting_image_path' => $t['supporting_image_path'] ?? null,
            'estimated_time' => $t['estimated_time'] ?? null
        ];
        
        $database->insert('tickets_unified', [
            'user_id' => $t['user_id'],
            'owner_id' => $t['owner_id'] ?? $t['user_id'],
            'type' => 'general',
            'subtype' => $t['ticket_subtype'] ?? 'General',
            'booking_reference' => $t['booking_reference'] ?? '',
            'priority' => $t['priority'],
            'status' => $t['status'],
            'expected_timeline' => $t['expected_timeline'] ?? null,
            'delay_reason' => $t['delay_reason'] ?? null,
            'description' => $t['description'] ?? '',
            'metadata' => json_encode($meta),
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at']
        ]);
        
        $database->insert('ticket_migration_map', [
            'old_id' => $t['id'], 'old_type' => 'general', 'new_id' => $database->id()
        ]);
    }
    echo "✔ Migrated $countGen general tickets.\n\n";


    // ==========================================
    // PHASE 3: MASTER ADMIN & FINANCIAL ROLES
    // ==========================================
    echo "--- Phase 3: User Role Updates ---\n";
    $columns = $database->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    if (!in_array('is_master_admin', $columnNames)) {
        $database->query("ALTER TABLE users ADD COLUMN is_master_admin BOOLEAN NOT NULL DEFAULT FALSE");
        echo "✔ Added 'is_master_admin' column.\n";
    } else {
        echo "- 'is_master_admin' column already exists.\n";
    }

    if (!in_array('can_view_financials', $columnNames)) {
        $database->query("ALTER TABLE users ADD COLUMN can_view_financials BOOLEAN NOT NULL DEFAULT FALSE");
        echo "✔ Added 'can_view_financials' column.\n";
    } else {
        echo "- 'can_view_financials' column already exists.\n";
    }

    // Automatically make the first admin a Master Admin so they aren't locked out
    $firstAdmin = $database->get('users', '*', ['is_admin' => 1]);
    if ($firstAdmin) {
        $database->update('users', [
            'is_master_admin' => 1,
            'can_view_financials' => 1
        ], ['id' => $firstAdmin['id']]);
        echo "✔ Set Admin (ID: {$firstAdmin['id']}, Name: {$firstAdmin['name']}) as initial Master Admin.\n";
    }

    echo "\n==========================================\n";
    echo "SUCCESS: ALL MIGRATIONS COMPLETED!\n";
    echo "You may now safely delete this script or leave it as a reference.\n";
    echo "==========================================\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "\n❌ Error during migration: " . $e->getMessage() . "\n";
    echo "</pre>";
}
