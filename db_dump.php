<?php
require_once __DIR__ . '/database.php';
$tables = $database->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "TABLE: $table\n";
    $columns = $database->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  " . $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "\n";
}
