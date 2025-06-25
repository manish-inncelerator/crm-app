<?php
require_once '../database.php';
session_start();

header('Content-Type: application/json');

// You may want to add your own authentication check here
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$user_id = $_SESSION['user_id'];
$is_admin = $database->get('users', 'is_admin', ['id' => $user_id]);
if (!$is_admin) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

$stmt = $database->pdo->prepare(
    "SELECT u.id, u.name, u.picture, u.last_activity, MAX(m.sent_at) as last_message_time, MAX(m.id) as last_message_id, 
            (SELECT message FROM messages WHERE id = MAX(m.id)) as last_message,
            SUM(CASE WHEN m.status = 'sent' AND m.receiver_id = ? THEN 1 ELSE 0 END) as unread_count
     FROM users u
     JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
     WHERE (m.receiver_id = ? OR m.sender_id = ?) AND u.is_admin = 0
     GROUP BY u.id, u.name, u.picture, u.last_activity
     ORDER BY last_message_time DESC"
);
$stmt->execute([$user_id, $user_id, $user_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success' => true, 'users' => $users]);
