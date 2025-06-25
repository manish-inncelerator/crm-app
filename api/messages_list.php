<?php
require_once '../database.php';
header('Content-Type: application/json');

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

$sender_id = isset($_REQUEST['sender_id']) ? intval($_REQUEST['sender_id']) : 0;
$receiver_id = isset($_REQUEST['receiver_id']) ? intval($_REQUEST['receiver_id']) : 0;
$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 10;
$offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;

if ($sender_id <= 0 || $receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    $messages = $database->select('messages', '*', [
        'OR' => [
            'AND #1' => [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id
            ],
            'AND #2' => [
                'sender_id' => $receiver_id,
                'receiver_id' => $sender_id
            ]
        ],
        'ORDER' => ['sent_at' => 'DESC'],
        'LIMIT' => [$offset, $limit]
    ]);
    // Reverse the array to show newest messages at the bottom
    $messages = array_reverse($messages);
    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
