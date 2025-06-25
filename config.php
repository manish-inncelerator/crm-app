<?php
// Environment configuration
define('IS_DEVELOPMENT', true); // Set to false in production

// SSL Configuration
define('SSL_VERIFY', !IS_DEVELOPMENT); // Disable SSL verification in development
define('SSL_CERT_PATH', __DIR__ . '/certs/cacert.pem'); // Path to SSL certificate bundle

// Auth0 Configuration
define('AUTH0_DOMAIN', 'fayyaztravels.us.auth0.com');
define('AUTH0_CLIENT_ID', 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC');
define('AUTH0_CLIENT_SECRET', 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO');
define('AUTH0_REDIRECT_URI', 'https://crm.fyyz.link/callback.php');
define('AUTH0_COOKIE_SECRET', 'your-secret-key-here');
