<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'database.php';

try {
    // 1. Create the unified tickets table
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

    echo "Table tickets_unified created or already exists.\n";

    // Create a mapping table for old_id -> new_id to update comments/notifications
    $database->query("
        CREATE TABLE IF NOT EXISTS ticket_migration_map (
            old_id INT,
            old_type VARCHAR(50),
            new_id INT,
            PRIMARY KEY (old_type, old_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $database->query("TRUNCATE TABLE ticket_migration_map");
    // Clear unified table in case of re-run
    // $database->query("TRUNCATE TABLE tickets_unified");
    // ONLY truncate if it's empty or we're in testing. Let's not truncate by default.

    $estimateTickets = $database->select('estimate_tickets', '*');
    echo "Migrating " . count($estimateTickets) . " estimate tickets...\n";
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
        
        $newId = $database->id();
        $database->insert('ticket_migration_map', [
            'old_id' => $t['id'],
            'old_type' => 'estimate',
            'new_id' => $newId
        ]);
    }

    $supplierTickets = $database->select('supplier_tickets', '*');
    echo "Migrating " . count($supplierTickets) . " supplier tickets...\n";
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
        
        $newId = $database->id();
        $database->insert('ticket_migration_map', [
            'old_id' => $t['id'],
            'old_type' => 'supplier',
            'new_id' => $newId
        ]);
    }

    $generalTickets = $database->select('general_tickets', '*');
    echo "Migrating " . count($generalTickets) . " general tickets...\n";
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
        
        $newId = $database->id();
        $database->insert('ticket_migration_map', [
            'old_id' => $t['id'],
            'old_type' => 'general',
            'new_id' => $newId
        ]);
    }

    echo "Migration to tickets_unified completed.\n";

} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
