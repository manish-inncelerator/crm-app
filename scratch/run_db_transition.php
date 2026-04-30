<?php
require 'database.php';

$sql = file_get_contents('database/transition_to_accounting.sql');
$queries = explode(';', $sql);

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        try {
            $database->query($query);
            echo "Executed: " . substr($query, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "Error executing query: " . $e->getMessage() . "\n";
        }
    }
}
echo "Database transition complete.\n";
