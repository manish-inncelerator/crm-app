<?php
require_once __DIR__ . '/vendor/autoload.php';

use Medoo\Medoo;

// Determine environment based on URL or CLI
$is_localhost = false;
if (php_sapi_name() === 'cli') {
    // Default to localhost for CLI operations unless specified otherwise
    $is_localhost = true;
} else if (isset($_SERVER['HTTP_HOST'])) {
    $host = strtolower($_SERVER['HTTP_HOST']);
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '.local') !== false) {
        $is_localhost = true;
    }
}

if ($is_localhost) {
    // Localhost Configuration
    $database = new Medoo([
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'crm',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'port' => 3304,
        'prefix' => '',
        'logging' => true,
        'error' => PDO::ERRMODE_EXCEPTION,
        'command' => [
            'SET SQL_MODE=ANSI_QUOTES'
        ]
    ]);
} else {
    // Production Configuration
    $database = new Medoo([
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'crm',
        'username' => 'root',
        'password' => 'Inncelerator@2025@#',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'port' => 3306,
        'prefix' => '',
        'logging' => true,
        'error' => PDO::ERRMODE_EXCEPTION,
        'command' => [
            'SET SQL_MODE=ANSI_QUOTES'
        ]
    ]);
}