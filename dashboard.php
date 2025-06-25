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

// Debug information
writeLog('Dashboard - Session data: ' . print_r($_SESSION, true));
writeLog('Dashboard - Auth0 Configuration: ' . print_r([
    'domain' => $config->getDomain(),
    'clientId' => $config->getClientId(),
    'redirectUri' => $config->getRedirectUri()
], true));

try {
    // Get user info from Auth0
    $user = $auth0->getUser();
    writeLog('Dashboard - Raw Auth0 user data: ' . print_r($user, true));

    if (!$user) {
        writeLog('Dashboard - No user found in Auth0 session, redirecting to login', 'ERROR');
        header('Location: login.php');
        exit;
    }

    writeLog('Dashboard - User data from Auth0: ' . print_r($user, true));

    // Get user data from database
    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        writeLog('Dashboard - User not found in database with auth0_id: ' . $user['sub'], 'ERROR');
        header('Location: login.php');
        exit;
    }

    writeLog('Dashboard - User data from database: ' . print_r($dbUser, true));

    // Get ticket counts
    $ticketTypes = ['estimate', 'supplier', 'general'];
    $openTickets = 0;
    $closedTickets = 0;

    foreach ($ticketTypes as $type) {
        $table = $type . '_tickets';
        if ($dbUser['is_admin']) {
            // Admin sees all tickets
            $openTickets += $database->count($table, [
                'status' => ['OPEN', 'IN_PROGRESS']
            ]);
            $closedTickets += $database->count($table, [
                'status' => ['RESOLVED', 'CLOSED']
            ]);
        } else {
            // Regular users see only their tickets
            $openTickets += $database->count($table, [
                'user_id' => $dbUser['id'],
                'status' => ['OPEN', 'IN_PROGRESS']
            ]);
            $closedTickets += $database->count($table, [
                'user_id' => $dbUser['id'],
                'status' => ['RESOLVED', 'CLOSED']
            ]);
        }
    }

    // Get unread notifications count
    $unreadNotifications = $database->count('notifications', [
        'user_id' => $dbUser['id'],
        'is_read' => false
    ]);

    // Get new messages count (unread comments on tickets)
    $newMessages = 0;
    foreach ($ticketTypes as $type) {
        $table = $type . '_tickets';

        if ($dbUser['is_admin']) {
            // For admin: count all new comments on all tickets
            $newMessages += $database->count('ticket_comments', [
                'ticket_type' => $type,
                'user_id[!]' => $dbUser['id'],
                'created_at[>]' => $dbUser['last_activity']
            ]);
        } else {
            // For regular users: count new comments on their tickets
            $ticketIds = $database->select($table, 'id', [
                'user_id' => $dbUser['id']
            ]);

            if (!empty($ticketIds)) {
                $newMessages += $database->count('ticket_comments', [
                    'ticket_id' => $ticketIds,
                    'ticket_type' => $type,
                    'user_id[!]' => $dbUser['id'],
                    'created_at[>]' => $dbUser['last_activity']
                ]);
            }
        }
    }

    // Update last_activity for current user
    $database->update('users', [
        'last_activity' => date('Y-m-d H:i:s')
    ], [
        'id' => $dbUser['id']
    ]);
} catch (\Exception $e) {
    writeLog('Dashboard Error: ' . $e->getMessage(), 'ERROR');
    header('Location: login.php');
    exit;
}

