<?php
require_once '../database.php';
header('Content-Type: application/json');

$sender_id = isset($_POST['sender_id']) ? intval($_POST['sender_id']) : 0;
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;

if ($sender_id <= 0 || $receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    $database->update('messages', [
        'status' => 'read'
    ], [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'status' => 'sent'
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
