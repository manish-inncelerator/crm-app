<?php
require_once __DIR__ . '/database.php';
$pdo = $database->pdo;
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES:\n" . implode(", ", $tables) . "\n";
