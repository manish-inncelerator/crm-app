<?php
require_once '../vendor/autoload.php';
require_once '../function.php';
require_once '../database.php';

session_start();

$auth0 = new Auth0\SDK\Auth0([
    'domain' => 'fayyaztravels.us.auth0.com',
    'clientId' => 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    'clientSecret' => 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    'redirectUri' => 'https://crm.fayyaz.travel/callback.php',
    'cookieSecret' => 'your-secret-key-here'
]);

try {
    $user = $auth0->getUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser || !($dbUser['is_admin'] ?? false)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $targetUserId = $data['user_id'] ?? null;
    $receiveEmails = $data['receive_emails'] ?? 0;

    if (!$targetUserId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }

    $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);
    
    // Only Master Admins can toggle this for other admins, 
    // but any admin can toggle it for themselves, OR
    // Wait, the prompt said "add button in users to make them receive email".
    // I will allow Master Admins to toggle it for anyone, and Admins to toggle it for anyone if it's visible to them.
    // Standard validation: 
    if (!$isMasterAdmin && $dbUser['id'] != $targetUserId) {
        http_response_code(403);
        echo json_encode(['error' => 'Only Super Admins can change other users preferences']);
        exit;
    }

    $result = $database->update('users', ['receive_emails' => $receiveEmails], ['id' => $targetUserId]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
