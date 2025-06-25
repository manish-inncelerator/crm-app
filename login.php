<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

// Start the session
session_start();

// Create a Guzzle client with SSL verification disabled for development
$httpClient = new Client([
    'verify' => false, // Disable SSL verification for development
    'timeout' => 30,  // Increase timeout
    'connect_timeout' => 10,
    'http_errors' => false // Don't throw exceptions on HTTP errors
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

$auth0 = new Auth0($config);

try {
    // Get user info from Auth0
    $user = $auth0->getUser();
    writeLog('Login - User data from Auth0: ' . print_r($user, true));

    if ($user) {
        // Check if user exists in database
        $dbUser = $database->get('users', '*', [
            'auth0_id' => $user['sub']
        ]);

        if ($dbUser) {
            writeLog('Login - User is authenticated and exists in database, redirecting to dashboard');
            header('Location: dashboard.php');
            exit;
        } else {
            // User exists in Auth0 but not in database - clear session and show login
            writeLog('Login - User exists in Auth0 but not in database, clearing session');
            session_destroy();
        }
    }
} catch (\Exception $e) {
    writeLog('Login Error: ' . $e->getMessage(), 'ERROR');
    writeLog('Login Error trace: ' . $e->getTraceAsString(), 'ERROR');
    session_destroy();
}

// Print HTML start
html_start('Login - Fayyaz Travels CRM');

// Add login CSS
echo '<link rel="stylesheet" href="assets/css/login.css">';
?>

<div class="login-container">
    <div class="login-box">
        <div class="logo-container">
            <img src="https://fayyaztravels.com/visa/assets/images/main-logo.png" alt="Fayyaz Travels" class="logo">
        </div>
        <h1>Welcome Back</h1>
        <p>Please sign in to continue</p>

        <a href="<?php echo $auth0->login(); ?>" class="google-btn">
            <img src="https://imagepng.org/wp-content/uploads/2019/08/google-icon.png" alt="Google Icon" class="google-icon">
            Sign in with Google
        </a>
    </div>
</div>

<?php
// Print HTML end
html_end();
?>