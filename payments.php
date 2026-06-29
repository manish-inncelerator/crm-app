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
    if (!$user) { header('Location: login.php'); exit; }
    
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) { header('Location: login.php'); exit; }

    $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);
    $canViewFinancials = (bool)($dbUser['can_view_financials'] ?? false);
    
    if (!$isMasterAdmin && !$canViewFinancials) {
        die("<h3>Access Denied</h3><p>You do not have financial tracking permissions.</p><a href='dashboard.php'>Return to Dashboard</a>");
    }

    // Fetch financial tickets (estimates, suppliers, invoices)
    $financialTickets = $database->select('tickets_unified', '*', [
        'type' => ['estimate', 'supplier'],
        'ORDER' => ['id' => 'DESC']
    ]);
    
    $totalExpectedRevenue = 0;
    $totalPendingLiabilities = 0;
    $paidSupplierPayments = 0;
    
    $paymentTimeline = [];

    foreach ($financialTickets as $t) {
        $meta = json_decode($t['metadata'], true) ?? [];
        
        if ($t['type'] === 'estimate') {
            $amount = floatval($meta['total_amount'] ?? 0);
            if ($t['status'] !== 'CLOSED' && $t['status'] !== 'REJECTED') {
                $totalExpectedRevenue += $amount;
            }
        } elseif ($t['type'] === 'supplier') {
            // This is a naive summation, assuming a single currency for simplicity in dashboard.
            // Ideally we'd map by currency, but we'll sum for a quick view.
            $amount = floatval($meta['complete_costing'] ?? 0); // or another field. We will use a placeholder logic if missing.
            if ($t['status'] === 'OPEN' || $t['status'] === 'IN_PROGRESS' || $t['status'] === 'APPROVED') {
                $totalPendingLiabilities += $amount;
                
                // Add to timeline if due date exists
                if (!empty($meta['due_date'])) {
                    $paymentTimeline[] = [
                        'id' => $t['id'],
                        'title' => $t['subtype'],
                        'date' => $meta['due_date'],
                        'supplier_id' => $meta['supplier_id'] ?? 'Unknown'
                    ];
                }
            } elseif ($t['status'] === 'PROCESSED' || $t['status'] === 'CLOSED') {
                $paidSupplierPayments += $amount;
            }
        }
    }
    
    // Sort timeline by date
    usort($paymentTimeline, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));
    $upcomingPayments = array_slice($paymentTimeline, 0, 5);

} catch (\Exception $e) {
    die("Error loading financials: " . $e->getMessage());
}

