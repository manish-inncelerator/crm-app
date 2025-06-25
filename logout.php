<?php
require 'vendor/autoload.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

// Start the session
session_start();

// Create a Guzzle client with SSL verification disabled for development
$httpClient = new Client([
    'verify' => false // Disable SSL verification for development
]);

// Auth0 configuration
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fyyz.link/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

// Create session store with configuration
$sessionStore = new SessionStore($config);

$auth0 = new Auth0($config);

// Clear all session data
session_destroy();

// Clear Auth0 session
$auth0->clear();

// Get logout URL from Auth0 with proper returnTo URL
$logoutUrl = $auth0->logout('http://localhost/crm/login.php');

// Clear any remaining cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to Auth0 logout
header('Location: ' . $logoutUrl);
exit;
