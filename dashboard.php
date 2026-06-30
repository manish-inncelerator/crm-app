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
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
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
    $is_admin = (bool)($dbUser['is_admin'] ?? false);

    // Get ticket counts from unified table
    $openStatuses = ['SUBMITTED', 'OPEN', 'IN_PROGRESS', 'UNDER_REVIEW', 'PENDING_APPROVAL'];
    $closedStatuses = ['RESOLVED', 'CLOSED', 'APPROVED', 'PROCESSED', 'REJECTED'];
    
    if ($dbUser['is_admin']) {
        // Admin sees all tickets
        $openTickets = $database->count('tickets_unified', ['status' => $openStatuses]);
        $closedTickets = $database->count('tickets_unified', ['status' => $closedStatuses]);
    } else {
        // Regular users see only their tickets
        $openTickets = $database->count('tickets_unified', [
            'user_id' => $dbUser['id'],
            'status' => $openStatuses
        ]);
        $closedTickets = $database->count('tickets_unified', [
            'user_id' => $dbUser['id'],
            'status' => $closedStatuses
        ]);
    }

    // Get unread notifications count
    $unreadNotifications = $database->count('notifications', [
        'user_id' => $dbUser['id'],
        'is_read' => false
    ]);



    // Get recent activity
    $activityPage = max(1, intval($_GET['activity_page'] ?? 1));
    $activityPerPage = 5;
    $activityOffset = ($activityPage - 1) * $activityPerPage;
    $totalActivities = $database->count('notifications', ['user_id' => $dbUser['id']]);
    $totalActivityPages = ceil($totalActivities / $activityPerPage);

    $recentActivity = $database->select('notifications', '*', [
        'user_id' => $dbUser['id'],
        'ORDER' => ['created_at' => 'DESC'],
        'LIMIT' => [$activityOffset, $activityPerPage]
    ]);

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
html_start('Dashboard - Fayyaz Travels CRM', ['assets/css/dashboard.css']);
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
                el.onclick = function () {
                    toggleSidebar();
                };
                document.body.appendChild(el);
            }
        } else {
            if (backdrop) backdrop.remove();
        }
    }

    function closeSidebarOnNav() {
        if (window.innerWidth <= 900) {
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.remove('open');
            if (backdrop) backdrop.remove();
        }
    }

    window.onclick = function (event) {
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
    window.toggleDarkMode = function () {
        if (origToggleDarkMode) origToggleDarkMode();
        updateMobileModeIcon();
    };
    document.addEventListener('DOMContentLoaded', updateMobileModeIcon);
</script>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content dashboard-main-area">
        <?php include 'components/navbar.php'; ?>
        <div class="hero-greeting">
            <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>"
                alt="Profile" class="greeting-avatar-large">
            <div class="hero-info">
                <h1>Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>!</h1>
                <p><?php echo htmlspecialchars($user['email'] ?? ''); ?> • Ready to manage your CRM?</p>
            </div>
        </div>

        <div class="dashboard-actions-header">
            <h2>Overview</h2>
            <div class="action-buttons-group">
                <?php if (!$is_admin): ?>
                <a href="create-ticket.php" class="btn-premium">
                    <i class="fas fa-plus"></i> New Ticket
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="bento-grid">
            <a href="dashboard.php" class="bento-card" title="Go to Dashboard">
                <div class="bento-icon-wrapper"><i class="fas fa-home"></i></div>
                <h3>Dashboard</h3>
                <p>Overview and quick stats</p>
                <div class="bento-badges">
                    <span class="bento-badge">Active</span>
                </div>
            </a>
            
            <a href="tickets.php" class="bento-card" title="Go to Tickets">
                <div class="bento-icon-wrapper"><i class="fas fa-ticket-alt"></i></div>
                <h3>Tickets</h3>
                <p>View and manage your tickets</p>
                <div class="bento-badges">
                    <span class="bento-badge"><?php echo $openTickets; ?> Open</span>
                    <span class="bento-badge secondary"><?php echo $closedTickets; ?> Closed</span>
                </div>
            </a>
            

            <a href="notifications.php" class="bento-card" title="Go to Notifications">
                <div class="bento-icon-wrapper"><i class="fas fa-bell"></i></div>
                <h3>Notifications</h3>
                <p>See your latest notifications</p>
                <div class="bento-badges">
                    <span class="bento-badge <?php echo $unreadNotifications > 0 ? 'alert' : 'secondary'; ?>">
                        <?php echo $unreadNotifications; ?> Unread
                    </span>
                </div>
            </a>
        </div>

        <!-- Recent Activity Timeline -->
        <div class="recent-activity-section" id="recent-activity">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <?php if (empty($recentActivity)): ?>
                <div class="empty-activity">
                    <i class="fas fa-inbox"></i>
                    <p>No recent activity found.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($recentActivity as $activity): 
                        // Determine icon and color based on notification type
                        $iconClass = 'fas fa-info';
                        if ($activity['type'] === 'success') $iconClass = 'fas fa-check';
                        if ($activity['type'] === 'warning') $iconClass = 'fas fa-exclamation-triangle';
                        if ($activity['type'] === 'error') $iconClass = 'fas fa-times';
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"><i class="<?php echo $iconClass; ?>"></i></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <h4 class="timeline-title"><?php echo htmlspecialchars($activity['title'] ?? 'Notification'); ?></h4>
                                    <span class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></span>
                                </div>
                                <p class="timeline-text"><?php echo htmlspecialchars($activity['message'] ?? ''); ?></p>
                                <?php if (!empty($activity['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($activity['link']); ?>" class="timeline-link">View Details &rarr;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination UI -->
                <?php if ($totalActivityPages > 1): ?>
                    <div class="d-flex justify-content-center mt-3 gap-2 align-items-center">
                        <?php if ($activityPage > 1): ?>
                            <a href="?activity_page=<?= $activityPage - 1 ?>#recent-activity" class="btn btn-sm btn-outline-primary"><i class="bi bi-chevron-left"></i> Previous</a>
                        <?php endif; ?>
                        <span class="text-muted small">Page <?= $activityPage ?> of <?= $totalActivityPages ?></span>
                        <?php if ($activityPage < $totalActivityPages): ?>
                            <a href="?activity_page=<?= $activityPage + 1 ?>#recent-activity" class="btn btn-sm btn-outline-primary">Next <i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'components/bottom_navbar.php'; ?>
<?php
// Print HTML end
html_end();
?>