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
    'verify' => false 
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

    $is_admin = $dbUser['is_admin'];

    // Get ticket counts
    $ticketTypes = ['estimate', 'supplier', 'general'];
    $openTickets = 0;
    $inProgressTickets = 0;
    $resolvedTickets = 0;
    $closedTickets = 0;

    foreach ($ticketTypes as $type) {
        $table = $type . '_tickets';
        $where = $is_admin ? [] : ['user_id' => $dbUser['id']];
        
        $openTickets += $database->count($table, array_merge($where, ['status' => 'OPEN']));
        $inProgressTickets += $database->count($table, array_merge($where, ['status' => 'IN_PROGRESS']));
        $resolvedTickets += $database->count($table, array_merge($where, ['status' => 'RESOLVED']));
        $closedTickets += $database->count($table, array_merge($where, ['status' => 'CLOSED']));
    }

    // Get recent tickets for the table
    $recentTickets = [];
    foreach ($ticketTypes as $type) {
        $table = $type . '_tickets';
        $tickets = $database->select($table, '*', [
            $is_admin ? 'LIMIT' : 'user_id' => $is_admin ? 5 : $dbUser['id'],
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => 5
        ]);
        foreach ($tickets as $t) {
            $t['type'] = ucfirst($type);
            $recentTickets[] = $t;
        }
    }
    
    // Sort combined tickets by date
    usort($recentTickets, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recentTickets = array_slice($recentTickets, 0, 8);

    // Get recent activity
    $recentActivity = $database->select('notifications', '*', [
        'user_id' => $dbUser['id'],
        'ORDER' => ['created_at' => 'DESC'],
        'LIMIT' => 8
    ]);

    $database->update('users', ['last_activity' => date('Y-m-d H:i:s')], ['id' => $dbUser['id']]);

} catch (\Exception $e) {
    writeLog('Dashboard Error: ' . $e->getMessage(), 'ERROR');
    header('Location: login.php');
    exit;
}

html_start('Dashboard - Fayyaz Travels CRM', ['assets/css/dashboard.css']);
?>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="dashboard-main-area">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">Dashboard Overview</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Welcome back, <?php echo htmlspecialchars($user['name']); ?>. Here's what's happening today.</p>
            </div>

            <!-- Metrics Banner -->
            <div class="metrics-banner">
                <div class="metric-card primary">
                    <span class="label">Open Tickets</span>
                    <span class="value"><?php echo $openTickets; ?></span>
                    <span class="trend" style="color: #64748b;">Awaiting action</span>
                </div>
                <div class="metric-card warning">
                    <span class="label">In Progress</span>
                    <span class="value"><?php echo $inProgressTickets; ?></span>
                    <span class="trend" style="color: #f59e0b;">Being handled</span>
                </div>
                <div class="metric-card success">
                    <span class="label">Resolved</span>
                    <span class="value"><?php echo $resolvedTickets; ?></span>
                    <span class="trend" style="color: #10b981;">Successfully closed</span>
                </div>
                <div class="metric-card danger">
                    <span class="label">Avg Response</span>
                    <span class="value">2.4h</span>
                    <span class="trend" style="color: #ef4444;">-12% vs last week</span>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Recent Tickets Panel -->
                <div class="content-panel">
                    <div class="panel-header">
                        <h3>Your Recent Tickets</h3>
                        <a href="tickets.php" style="font-size: 0.8rem; font-weight: 600; color: var(--sidebar-accent); text-decoration: none;">View All &rarr;</a>
                    </div>
                    <div class="panel-body" style="padding: 0;">
                        <table class="dense-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTickets)): ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No tickets found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--sidebar-accent);">#<?php echo $ticket['id']; ?></td>
                                            <td style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($ticket['subject'] ?? 'No Subject'); ?>
                                            </td>
                                            <td><span style="font-size: 0.8rem; opacity: 0.7;"><?php echo $ticket['type']; ?></span></td>
                                            <td>
                                                <?php 
                                                    $statusClass = strtolower($ticket['status'] === 'IN_PROGRESS' ? 'progress' : $ticket['status']);
                                                    echo '<span class="status-badge ' . $statusClass . '">' . str_replace('_', ' ', $ticket['status']) . '</span>';
                                                ?>
                                            </td>
                                            <td style="font-size: 0.8rem; color: var(--text-secondary);">
                                                <?php echo date('M d, H:i', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Activity Panel -->
                <div class="content-panel">
                    <div class="panel-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="panel-body">
                        <div class="timeline-compact">
                            <?php if (empty($recentActivity)): ?>
                                <p style="text-align: center; color: var(--text-secondary); font-size: 0.9rem; padding: 1rem;">No recent activity</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): 
                                    $icon = 'bi-info-circle';
                                    if ($activity['type'] === 'success') $icon = 'bi-check-circle';
                                    if ($activity['type'] === 'warning') $icon = 'bi-exclamation-circle';
                                    if ($activity['type'] === 'error') $icon = 'bi-x-circle';
                                ?>
                                    <div class="timeline-item-compact">
                                        <div class="timeline-icon"><i class="bi <?php echo $icon; ?>"></i></div>
                                        <div class="timeline-info">
                                            <div class="title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                            <div class="meta"><?php echo date('H:i', strtotime($activity['created_at'])); ?> &bull; <?php echo htmlspecialchars($activity['message']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php html_end(); ?>