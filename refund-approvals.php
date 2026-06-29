<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

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

$sessionStore = new SessionStore($config);
$auth0 = new Auth0($config);

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $str) {
        $val = floor($diff / $secs);
        if ($val >= 1) return $val . ' ' . $str . ($val > 1 ? 's' : '') . ' ago';
    }
    return date('Y-m-d', $time);
}

function getPriorityClass($priority) {
    switch ($priority) {
        case 'URGENT': return 'bg-danger';
        case 'HIGH': return 'bg-warning text-dark';
        case 'MEDIUM': return 'bg-info text-dark';
        case 'LOW': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'CLOSED':
        case 'APPROVED':
        case 'PROCESSED':
        case 'RESOLVED':
            return '<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> ' . $status . '</span>';
        case 'IN_PROGRESS':
        case 'UNDER_REVIEW':
            return '<span class="badge bg-info"><i class="bi bi-arrow-repeat"></i> ' . $status . '</span>';
        case 'PENDING_APPROVAL':
            return '<span class="badge bg-orange text-white" style="background-color: #fd7e14;"><i class="bi bi-hourglass-split"></i> ' . $status . '</span>';
        case 'REJECTED':
            return '<span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> ' . $status . '</span>';
        case 'SUBMITTED':
        case 'OPEN':
        default:
            return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-fill"></i> ' . $status . '</span>';
    }
}

try {
    $user = $auth0->getUser();
    if (!$user) { header('Location: login.php'); exit; }
    
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) { header('Location: login.php'); exit; }

    $isAdmin = (bool)($dbUser['is_admin'] ?? false);
    $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);

    if (!$isMasterAdmin) {
        die("Unauthorized access. This page is only for super admins.");
    }

    $tableExists = $database->query("SHOW TABLES LIKE 'tickets_unified'")->fetchAll();
    if (empty($tableExists)) die("Please run the migration script first.");

    // Fetch Refund Tickets that are Pending Approval
    $openConditions = ['type' => 'refund', 'status' => 'PENDING_APPROVAL'];
    $openTickets = $database->select('tickets_unified', '*', array_merge($openConditions, ['ORDER' => ['id' => 'DESC']]));

    $userIds = array_unique(array_merge(array_column($openTickets, 'user_id'), array_column($openTickets, 'owner_id')));
    $users = [];
    if (!empty($userIds)) {
        $userRows = $database->select('users', ['id', 'name', 'email'], ['id' => array_filter($userIds)]);
        foreach ($userRows as $u) $users[$u['id']] = $u;
    }

} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Refund Approvals');
?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .ticket-row { cursor: pointer; transition: background-color 0.2s; }
    .ticket-row:hover { background-color: rgba(37, 99, 235, 0.05); }
    .ticket-meta { font-size: 0.8rem; color: #6c757d; }
    .ticket-title { font-weight: 600; color: #111827; }
    .dark-mode .ticket-title { color: #f9fafb; }
    .filters-bar { background: #fff; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .dark-mode .filters-bar { background: #1f2937; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid px-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-gray-800">Refund Approvals</h1>
                    <span class="badge bg-purple text-white"><i class="bi bi-star-fill"></i> Master Admin View</span>
                </div>
            </div>

            <!-- List View Container -->
            <div id="listView">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="approvalsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Requested By</th>
                                        <th>Subject</th>
                                        <th>Ref #</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($openTickets as $t): ?>
                                        <?php $creator = $users[$t['user_id']] ?? ['name'=>'Unknown','email'=>'']; ?>
                                        <tr class="ticket-row" onclick="window.location='ticket-details.php?id=<?= $t['id'] ?>'">
                                            <td><span class="text-muted fw-bold">#<?= str_pad($t['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="ms-2">
                                                        <div class="fw-bold"><?= htmlspecialchars($creator['name']) ?></div>
                                                        <div class="ticket-meta"><?= htmlspecialchars($creator['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="ticket-title"><?= htmlspecialchars($t['subtype']) ?></div>
                                                <div class="ticket-meta"><?= ucfirst($t['type']) ?> Request</div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['booking_reference'] ?: 'N/A') ?></span></td>
                                            <td><span class="badge <?= getPriorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                                            <td><?= getStatusBadge($t['status']) ?></td>
                                            <td>
                                                <div><?= date('M d, Y', strtotime($t['created_at'])) ?></div>
                                                <div class="ticket-meta"><?= timeAgo($t['created_at']) ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end([
    'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'
]); ?>
<script>
    $(document).ready(function() {
        const tableOptions = {
            dom: '<"row p-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row p-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 10,
            ordering: true,
            order: [[0, 'desc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search requests..."
            }
        };

        $('#approvalsTable').DataTable(tableOptions);
    });
</script>
