<?php
require_once '../vendor/autoload.php';
require_once '../function.php';
require_once '../database.php';

// Start the session
session_start();

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;

header('Content-Type: application/json');

// Create a Guzzle client with SSL verification disabled for development
$httpClient = new Client([
    'verify' => false
]);

// Auth0 configuration
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

$auth0 = new Auth0($config);

try {
    $user = $auth0->getUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Get user data from database
    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    // Find unread notifications newer than lastId
    $conditions = [
        'user_id' => $dbUser['id'],
        'is_read' => 0
    ];
    
    if ($lastId > 0) {
        $conditions['id[>]'] = $lastId;
    }
    
    $newNotifications = $database->select('notifications', '*', [
        'AND' => $conditions,
        'ORDER' => ['id' => 'ASC']
    ]);

    echo json_encode([
        'success' => true,
        'notifications' => $newNotifications
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
