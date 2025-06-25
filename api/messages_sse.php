<?php
require_once '../database.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

ignore_user_abort(true);
set_time_limit(0);

$sender_id = isset($_GET['sender_id']) ? intval($_GET['sender_id']) : 0;
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;

if ($sender_id <= 0 || $receiver_id <= 0) {
    echo ": Invalid parameters\n\n";
    flush();
    exit;
}

function is_online($last_activity)
{
    return $last_activity && (strtotime($last_activity) > (time() - 120));
}

$last_message_id = 0;
$start = time();
$timeout = 60; // seconds

while (connection_aborted() === 0 && (time() - $start) < $timeout) {
    // Get latest message
    $msg = $database->get('messages', '*', [
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
        'ORDER' => ['id' => 'DESC']
    ]);

    $new_message = null;
    if ($msg && $msg['id'] > $last_message_id) {
        $new_message = $msg;
        $last_message_id = $msg['id'];
    }

    // Get online status for both users
    $sender_last = $database->get('users', 'last_activity', ['id' => $sender_id]);
    $receiver_last = $database->get('users', 'last_activity', ['id' => $receiver_id]);
    $sender_online = is_online($sender_last);
    $receiver_online = is_online($receiver_last);

    $payload = [
        'new_message' => $new_message,
        'sender_online' => $sender_online,
        'receiver_online' => $receiver_online,
        'timestamp' => time()
    ];
    echo "data: " . json_encode($payload) . "\n\n";
    ob_flush();
    flush();
    sleep(2);
}

echo ": done\n\n";
flush();
