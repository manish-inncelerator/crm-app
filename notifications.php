<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;

session_start();

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

try {
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }
    
    $user_id = $dbUser['id'];

    // Handle mark as read
    if (isset($_POST['mark_all_read'])) {
        $database->update('notifications', ['is_read' => true], ['user_id' => $user_id]);
    }

    $notifications = $database->select('notifications', '*', [
        'user_id' => $user_id,
        'ORDER' => ['created_at' => 'DESC'],
        'LIMIT' => 20
    ]);

} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Notifications - Fayyaz Travels CRM', ['assets/css/notifications.css']);
?>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="notifications-container">
            <div class="notifications-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>Notifications</h1>
                        <p>Latest updates and alerts from your workspace</p>
                    </div>
                    <form method="POST">
                        <button type="submit" name="mark_all_read" style="background: transparent; border: 1px solid var(--card-border); padding: 0.5rem 1rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); cursor: pointer;">
                            Mark all as read
                        </button>
                    </form>
                </div>
            </div>

            <div class="notifications-filter">
                <button class="filter-btn active">All</button>
                <button class="filter-btn">Unread</button>
                <button class="filter-btn">Alerts</button>
            </div>

            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div style="text-align: center; padding: 4rem; color: var(--text-secondary);">
                        <i class="bi bi-bell-slash" style="font-size: 3rem; opacity: 0.2; display: block; margin-bottom: 1rem;"></i>
                        <p>All caught up! No notifications yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): 
                        $icon = 'bi-info-circle';
                        $iconColor = 'icon-info';
                        if ($n['type'] === 'success') { $icon = 'bi-check-circle'; $iconColor = 'icon-success'; }
                        if ($n['type'] === 'warning') { $icon = 'bi-exclamation-triangle'; $iconColor = 'icon-warning'; }
                        if ($n['type'] === 'error') { $icon = 'bi-x-circle'; $iconColor = 'icon-error'; }
                    ?>
                        <div class="notification-card <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-icon">
                                <i class="bi <?php echo $icon; ?> <?php echo $iconColor; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($n['message']); ?></div>
                                <div class="notification-time"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></div>
                            </div>
                            <?php if ($n['ticket_id']): ?>
                                <a href="messages.php?ticket_id=<?php echo $n['ticket_id']; ?>&type=<?php echo $n['ticket_type']; ?>" style="font-size: 0.8rem; color: var(--sidebar-accent); text-decoration: none; font-weight: 600;">View Ticket</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php html_end(); ?>