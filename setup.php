<?php
require 'database.php';

// Function to create users table if it doesn't exist
function createUsersTable($database)
{
    try {
        // Check if table exists
        $tableExists = $database->query("SHOW TABLES LIKE 'users'")->fetchAll();

        if (empty($tableExists)) {
            // Create users table
            $database->query("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    auth0_id VARCHAR(255) NOT NULL UNIQUE,
                    email VARCHAR(255) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    picture VARCHAR(512),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (auth0_id),
                    INDEX (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            echo "Users table created successfully!\n";
        } else {
            echo "Users table already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error creating users table: " . $e->getMessage() . "\n";
    }
}

// Create tables
createUsersTable($database);

echo "Database setup completed!\n";