// Print HTML start
html_start('Dashboard - Fayyaz Travels CRM');
?>
<script>
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebar-backdrop');
        sidebar.classList.toggle('open');
        if (sidebar.classList.contains('open')) {
            if (!backdrop) {
                var el = document.createElement('div');
                el.className = 'sidebar-backdrop';
                el.id = 'sidebar-backdrop';
                el.onclick = function() {
                    toggleSidebar();
                };
                document.body.appendChild(el);
            }
        } else {
            if (backdrop) backdrop.remove();
        }
    }

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('dashboard-dark-mode', document.body.classList.contains('dark-mode'));
    }

    function toggleNavbarDropdown() {
        var dropdown = document.getElementById('navbar-dropdown');
        dropdown.classList.toggle('show');
    }

    function closeSidebarOnNav() {
        if (window.innerWidth <= 900) {
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.remove('open');
            if (backdrop) backdrop.remove();
        }
    }

    window.onload = function() {
        if (localStorage.getItem('dashboard-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    }

    window.onclick = function(event) {
        if (!event.target.matches('.navbar-avatar')) {
            var dropdown = document.getElementById('navbar-dropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    }

    function sidebarLogout() {
        window.location.href = 'logout.php';
    }

    function toggleSidebarMobile() {
        var sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }

    function updateMobileModeIcon() {
        var icon = document.querySelector('.mobile-bottom-navbar .mode i');
        if (!icon) return;
        if (document.body.classList.contains('dark-mode')) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }

    var origToggleDarkMode = window.toggleDarkMode;
    window.toggleDarkMode = function() {
        if (origToggleDarkMode) origToggleDarkMode();
        updateMobileModeIcon();
    };
    document.addEventListener('DOMContentLoaded', updateMobileModeIcon);
</script>
<?php
?>
</?php include 'components/navbar.php' ; ?>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <div class="greeting-section">
            <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>" alt="Profile" class="greeting-avatar">
            <div class="greeting-info">
                <h2>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>!</h2>
                <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
            </div>
        </div>
        <div class="content-header">
            <h1>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h1>
            <div class="header-actions">
                <button class="btn-primary">
                    <i class="fas fa-plus"></i> New Ticket
                </button>
                <button class="btn-primary">
                    <i class="fas fa-envelope"></i> New Message
                </button>
            </div>
        </div>
        <div class="dashboard-widgets">
            <a href="dashboard.php" class="widget-link" title="Go to Dashboard">
                <div class="widget">
                    <div class="widget-main-content">
                        <div class="widget-icon"><i class="fas fa-home"></i></div>
                        <h3>Dashboard</h3>
                        <div class="widget-content">
                            <p>Overview and quick stats</p>
                        </div>
                    </div>
                    <div class="widget-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </a>
            <a href="tickets.php" class="widget-link" title="Go to Tickets">
                <div class="widget">
                    <div class="widget-main-content">
                        <div class="widget-icon"><i class="fas fa-ticket-alt"></i></div>
                        <h3>Tickets</h3>
                        <div class="widget-content">
                            <p>View and manage your tickets</p>
                        </div>
                        <div>
                            <span class="widget-badge"><?php echo $openTickets; ?> Open</span>
                            <span class="widget-badge secondary"><?php echo $closedTickets; ?> Closed</span>
                        </div>
                    </div>
                    <div class="widget-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </a>
            <a href="messages.php" class="widget-link" title="Go to Messages">
                <div class="widget">
                    <div class="widget-main-content">
                        <div class="widget-icon"><i class="fas fa-envelope"></i></div>
                        <h3>Messages</h3>
                        <div class="widget-content">
                            <p>Check your recent messages</p>
                        </div>
                        <div>
                            <span class="widget-badge info"><?php echo $newMessages; ?> New</span>
                        </div>
                    </div>
                    <div class="widget-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </a>
            <a href="notifications.php" class="widget-link" title="Go to Notifications">
                <div class="widget">
                    <div class="widget-main-content">
                        <div class="widget-icon"><i class="fas fa-bell"></i></div>
                        <h3>Notifications</h3>
                        <div class="widget-content">
                            <p>See your latest notifications</p>
                        </div>
                        <div>
                            <span class="widget-badge alert"><?php echo $unreadNotifications; ?> New</span>
                        </div>
                    </div>
                    <div class="widget-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </a>
        </div>
    </div>
</div>
<?php include 'components/bottom_navbar.php'; ?>
<?php
// Print HTML end
html_end();
?>