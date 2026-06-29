<?php
require 'database.php';

$tables = ['estimate_tickets', 'supplier_tickets', 'general_tickets', 'ticket_comments'];

foreach ($tables as $table) {
    try {
        $result = $database->query("SHOW CREATE TABLE $table")->fetchAll();
        if (!empty($result)) {
            echo "--- Table: $table ---\n";
            echo $result[0]['Create Table'] . "\n\n";
        }
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
