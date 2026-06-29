<?php
require_once 'vendor/autoload.php';
require_once 'database.php';
use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

$start = microtime(true);
session_start();
$t1 = microtime(true);

$httpClient = new Client(['verify' => false]);
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);
$auth0 = new Auth0($config);
$t2 = microtime(true);

try {
    $user = $auth0->getUser();
} catch(Exception $e) {}
$t3 = microtime(true);

$count = $database->count('tickets_unified');
$t4 = microtime(true);

echo "Session Start: " . round(($t1 - $start)*1000, 2) . "ms\n";
echo "Auth0 Init: " . round(($t2 - $t1)*1000, 2) . "ms\n";
echo "Auth0 getUser: " . round(($t3 - $t2)*1000, 2) . "ms\n";
echo "DB Query (Count $count): " . round(($t4 - $t3)*1000, 2) . "ms\n";
echo "Total: " . round(($t4 - $start)*1000, 2) . "ms\n";
