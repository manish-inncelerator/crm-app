<?php
require_once '../database.php';
require_once '../vendor/autoload.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;

session_start();

$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here'
);
$auth0 = new Auth0($config);

$user = $auth0->getUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
if (!$dbUser || $dbUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['user_id']) || !isset($data['is_ex_employee'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$result = $database->update('users', [
    'is_ex_employee' => $data['is_ex_employee']
], [
    'id' => $data['user_id']
]);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update user status']);
}
