<?php
require 'vendor/autoload.php';

use Medoo\Medoo;

// Database configuration
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
