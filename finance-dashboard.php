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

// Finance dashboard access check
$canViewFinancials = (bool)($dbUser['can_view_financials'] ?? false);
$isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);

if (!$canViewFinancials && !$isMasterAdmin) {
    header('Location: dashboard.php');
    exit;
}

// CSV Export Logic
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $pendingRefunds = $database->select('tickets_unified', [
        '[>]users' => ['user_id' => 'id']
    ], [
        'tickets_unified.id',
        'tickets_unified.created_at',
        'users.name(client_name)',
        'tickets_unified.status',
        'tickets_unified.amount',
        'tickets_unified.currency'
    ], [
        'tickets_unified.status' => ['UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED'],
        'ORDER' => ['tickets_unified.created_at' => 'DESC']
    ]);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pending_refunds_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Ticket ID', 'Date', 'Client Name', 'Status', 'Amount', 'Currency']);
    foreach ($pendingRefunds as $row) {
        fputcsv($output, [
            $row['id'], 
            date('Y-m-d H:i:s', strtotime($row['created_at'])), 
            $row['client_name'], 
            $row['status'], 
            number_format($row['amount'], 2, '.', ''), 
            $row['currency']
        ]);
    }
    fclose($output);
    exit;
}

// --- Data Fetching for Dashboard ---

// 1. Total Pending Value
$totalPending = $database->sum('tickets_unified', 'amount', [
    'status' => ['UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED']
]) ?: 0;

// 2. Total Processed This Month
$startOfMonth = date('Y-m-01 00:00:00');
$totalProcessed = $database->sum('tickets_unified', 'amount', [
    'status' => 'PROCESSED',
    'updated_at[>=]' => $startOfMonth
]) ?: 0;

// 3. Aging Report (Tickets pending by age)
$now = time();
$aging0_3 = 0; $val0_3 = 0;
$aging3_7 = 0; $val3_7 = 0;
$aging7plus = 0; $val7plus = 0;

$pendingTickets = $database->select('tickets_unified', '*', [
    'status' => ['UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED']
]);

foreach ($pendingTickets as $pt) {
    $days = ($now - strtotime($pt['created_at'])) / (60 * 60 * 24);
    $amt = floatval($pt['amount']);
    if ($days <= 3) { $aging0_3++; $val0_3 += $amt; }
    elseif ($days <= 7) { $aging3_7++; $val3_7 += $amt; }
    else { $aging7plus++; $val7plus += $amt; }
}

html_start('Finance Dashboard');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .finance-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .dark-mode .finance-card { background: #1f2937; border-color: #374151; }
    .stat-title { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; margin-bottom: 0.5rem; }
    .stat-value { font-size: 2.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0; }
    .dark-mode .stat-value { color: #f8fafc; }
    
    .aging-bar-container { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-top: 0.5rem; margin-bottom: 1.5rem; }
    .aging-bar { height: 100%; border-radius: 4px; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1 fw-bold"><i class="bi bi-graph-up-arrow text-success me-2"></i> Financial Overview</h2>
                    <p class="text-muted">Monitor monetary flow, aging reports, and pending approvals.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="bulk-operations.php" class="btn btn-outline-secondary"><i class="bi bi-list-check"></i> Bulk Updates</a>
                    <a href="?export=csv" class="btn btn-primary"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
                </div>
            </div>

            <!-- Top Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="finance-card border-start border-4 border-warning">
                        <div class="stat-title">Total Pending (Unprocessed)</div>
                        <div class="stat-value">$<?php echo number_format($totalPending, 2); ?></div>
                        <div class="text-muted small mt-2"><i class="bi bi-clock-history me-1"></i> Across <?php echo count($pendingTickets); ?> active tickets</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="finance-card border-start border-4 border-success">
                        <div class="stat-title">Processed This Month</div>
                        <div class="stat-value">$<?php echo number_format($totalProcessed, 2); ?></div>
                        <div class="text-muted small mt-2"><i class="bi bi-calendar-check me-1"></i> Since <?php echo date('M 1, Y'); ?></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Aging Report -->
                <div class="col-lg-6">
                    <div class="finance-card">
                        <h5 class="fw-bold mb-4 border-bottom pb-2">Aging Report (Pending)</h5>
                        
                        <?php $totalAgeCount = max(1, $aging0_3 + $aging3_7 + $aging7plus); ?>
                        
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <span class="fw-bold">0-3 Days Old</span>
                            <span class="text-muted small"><?php echo $aging0_3; ?> tickets ($<?php echo number_format($val0_3, 2); ?>)</span>
                        </div>
                        <div class="aging-bar-container">
                            <div class="aging-bar bg-success" style="width: <?php echo ($aging0_3 / $totalAgeCount) * 100; ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <span class="fw-bold">3-7 Days Old</span>
                            <span class="text-muted small"><?php echo $aging3_7; ?> tickets ($<?php echo number_format($val3_7, 2); ?>)</span>
                        </div>
                        <div class="aging-bar-container">
                            <div class="aging-bar bg-warning" style="width: <?php echo ($aging3_7 / $totalAgeCount) * 100; ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <span class="fw-bold text-danger">7+ Days Overdue</span>
                            <span class="text-danger small fw-bold"><?php echo $aging7plus; ?> tickets ($<?php echo number_format($val7plus, 2); ?>)</span>
                        </div>
                        <div class="aging-bar-container mb-0">
                            <div class="aging-bar bg-danger" style="width: <?php echo ($aging7plus / $totalAgeCount) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions / Alerts -->
                <div class="col-lg-6">
                    <div class="finance-card justify-content-start">
                        <h5 class="fw-bold mb-4 border-bottom pb-2">Attention Required</h5>
                        
                        <?php if ($aging7plus > 0): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-3">
                                <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                                <div>
                                    <strong>Overdue Action:</strong> You have <?php echo $aging7plus; ?> tickets pending for over a week!
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                        $pendingApprovalCount = $database->count('tickets_unified', ['status' => 'PENDING_APPROVAL']);
                        ?>
                        <?php if ($pendingApprovalCount > 0): ?>
                            <div class="alert alert-warning d-flex align-items-center mb-3">
                                <i class="bi bi-hourglass-split fs-4 me-3"></i>
                                <div>
                                    <strong>Approval Needed:</strong> <?php echo $pendingApprovalCount; ?> tickets are awaiting final Master Admin approval.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success d-flex align-items-center mb-3">
                                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                <div>
                                    <strong>All Caught Up:</strong> No tickets currently pending approval.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <a href="tickets.php" class="btn btn-light border w-100 fw-bold">View All Tickets</a>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
