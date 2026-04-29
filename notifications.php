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
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
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
                el.onclick = function () {
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

    window.onload = function () {
        if (localStorage.getItem('dashboard-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
        }
        updateMobileModeIcon();
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
    window.toggleDarkMode = function () {
        if (origToggleDarkMode) origToggleDarkMode();
        updateMobileModeIcon();
    };
    document.addEventListener('DOMContentLoaded', updateMobileModeIcon);
</script>
<style>
    /* ====================================
       PROFESSIONAL NOTIFICATIONS STYLES
       ==================================== */

    /* Page Header */
    .notifications-header {
        background: #fff;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border-left: 4px solid #4e1f00;
        margin-bottom: 2rem;
    }

    .notifications-title {
        font-size: 2rem;
        font-weight: 700;
        color: #2d3436;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .notifications-subtitle {
        color: #6c757d;
        margin-top: 0.5rem;
        font-size: 0.95rem;
    }

    /* Breadcrumb */
    .breadcrumb {
        background-color: transparent;
        padding: 0;
        margin-bottom: 1rem;
    }

    .breadcrumb-item a {
        color: #4e1f00;
        text-decoration: none;
    }

    .breadcrumb-item a:hover {
        color: #a97c50;
    }

    /* Stats Grid */
    .notification-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-box {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #f0f0f0;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-box:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        border-color: #4e1f00;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #4e1f00;
        margin: 0;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }

    /* Filter Section */
    .filter-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e9ecef;
        margin-bottom: 2rem;
    }

    .filter-title {
        font-size: 1rem;
        font-weight: 700;
        color: #2d3436;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-label {
        font-weight: 600;
        color: #2d3436;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-select {
        border: 1.5px solid #dee2e6;
        border-radius: 8px;
        padding: 0.65rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background-color: #fff;
    }

    .form-select:focus {
        border-color: #4e1f00;
        box-shadow: 0 0 0 0.2rem rgba(78, 31, 0, 0.15);
        outline: none;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .action-btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        border: 1.5px solid #4e1f00;
        background: #4e1f00;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .action-btn:hover {
        background: #a97c50;
        border-color: #a97c50;
    }

    .clear-btn {
        background: transparent;
        color: #4e1f00;
        border-color: #4e1f00;
    }

    .clear-btn:hover {
        background: #4e1f00;
        color: white;
    }

    /* Notifications Container */
    .notifications-container {
        max-width: 900px;
        margin: 0 auto;
    }

    /* Empty State */
    .empty-state {
        background: #fff;
        border-radius: 12px;
        padding: 3rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .empty-state-icon {
        font-size: 3.5rem;
        color: #4e1f00;
        margin-bottom: 1rem;
        opacity: 0.7;
    }

    .empty-state-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #2d3436;
        margin-bottom: 0.5rem;
    }

    .empty-state-text {
        color: #6c757d;
        font-size: 0.95rem;
    }

    /* Notification Group */
    .notification-group-date {
        font-size: 0.95rem;
        font-weight: 700;
        color: #4e1f00;
        margin-top: 2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f0f0f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Notification Item */
    .notification-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1.5px solid #e9ecef;
        transition: all 0.3s ease;
        display: flex;
        gap: 1rem;
    }

    .notification-card.unread {
        background: linear-gradient(to right, #fffbe7 0%, #fff 100%);
        border-color: #ffd54f;
    }

    .notification-card:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        border-color: #4e1f00;
    }

    .notification-icon-box {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .notification-icon-info {
        background: rgba(33, 150, 243, 0.1);
        color: #2196f3;
    }

    .notification-icon-success {
        background: rgba(76, 175, 80, 0.1);
        color: #4caf50;
    }

    .notification-icon-warning {
        background: rgba(255, 152, 0, 0.1);
        color: #ff9800;
    }

    .notification-icon-error {
        background: rgba(244, 67, 54, 0.1);
        color: #f44336;
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
        gap: 1rem;
    }

    .notification-title {
        font-size: 1rem;
        font-weight: 700;
        color: #2d3436;
        margin: 0;
    }

    .notification-time {
        font-size: 0.85rem;
        color: #a0a0a0;
        white-space: nowrap;
    }

    .notification-message {
        font-size: 0.95rem;
        color: #6c757d;
        line-height: 1.5;
        margin-bottom: 0.75rem;
    }

    .notification-footer {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .notification-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.75rem;
        background: #ff5252;
        color: white;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .notification-link {
        color: #4e1f00;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .notification-link:hover {
        background: rgba(78, 31, 0, 0.1);
        color: #a97c50;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        gap: 0.75rem;
        margin-top: 2.5rem;
        flex-wrap: wrap;
    }

    .pagination-link {
        padding: 0.65rem 1rem;
        border-radius: 8px;
        border: 1.5px solid #dee2e6;
        background: #fff;
        color: #4e1f00;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.9rem;
    }

    .pagination-link:hover:not(.active) {
        border-color: #4e1f00;
        background: #f8f9fa;
    }

    .pagination-link.active {
        background: linear-gradient(135deg, #4e1f00 0%, #a97c50 100%);
        color: white;
        border-color: transparent;
    }

    /* Dark Mode */
    body.dark-mode .notifications-header,
    body.dark-mode .notification-card,
    body.dark-mode .stat-box,
    body.dark-mode .filter-card,
    body.dark-mode .empty-state {
        background: #2d3436;
        border-color: #3a3f44;
        color: #fff;
    }

    body.dark-mode .notifications-title,
    body.dark-mode .notification-title {
        color: #fff;
    }

    body.dark-mode .notification-message,
    body.dark-mode .stat-label {
        color: #a0a0a0;
    }

    body.dark-mode .form-select {
        background: #3a3f44;
        border-color: #444c56;
        color: #fff;
    }

    body.dark-mode .form-select:focus {
        border-color: #a97c50;
    }

    body.dark-mode .pagination-link {
        background: #3a3f44;
        border-color: #444c56;
        color: #a97c50;
    }

    body.dark-mode .pagination-link:hover:not(.active) {
        background: #444c56;
        border-color: #555960;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .notification-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .notification-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .notification-card {
            flex-direction: column;
        }

        .filter-card .row>div {
            margin-bottom: 0.75rem;
        }
    }
</style>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <!-- Header -->
        <div class="notifications-header">
            <nav aria-label="breadcrumb" style="margin-bottom: 1rem;">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><i class="bi bi-bell"></i> Notifications</li>
                </ol>
            </nav>
            <h1 class="notifications-title">
                <i class="bi bi-bell-fill"></i> Notifications
            </h1>
            <p class="notifications-subtitle">Stay updated with your latest activity and alerts</p>
        </div>

        <!-- Statistics -->
        <div class="notification-stats">
            <div class="stat-box">
                <p class="stat-number"><?php echo $totalCount; ?></p>
                <p class="stat-label">Total Notifications</p>
            </div>
            <div class="stat-box">
                <p class="stat-number" style="color: #ff5252;"><?php echo $unreadCount; ?></p>
                <p class="stat-label">Unread</p>
            </div>
            <div class="stat-box">
                <p class="stat-number" style="color: #2196f3;"><?php echo $typeCounts['info']; ?></p>
                <p class="stat-label">Info</p>
            </div>
            <div class="stat-box">
                <p class="stat-number" style="color: #ff9800;">
                    <?php echo $typeCounts['warning'] + $typeCounts['error']; ?></p>
                <p class="stat-label">Alerts</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <div class="filter-title">
                <i class="bi bi-funnel"></i> Filter Notifications
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" onchange="applyFilter('type', this.value)">
                        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types (<?= $totalCount ?>)
                        </option>
                        <option value="info" <?= $type === 'info' ? 'selected' : '' ?>>ℹ️ Info (<?= $typeCounts['info'] ?>)
                        </option>
                        <option value="success" <?= $type === 'success' ? 'selected' : '' ?>>✓ Success
                            (<?= $typeCounts['success'] ?>)</option>
                        <option value="warning" <?= $type === 'warning' ? 'selected' : '' ?>>⚠️ Warning
                            (<?= $typeCounts['warning'] ?>)</option>
                        <option value="error" <?= $type === 'error' ? 'selected' : '' ?>>✕ Error
                            (<?= $typeCounts['error'] ?>)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" onchange="applyFilter('status', this.value)">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread (<?= $unreadCount ?>)
                        </option>
                        <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read
                            (<?= $totalCount - $unreadCount ?>)</option>
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
                    <button onclick="clearFilters()" class="btn clear-btn w-100">
                        <i class="bi bi-x-lg"></i> Clear
                    </button>
                </div>
            </div>
            <?php if ($unreadCount > 0): ?>
                <div class="action-buttons" style="margin-top: 1rem;">
                    <button onclick="markAllAsRead()" class="action-btn">
                        <i class="bi bi-check2-all"></i> Mark All as Read
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <div class="notifications-container">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h3 class="empty-state-title">No notifications</h3>
                    <p class="empty-state-text">All caught up! No notifications match your filters. Check back later for
                        updates.</p>
                </div>
            <?php else: ?>
                <?php
                $currentDate = null;
                foreach ($notifications as $n):
                    $notificationDate = date('Y-m-d', strtotime($n['created_at']));
                    if ($currentDate !== $notificationDate):
                        $currentDate = $notificationDate;
                        ?>
                        <div class="notification-group-date">
                            <?= date('F j, Y', strtotime($currentDate)) ?>
                        </div>
                    <?php endif; ?>

                    <div class="notification-card <?= !$n['is_read'] ? 'unread' : '' ?>">
                        <div class="notification-icon-box notification-icon-<?= $n['type'] ?>">
                            <?php
                            switch ($n['type']) {
                                case 'info':
                                    echo '<i class="bi bi-info-circle-fill"></i>';
                                    break;
                                case 'success':
                                    echo '<i class="bi bi-check-circle-fill"></i>';
                                    break;
                                case 'warning':
                                    echo '<i class="bi bi-exclamation-triangle-fill"></i>';
                                    break;
                                case 'error':
                                    echo '<i class="bi bi-x-circle-fill"></i>';
                                    break;
                                default:
                                    echo '<i class="bi bi-bell-fill"></i>';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="notification-content">
                            <div class="notification-header">
                                <h3 class="notification-title"><?= htmlspecialchars($n['title']) ?></h3>
                                <span class="notification-time"><?= date('H:i', strtotime($n['created_at'])) ?></span>
                            </div>
                            <p class="notification-message"><?= htmlspecialchars($n['message']) ?></p>
                            <div class="notification-footer">
                                <?php if (!$n['is_read']): ?>
                                    <span class="notification-badge">
                                        <i class="bi bi-star-fill"></i> New
                                    </span>
                                    <button onclick="markAsRead(<?= $n['id'] ?>)" class="notification-link">
                                        Mark as read
                                    </button>
                                <?php endif; ?>
                                <?php if ($n['ticket_id']): ?>
                                    <a href="tickets.php?id=<?= $n['ticket_id'] ?>&type=<?= $n['ticket_type'] ?>"
                                        class="notification-link">
                                        <i class="bi bi-arrow-right"></i> View Ticket
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $queryString = $queryString ? "&$queryString" : "";

                        if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 . $queryString ?>" class="pagination-link">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i . $queryString ?>" class="pagination-link <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 . $queryString ?>" class="pagination-link">
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