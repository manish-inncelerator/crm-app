<?php
require_once '../vendor/autoload.php';
require_once '../database.php';

/**
 * CRM to Financial Monitoring System Migration Script
 * This script updates the database schema to support the new accounting-centric features.
 */

try {
    echo "Starting migration...\n";

    // 1. Create Suppliers table if it doesn't exist
    echo "Creating suppliers table...\n";
    $database->query("
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // 2. Insert initial suppliers if table is empty
    $count = $database->count('suppliers');
    if ($count == 0) {
        echo "Seeding suppliers...\n";
        $database->insert('suppliers', [
            ['name' => 'Supplier A'],
            ['name' => 'Supplier B'],
            ['name' => 'Generic Supplier']
        ]);
    }

    // 3. Define the ticket tables to update
    $ticketTables = ['estimate_tickets', 'supplier_tickets', 'general_tickets'];

    foreach ($ticketTables as $table) {
        echo "Updating table: $table...\n";

        // Add booking_reference
        $database->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS booking_reference VARCHAR(50) AFTER id");
        
        // Add owner_id
        $database->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS owner_id INT AFTER user_id");
        
        // Add expected_timeline
        $database->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS expected_timeline DATETIME AFTER status");
        
        // Add delay_reason
        $database->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS delay_reason TEXT AFTER expected_timeline");
        
        // Add supplier_id (if not exists)
        if ($table !== 'supplier_tickets') { // supplier_tickets might already have it or handled differently
             $database->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS supplier_id INT AFTER booking_reference");
        } else {
             // Ensure supplier_id exists in supplier_tickets (it might be there but let's be sure)
             $database->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS supplier_id INT AFTER user_id");
        }

        // 4. Update status ENUM values
        // Note: MySQL doesn't support 'ADD IF NOT EXISTS' for ENUM values easily. 
        // We'll change the column type to include all new values.
        echo "Updating status ENUM for $table...\n";
        $database->query("
            ALTER TABLE $table MODIFY COLUMN status ENUM(
                'OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED',
                'SUBMITTED', 'PENDING_APPROVAL', 'APPROVED', 
                'PAID', 'OVERDUE', 'REJECTED', 'UNDER_REVIEW'
            ) NOT NULL DEFAULT 'OPEN'
        ");
        
        // Set default owner_id to user_id for existing records
        $database->query("UPDATE $table SET owner_id = user_id WHERE owner_id IS NULL");
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
