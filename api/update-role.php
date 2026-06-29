<?php
require_once '../vendor/autoload.php';
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
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['user_id'], $data['role_type'], $data['action_value'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $targetUserId = (int)$data['user_id'];
    $roleType = $data['role_type'];
    $actionValue = (int)$data['action_value']; // 1 for grant, 0 for revoke
    
    // Validate permissions
    if ($roleType === 'master' || $roleType === 'finance') {
        if (!$isMasterAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Only Master Admins can edit this role.']);
            exit;
        }
    }
    
    // Prevent removing your own master admin / admin status accidentally
    if ($targetUserId === (int)$dbUser['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot modify your own roles.']);
        exit;
    }
    
    $updates = [];
    if ($roleType === 'admin') {
        $updates['is_admin'] = $actionValue;
        if ($actionValue === 0) {
            // Demoting from admin also revokes master and finance
            $updates['is_master_admin'] = 0;
            $updates['can_view_financials'] = 0;
        }
    } elseif ($roleType === 'master') {
        $updates['is_master_admin'] = $actionValue;
        if ($actionValue === 1) {
            $updates['is_admin'] = 1; // Ensure they are regular admin too
        }
    } elseif ($roleType === 'finance') {
        $updates['can_view_financials'] = $actionValue;
    }
    
    if (!empty($updates)) {
        $database->update('users', $updates, ['id' => $targetUserId]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
