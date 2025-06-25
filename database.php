<?php
require 'vendor/autoload.php';

use Medoo\Medoo;

// Database configuration
$database = new Medoo([
    'type' => 'mysql',
    'host' => 'cmcbs4ya7000ipcahiaqe1578',
    'database' => 'cmcbs4ya00006ahpc21gr6anf',
    'username' => 'cmcbs4y9w0004ahpc0iirh8kw',
    'password' => 'hPYekEStT51f57KoXAgErJMD',
    'port' => 3306,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'logging' => true,
    'error' => PDO::ERRMODE_EXCEPTION,
    'command' => [
        'SET SQL_MODE=ANSI_QUOTES'
    ]
]);
