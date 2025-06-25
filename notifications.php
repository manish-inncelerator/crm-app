<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';
require_once 'functions/notifications.php';

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

try {
    // Get user info from Auth0
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    // Get user data from database
    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }

    $user_id = $dbUser['id'];
    $isAdmin = isset($dbUser['is_admin']) && $dbUser['is_admin'] == 1;

    // Handle marking notification as read
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        markNotificationAsRead($_POST['notification_id']);
    }

    // Handle marking all as read
    if (isset($_POST['mark_all_read'])) {
        $database->update('notifications', [
            'is_read' => true
        ], [
            'user_id' => $user_id,
            'is_read' => false
        ]);
    }

    // Get filter parameters
    $type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $date = $_GET['date'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 10;

    // Build query conditions
    $conditions = ['user_id' => $user_id];

    if ($type !== 'all') {
        $conditions['type'] = $type;
    }

    if ($status !== 'all') {
        $conditions['is_read'] = ($status === 'read');
    }

    if ($date !== 'all') {
        $dateConditions = [
            'today' => ['created_at[>=]' => date('Y-m-d 00:00:00')],
            'week' => ['created_at[>=]' => date('Y-m-d 00:00:00', strtotime('-7 days'))],
            'month' => ['created_at[>=]' => date('Y-m-d 00:00:00', strtotime('-30 days'))]
        ];
        if (isset($dateConditions[$date])) {
            $conditions = array_merge($conditions, $dateConditions[$date]);
        }
    }

    // Get total count for pagination
    $total_notifications = $database->count('notifications', ['AND' => $conditions]);
    $total_pages = ceil($total_notifications / $per_page);
    $offset = ($page - 1) * $per_page;

    // Fetch notifications for the user (latest first) with pagination
    $notifications = $database->select('notifications', '*', [
        'AND' => $conditions,
        'ORDER' => ['created_at' => 'DESC'],
        'LIMIT' => [$offset, $per_page]
    ]);

    // Get counts for filters
    $totalCount = $database->count('notifications', ['user_id' => $user_id]);
    $unreadCount = $database->count('notifications', ['user_id' => $user_id, 'is_read' => false]);
    $typeCounts = [
        'info' => $database->count('notifications', ['user_id' => $user_id, 'type' => 'info']),
        'success' => $database->count('notifications', ['user_id' => $user_id, 'type' => 'success']),
        'warning' => $database->count('notifications', ['user_id' => $user_id, 'type' => 'warning']),
        'error' => $database->count('notifications', ['user_id' => $user_id, 'type' => 'error'])
    ];
} catch (\Exception $e) {
    writeLog('Notifications Error: ' . $e->getMessage(), 'ERROR');
    header('Location: login.php');
    exit;
}

