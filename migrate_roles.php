<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'database.php';

try {
    // Check if columns exist before adding
    $columns = $database->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    if (!in_array('is_master_admin', $columnNames)) {
        $database->query("ALTER TABLE users ADD COLUMN is_master_admin BOOLEAN NOT NULL DEFAULT FALSE");
        echo "Added 'is_master_admin' column.\n";
    } else {
        echo "'is_master_admin' already exists.\n";
    }

    if (!in_array('can_view_financials', $columnNames)) {
        $database->query("ALTER TABLE users ADD COLUMN can_view_financials BOOLEAN NOT NULL DEFAULT FALSE");
        echo "Added 'can_view_financials' column.\n";
    } else {
        echo "'can_view_financials' already exists.\n";
    }

    // Automatically make the first admin a Master Admin so they aren't locked out
    $firstAdmin = $database->get('users', '*', ['is_admin' => 1]);
    if ($firstAdmin) {
        $database->update('users', [
            'is_master_admin' => 1,
            'can_view_financials' => 1
        ], ['id' => $firstAdmin['id']]);
        echo "Set user ID {$firstAdmin['id']} as Master Admin for initial access.\n";
    }

    echo "Role migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error during role migration: " . $e->getMessage() . "\n";
}
