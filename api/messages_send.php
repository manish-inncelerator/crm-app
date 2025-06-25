<?php
require_once '../database.php';
header('Content-Type: application/json');

ini_set('upload_max_filesize', '64M');
ini_set('post_max_size', '64M');

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Use POST.']);
    exit;
}

$sender_id = isset($_POST['sender_id']) ? intval($_POST['sender_id']) : 0;
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';

if ($sender_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid sender_id.']);
    exit;
}
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid receiver_id.']);
    exit;
}
if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

// Check if receiver exists
$receiver = $database->get('users', '*', ['id' => $receiver_id]);
if (!$receiver) {
    echo json_encode(['success' => false, 'error' => 'Receiver user not found.']);
    exit;
}

$attachment_path = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    $allowed_types = ['image/', 'audio/', 'video/'];
    $mime = mime_content_type($file['tmp_name']);
    $is_allowed = false;
    foreach ($allowed_types as $type) {
        if (strpos($mime, $type) === 0) {
            $is_allowed = true;
            break;
        }
    }
    if ($is_allowed) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('msg_', true) . '.' . $ext;
        $upload_dir = realpath(__DIR__ . '/../assets/uploads');
        if (!$upload_dir) {
            mkdir(__DIR__ . '/../assets/uploads', 0777, true);
            $upload_dir = realpath(__DIR__ . '/../assets/uploads');
        }
        $target = $upload_dir . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $attachment_path = 'assets/uploads/' . $filename;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
        exit;
    }
}

try {
    $insertData = [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'message' => $message,
        'is_admin' => 0,
        'status' => 'sent',
        'sent_at' => date('Y-m-d H:i:s')
    ];
    if ($attachment_path) {
        $insertData['attachment'] = $attachment_path;
    }
    $database->insert('messages', $insertData);
    echo json_encode(['success' => true, 'message' => 'Message sent successfully.', 'attachment' => $attachment_path]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