html_start('Financial Tracking');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .metric-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .dark-mode .metric-card { background: #1f2937; border-color: #374151; }
    
    .metric-title { font-size: 0.9rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .dark-mode .metric-title { color: #9ca3af; }
    .metric-value { font-size: 2rem; font-weight: 700; color: #111827; }
    .dark-mode .metric-value { color: #f9fafb; }
    .metric-value.revenue { color: #059669; }
    .metric-value.liability { color: #dc2626; }
    
    .timeline-box {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e5e7eb;
    }
    .dark-mode .timeline-box { background: #1f2937; border-color: #374151; }
    
    .timeline-item {
        display: flex;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .dark-mode .timeline-item { border-color: #374151; }
    .timeline-item:last-child { border-bottom: none; }
    
    .timeline-date {
        min-width: 100px;
        font-weight: 700;
        color: #2563eb;
    }
    
    .fin-kanban { display: flex; gap: 1.5rem; margin-top: 2rem; overflow-x: auto; padding-bottom: 1rem; }
    .fin-col { flex: 1; min-width: 300px; background: #f9fafb; border-radius: 12px; padding: 1rem; border: 1px solid #e5e7eb;}
    .dark-mode .fin-col { background: #111827; border-color: #374151;}
    .fin-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer; }
    .dark-mode .fin-card { background: #1f2937; border-color: #374151; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid px-4 py-4">
            <div class="mb-4">
                <h1 class="h3 mb-1 text-gray-800 fw-bold"><i class="bi bi-wallet2 text-success"></i> Financial Tracking Dashboard</h1>
                <p class="text-muted">Master overview of expected revenues and supplier liabilities.</p>
            </div>

            <!-- Top Metrics -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="metric-card border-bottom border-success border-4">
                        <div class="metric-title">Expected Ticket Revenue</div>
                        <div class="metric-value revenue">$<?= number_format($totalExpectedRevenue, 2) ?></div>
                        <div class="text-muted small mt-2">From Open/In-Progress Estimates</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card border-bottom border-danger border-4">
                        <div class="metric-title">Pending Supplier Payments</div>
                        <div class="metric-value liability">$<?= number_format($totalPendingLiabilities, 2) ?></div>
                        <div class="text-muted small mt-2">Unpaid Supplier Tickets</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card border-bottom border-primary border-4">
                        <div class="metric-title">Processed Payments</div>
                        <div class="metric-value">$<?= number_format($paidSupplierPayments, 2) ?></div>
                        <div class="text-muted small mt-2">Supplier Tickets Closed</div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Upcoming Deadlines -->
                <div class="col-lg-4">
                    <div class="timeline-box h-100">
                        <h5 class="fw-bold mb-4"><i class="bi bi-calendar-event"></i> Upcoming Due Dates</h5>
                        <?php if (empty($upcomingPayments)): ?>
                            <p class="text-muted">No upcoming supplier payments.</p>
                        <?php else: ?>
                            <?php foreach ($upcomingPayments as $payment): ?>
                                <?php 
                                    $isOverdue = strtotime($payment['date']) < time();
                                ?>
                                <div class="timeline-item" onclick="window.location='ticket-details.php?id=<?= $payment['id'] ?>'" style="cursor: pointer;">
                                    <div class="timeline-date <?= $isOverdue ? 'text-danger' : '' ?>">
                                        <?= date('M d', strtotime($payment['date'])) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($payment['title']) ?></div>
                                        <div class="text-muted small">Ticket #<?= $payment['id'] ?></div>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger mt-1">OVERDUE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Financial Kanban -->
                <div class="col-lg-8">
                    <div class="timeline-box h-100">
                        <h5 class="fw-bold mb-4"><i class="bi bi-kanban"></i> Payment Pipeline</h5>
                        
                        <div class="fin-kanban mt-0 border-0 p-0">
                            <!-- Awaiting Customer -->
                            <div class="fin-col">
                                <h6 class="fw-bold mb-3">Awaiting Customer Payment</h6>
                                <?php 
                                    $customerTickets = array_filter($financialTickets, fn($t) => $t['type'] === 'estimate' && $t['status'] === 'OPEN');
                                    foreach ($customerTickets as $t):
                                        $meta = json_decode($t['metadata'], true) ?? [];
                                ?>
                                    <div class="fin-card" onclick="window.location='ticket-details.php?id=<?= $t['id'] ?>'">
                                        <div class="fw-bold mb-1"><?= htmlspecialchars($meta['customer_name'] ?? 'Estimate') ?></div>
                                        <div class="text-muted small mb-2">#<?= $t['id'] ?></div>
                                        <div class="text-success fw-bold">$<?= number_format(floatval($meta['total_amount'] ?? 0), 2) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Ready to Pay Supplier -->
                            <div class="fin-col">
                                <h6 class="fw-bold mb-3">Ready to Pay Supplier</h6>
                                <?php 
                                    $supplierTickets = array_filter($financialTickets, fn($t) => $t['type'] === 'supplier' && ($t['status'] === 'APPROVED' || $t['status'] === 'OPEN'));
                                    foreach ($supplierTickets as $t):
                                        $meta = json_decode($t['metadata'], true) ?? [];
                                ?>
                                    <div class="fin-card" onclick="window.location='ticket-details.php?id=<?= $t['id'] ?>'">
                                        <div class="fw-bold mb-1"><?= htmlspecialchars($t['subtype']) ?></div>
                                        <div class="text-muted small mb-2">Due: <?= $meta['due_date'] ?? 'N/A' ?></div>
                                        <div class="text-danger fw-bold">Pending Action</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
