<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Token\Parser;
use Auth0\SDK\Token\Validator;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create HTTP client
$httpClient = new GuzzleHttp\Client([
    'verify' => false, // Disable for development only
    'timeout' => 30,
    'connect_timeout' => 10,
    'http_errors' => false
]);

// Auth0 configuration with PKCE support
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fyyz.link/callback.php',
    cookieSecret: 'your-secret-key-here', // Should be at least 32 chars
    httpClient: $httpClient,
    usePkce: true // Enable PKCE
);

$auth0 = new Auth0($config);

try {
    writeLog('Callback - Starting Auth0 exchange with state: ' . ($_GET['state'] ?? 'null'));
    writeLog('Callback - GET parameters: ' . print_r($_GET, true));

    // Handle the callback and exchange the code
    $auth0->exchange();

    // Get credentials after successful exchange
    $credentials = $auth0->getCredentials();

    if ($credentials === null) {
        throw new \Exception('Failed to get credentials from Auth0');
    }

    // Parse and validate the token
    $token = $credentials->idToken;
    $parser = new Parser($config, $token);

    // Get token components
    $header = $parser->getHeader($token);
    $claims = $parser->getClaims($token);

    writeLog('Callback - Token header: ' . print_r($header, true));
    writeLog('Callback - Token claims: ' . print_r($claims, true));

    // Use claims as user info
    $userInfo = $claims;

    writeLog('Callback - User info: ' . print_r($userInfo, true));

    // Save or update user in database
    try {
        writeLog('Callback - Attempting to save user with data: ' . print_r([
            'auth0_id' => $userInfo['sub'],
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'] ?? null,
            'picture' => $userInfo['picture'] ?? null
        ], true));

        // First try to get the user
        $existingUser = $database->get('users', '*', [
            'auth0_id' => $userInfo['sub']
        ]);

        if ($existingUser) {
            // Update existing user
            $result = $database->update('users', [
                'email' => $userInfo['email'] ?? null,
                'name' => $userInfo['name'] ?? null,
                'picture' => $userInfo['picture'] ?? null
            ], [
                'auth0_id' => $userInfo['sub']
            ]);
            $userId = $existingUser['id'];
        } else {
            // Insert new user
            $result = $database->insert('users', [
                'auth0_id' => $userInfo['sub'],
                'email' => $userInfo['email'] ?? null,
                'name' => $userInfo['name'] ?? null,
                'picture' => $userInfo['picture'] ?? null
            ]);
            $userId = $database->id();
        }

        // Download and save user avatar locally
        function saveUserAvatar($userId, $imageUrl)
        {
            $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (!$ext) $ext = 'jpg';
            $avatarDir = __DIR__ . '/assets/user_avatars/';
            if (!is_dir($avatarDir)) mkdir($avatarDir, 0777, true);
            $localPath = "assets/user_avatars/user_{$userId}." . $ext;
            $fullPath = __DIR__ . '/' . $localPath;
            $imgData = @file_get_contents($imageUrl);
            if ($imgData) {
                file_put_contents($fullPath, $imgData);
                return $localPath;
            }
            return null;
        }
        if (!empty($userInfo['picture']) && !empty($userId)) {
            $localAvatar = saveUserAvatar($userId, $userInfo['picture']);
            if ($localAvatar) {
                $database->update('users', ['picture' => $localAvatar], ['id' => $userId]);
            }
        }
        // Set user_id in session for API authentication
        $_SESSION['user_id'] = $userId;

        writeLog('Callback - Database operation result: ' . print_r($result, true));
    } catch (\Exception $dbError) {
        writeLog('Callback - Database error: ' . $dbError->getMessage(), 'ERROR');
        writeLog('Callback - Database error trace: ' . $dbError->getTraceAsString(), 'ERROR');
        throw new \Exception('Database error: ' . $dbError->getMessage());
    }

    // Set session variables
    $_SESSION['user'] = $userInfo;
    $_SESSION['auth0_id'] = $userInfo['sub'];
    $_SESSION['access_token'] = $credentials->accessToken;
    $_SESSION['id_token'] = $credentials->idToken;
    $_SESSION['refresh_token'] = $credentials->refreshToken ?? null;

    writeLog('Callback - Session data: ' . print_r($_SESSION, true));

    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;
} catch (\Exception $e) {
    writeLog('Auth0 Error: ' . $e->getMessage(), 'ERROR');
    writeLog('Auth0 Error trace: ' . $e->getTraceAsString(), 'ERROR');

    // Clear session on error
    session_destroy();

    // Redirect with error message
    header('Location: login.php?error=' . urlencode('Authentication failed: ' . $e->getMessage()));
    exit;
}

ob_end_flush();