html_start('Notifications');
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
        updateMobileModeIcon();
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
        updateMobileModeIcon();
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

    function markAsRead(notificationId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="mark_read" value="1">
            <input type="hidden" name="notification_id" value="${notificationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function markAllAsRead() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="mark_all_read" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }

    function applyFilter(type, value) {
        const url = new URL(window.location.href);
        url.searchParams.set(type, value);
        window.location.href = url.toString();
    }

    function clearFilters() {
        window.location.href = window.location.pathname;
    }

    var origToggleDarkMode = window.toggleDarkMode;
    window.toggleDarkMode = function() {
        if (origToggleDarkMode) origToggleDarkMode();
        updateMobileModeIcon();
    };
    document.addEventListener('DOMContentLoaded', updateMobileModeIcon);
</script>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="main-title">Notifications</h2>
                <div class="header-actions float-end">
                    <?php if ($unreadCount > 0): ?>
                        <button onclick="markAllAsRead()" class="float-end btn btn-outline-primary">
                            <i class="bi bi-check2-all"></i> Mark all as read
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filters-section" style="background:var(--card-bg);border-radius:12px;padding:16px;margin-bottom:24px;box-shadow:0 2px 8px rgba(78,31,0,0.06);">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" onchange="applyFilter('type', this.value)">
                        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types (<?= $totalCount ?>)</option>
                        <option value="info" <?= $type === 'info' ? 'selected' : '' ?>>Info (<?= $typeCounts['info'] ?>)</option>
                        <option value="success" <?= $type === 'success' ? 'selected' : '' ?>>Success (<?= $typeCounts['success'] ?>)</option>
                        <option value="warning" <?= $type === 'warning' ? 'selected' : '' ?>>Warning (<?= $typeCounts['warning'] ?>)</option>
                        <option value="error" <?= $type === 'error' ? 'selected' : '' ?>>Error (<?= $typeCounts['error'] ?>)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" onchange="applyFilter('status', this.value)">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread (<?= $unreadCount ?>)</option>
                        <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read (<?= $totalCount - $unreadCount ?>)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <select class="form-select" onchange="applyFilter('date', this.value)">
                        <option value="all" <?= $date === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="today" <?= $date === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $date === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="month" <?= $date === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button onclick="clearFilters()" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="notifications-list" style="max-width:800px;margin:0 auto;">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5" style="background:var(--card-bg);border-radius:12px;box-shadow:0 2px 8px rgba(78,31,0,0.06);">
                    <i class="fas fa-bell-slash" style="font-size:3em;color:#a98b6d;"></i>
                    <div class="mt-3" style="color:#6d4e1f;font-size:1.1em;">No notifications found</div>
                    <div class="mt-2" style="color:#a98b6d;">Try adjusting your filters or check back later</div>
                </div>
            <?php else: ?>
                <div class="notifications-group">
                    <?php
                    $currentDate = null;
                    foreach ($notifications as $n):
                        $notificationDate = date('Y-m-d', strtotime($n['created_at']));
                        if ($currentDate !== $notificationDate):
                            if ($currentDate !== null) echo '</div>'; // Close previous group
                            $currentDate = $notificationDate;
                    ?>
                            <div class="date-header" style="margin:24px 0 16px;color:#6d4e1f;font-weight:600;">
                                <?= date('F j, Y', strtotime($currentDate)) ?>
                            </div>
                            <div class="notifications-group-content">
                            <?php endif; ?>
                            <div class="notification-item" style="background:<?= $n['is_read'] ? 'var(--card-bg)' : '#fffbe7' ?>;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(78,31,0,0.06);transition:all 0.2s;">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="notification-icon" style="font-size:1.5em;margin-top:2px;">
                                        <?php
                                        switch ($n['type']) {
                                            case 'info':
                                                echo '<i class="bi bi-info-circle" style="color:#2196f3"></i>';
                                                break;
                                            case 'success':
                                                echo '<i class="bi bi-check-circle" style="color:#4caf50"></i>';
                                                break;
                                            case 'warning':
                                                echo '<i class="bi bi-exclamation-triangle" style="color:#ff9800"></i>';
                                                break;
                                            case 'error':
                                                echo '<i class="bi bi-x-circle" style="color:#f44336"></i>';
                                                break;
                                            case 'time':
                                                echo '<i class="bi bi-clock" style="color:#9c27b0"></i>';
                                                break;
                                            default:
                                                echo '<i class="bi bi-bell" style="color:#a97c50"></i>';
                                                break;
                                        }
                                        ?>
                                    </div>
                                    <div class="notification-content flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h5 class="notification-title mb-1" style="font-weight:600;font-size:1.1em;"><?= htmlspecialchars($n['title']) ?></h5>
                                            <div class="notification-time" style="font-size:0.9em;color:#a98b6d;">
                                                <?= date('H:i', strtotime($n['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div class="notification-message" style="color:#6d4e1f;margin:8px 0;">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </div>
                                        <div class="notification-actions d-flex align-items-center gap-2">
                                            <?php if (!$n['is_read']): ?>
                                                <span class="badge bg-danger">New</span>
                                                <button onclick="markAsRead(<?= $n['id'] ?>)" class="btn btn-sm btn-link text-primary p-0">
                                                    Mark as read
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($n['ticket_id']): ?>
                                                <a href="tickets.php?id=<?= $n['ticket_id'] ?>&type=<?= $n['ticket_type'] ?>" class="btn btn-sm btn-link text-primary p-0">
                                                    View Ticket
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                            </div> <!-- Close last group -->
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container" style="margin-top:32px;display:flex;justify-content:center;gap:8px;">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $queryString = $queryString ? "&$queryString" : "";

                        // Previous page
                        if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 . $queryString ?>" class="pagination-btn" style="background:var(--card-bg);color:var(--text-main);border:1.5px solid var(--sidebar-border);border-radius:12px;padding:8px 16px;text-decoration:none;transition:all 0.2s;">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        // Page numbers
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i . $queryString ?>" class="pagination-btn <?= $i === $page ? 'active' : '' ?>"
                                style="background:<?= $i === $page ? 'var(--sidebar-accent)' : 'var(--card-bg)' ?>;color:<?= $i === $page ? '#fff' : 'var(--text-main)' ?>;border:1.5px solid var(--sidebar-border);border-radius:12px;padding:8px 16px;text-decoration:none;transition:all 0.2s;">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        // Next page
                        if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 . $queryString ?>" class="pagination-btn" style="background:var(--card-bg);color:var(--text-main);border:1.5px solid var(--sidebar-border);border-radius:12px;padding:8px 16px;text-decoration:none;transition:all 0.2s;">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>